<?php
declare(strict_types=1);

/**
 * Secure image upload pipeline using GD.
 *
 *  1. Validate the PHP upload (error code, is_uploaded_file, size).
 *  2. Sniff the real MIME type with finfo (never trust the client's type/extension).
 *  3. Check dimensions with getimagesize() BEFORE decoding to reject decompression bombs.
 *  4. Decode with GD, fix JPEG EXIF orientation.
 *  5. Resize (keeping aspect ratio) + generate a cover-cropped thumbnail.
 *  6. Re-encode to WebP. Re-encoding strips EXIF/GPS metadata and any payload
 *     smuggled inside the original file (polyglots), so only clean pixels are stored.
 *  7. Store under a random, unguessable filename.
 */
final class ImageUploadException extends RuntimeException {}

const ALLOWED_IMAGE_MIME = [
    'image/jpeg' => IMAGETYPE_JPEG,
    'image/png'  => IMAGETYPE_PNG,
    'image/gif'  => IMAGETYPE_GIF,
    'image/webp' => IMAGETYPE_WEBP,
];

/**
 * Normalise $_FILES['x'] (single or multiple) into a list of file arrays.
 */
function normalize_files(?array $f): array
{
    if (!$f || !isset($f['name'])) {
        return [];
    }
    if (!is_array($f['name'])) {
        return [$f];
    }
    $out = [];
    foreach (array_keys($f['name']) as $i) {
        $out[] = [
            'name'     => $f['name'][$i],
            'type'     => $f['type'][$i],
            'tmp_name' => $f['tmp_name'][$i],
            'error'    => $f['error'][$i],
            'size'     => $f['size'][$i],
        ];
    }
    // Drop empty slots (no file chosen)
    return array_values(array_filter($out, static fn($x) => $x['error'] !== UPLOAD_ERR_NO_FILE));
}

/**
 * @return array{filename:string, thumb:string, width:int, height:int}
 * @throws ImageUploadException
 */
function process_image_upload(array $file): array
{
    if (!function_exists('imagewebp')) {
        throw new ImageUploadException(t('img_no_webp'));
    }

    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new ImageUploadException(match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('img_too_large', ['mb' => (int) (config('max_upload_bytes') / 1048576)]),
            UPLOAD_ERR_PARTIAL => t('img_partial'),
            default => t('img_failed'),
        });
    }

    $tmp = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmp) && !defined('AQARAT_CLI')) {
        throw new ImageUploadException(t('img_failed'));
    }

    $size = filesize($tmp);
    if ($size === false || $size === 0 || $size > config('max_upload_bytes')) {
        throw new ImageUploadException(t('img_too_large', ['mb' => (int) (config('max_upload_bytes') / 1048576)]));
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset(ALLOWED_IMAGE_MIME[$mime])) {
        throw new ImageUploadException(t('img_bad_type'));
    }

    $info = @getimagesize($tmp);
    if ($info === false || $info[2] !== ALLOWED_IMAGE_MIME[$mime]) {
        throw new ImageUploadException(t('img_bad_type'));
    }
    [$w, $h] = $info;
    if ($w < 200 || $h < 150) {
        throw new ImageUploadException(t('img_too_small'));
    }
    if ($w * $h > config('max_pixels')) {
        throw new ImageUploadException(t('img_too_many_px'));
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png'  => @imagecreatefrompng($tmp),
        'image/gif'  => @imagecreatefromgif($tmp),
        'image/webp' => @imagecreatefromwebp($tmp),
    };
    if (!$src instanceof GdImage) {
        throw new ImageUploadException(t('img_corrupt'));
    }

    if ($mime === 'image/jpeg') {
        $src = fix_orientation($src, $tmp);
    }
    if (!imageistruecolor($src)) {
        imagepalettetotruecolor($src); // GIF/8-bit PNG → truecolor, required by imagewebp
    }

    $dir = config('upload_dir');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($src);
        throw new ImageUploadException(t('img_failed'));
    }

    $base  = bin2hex(random_bytes(16));
    $full  = resize_contain($src, (int) config('image_max_width'), (int) config('image_max_height'));
    $thumb = resize_cover($src, (int) config('thumb_width'), (int) config('thumb_height'));
    imagedestroy($src);

    $fullName  = $base . '.webp';
    $thumbName = $base . '_t.webp';
    $q = (int) config('webp_quality');

    $ok = imagewebp($full, $dir . '/' . $fullName, $q) && imagewebp($thumb, $dir . '/' . $thumbName, $q - 5);
    $fw = imagesx($full);
    $fh = imagesy($full);
    imagedestroy($full);
    imagedestroy($thumb);

    if (!$ok) {
        @unlink($dir . '/' . $fullName);
        @unlink($dir . '/' . $thumbName);
        throw new ImageUploadException(t('img_failed'));
    }

    return ['filename' => $fullName, 'thumb' => $thumbName, 'width' => $fw, 'height' => $fh];
}

function fix_orientation(GdImage $img, string $path): GdImage
{
    if (!function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($path);
    $o = (int) ($exif['Orientation'] ?? 1);
    $rotated = match ($o) {
        3 => imagerotate($img, 180, 0),
        6 => imagerotate($img, -90, 0),
        8 => imagerotate($img, 90, 0),
        default => null,
    };
    if ($rotated instanceof GdImage) {
        imagedestroy($img);
        return $rotated;
    }
    return $img;
}

function new_canvas(int $w, int $h): GdImage
{
    $c = imagecreatetruecolor($w, $h);
    imagealphablending($c, false);
    imagesavealpha($c, true);
    imagefill($c, 0, 0, imagecolorallocatealpha($c, 0, 0, 0, 127));
    return $c;
}

/** Scale down to fit inside $maxW×$maxH (never upscales). */
function resize_contain(GdImage $src, int $maxW, int $maxH): GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);
    $ratio = min(1, $maxW / $w, $maxH / $h);
    $nw = max(1, (int) round($w * $ratio));
    $nh = max(1, (int) round($h * $ratio));
    $dst = new_canvas($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/** Center-crop to exactly $tw×$th. */
function resize_cover(GdImage $src, int $tw, int $th): GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = max($tw / $w, $th / $h);
    $cw = (int) round($tw / $scale);
    $ch = (int) round($th / $scale);
    $sx = (int) (($w - $cw) / 2);
    $sy = (int) (($h - $ch) / 2);
    $dst = new_canvas($tw, $th);
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $tw, $th, $cw, $ch);
    return $dst;
}

function delete_image_files(array $img): void
{
    $dir = config('upload_dir');
    foreach (['filename', 'thumb'] as $k) {
        $name = basename((string) ($img[$k] ?? ''));
        if ($name !== '' && preg_match('/^[a-f0-9]{32}(_t)?\.webp$/', $name)) {
            @unlink($dir . '/' . $name);
        }
    }
}
