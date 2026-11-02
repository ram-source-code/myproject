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
 * Validate an uploaded file and decode it into a truecolor GD image (steps 1–4).
 * @throws ImageUploadException
 */
function load_uploaded_image(array $file, int $minW = 200, int $minH = 150): GdImage
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
    if (!is_uploaded_file($tmp) && !defined('AQARAT_CLI') && !defined('AQARAT_SEEDING')) {
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
    if ($w < $minW || $h < $minH) {
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
    return $src;
}

function ensure_upload_dir(): string
{
    $dir = config('upload_dir');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new ImageUploadException(t('img_failed'));
    }
    return $dir;
}

/** Office logo: square, cover-cropped, 256×256 WebP. Returns the stored filename. */
function process_logo_upload(array $file): string
{
    $src = load_uploaded_image($file, 64, 64);
    $dir = ensure_upload_dir();
    $logo = resize_cover($src, 256, 256);
    imagedestroy($src);
    $name = bin2hex(random_bytes(16)) . '.webp';
    $ok = imagewebp($logo, $dir . '/' . $name, (int) config('webp_quality'));
    imagedestroy($logo);
    if (!$ok) {
        @unlink($dir . '/' . $name);
        throw new ImageUploadException(t('img_failed'));
    }
    return $name;
}

/**
 * Property photo: full size (fit 1600×1200) + 640×427 thumbnail, both WebP.
 * @return array{filename:string, thumb:string, width:int, height:int}
 * @throws ImageUploadException
 */
function process_image_upload(array $file, ?string $watermark = null): array
{
    $src = load_uploaded_image($file);
    $phash = image_dhash($src);          // computed on the ORIGINAL so re-uploads of un-watermarked copies still match
    $color = image_dominant_color($src);
    $dir = config('upload_dir');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        imagedestroy($src);
        throw new ImageUploadException(t('img_failed'));
    }

    $base  = bin2hex(random_bytes(16));
    $full  = resize_contain($src, (int) config('image_max_width'), (int) config('image_max_height'));
    if ($watermark !== null && config('watermark')) {
        apply_watermark($full, $watermark);
    }
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

    return ['filename' => $fullName, 'thumb' => $thumbName, 'width' => $fw, 'height' => $fh, 'phash' => $phash, 'color' => $color];
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

/** Average colour as #rrggbb (slightly darkened so white text on a placeholder stays readable). */
function image_dominant_color(GdImage $img): string
{
    $px = imagecreatetruecolor(1, 1);
    imagecopyresampled($px, $img, 0, 0, 0, 0, 1, 1, imagesx($img), imagesy($img));
    $c = imagecolorat($px, 0, 0);
    imagedestroy($px);
    $f = 0.85;
    return sprintf('#%02x%02x%02x', (int) ((($c >> 16) & 0xFF) * $f), (int) ((($c >> 8) & 0xFF) * $f), (int) (($c & 0xFF) * $f));
}

/* ───────────── Perceptual hash (dHash) for duplicate-photo detection ───────────── */

/** 64-bit difference hash as 16 hex chars. Robust to resizing, recompression and small edits. */
function image_dhash(GdImage $img): string
{
    $small = imagecreatetruecolor(9, 8);
    imagecopyresampled($small, $img, 0, 0, 0, 0, 9, 8, imagesx($img), imagesy($img));
    $bits = '';
    for ($y = 0; $y < 8; $y++) {
        $prev = null;
        for ($x = 0; $x < 9; $x++) {
            $c = imagecolorat($small, $x, $y);
            $lum = (($c >> 16) & 0xFF) * 299 + (($c >> 8) & 0xFF) * 587 + ($c & 0xFF) * 114;
            if ($prev !== null) {
                $bits .= $lum > $prev ? '1' : '0';
            }
            $prev = $lum;
        }
    }
    imagedestroy($small);
    $hex = '';
    foreach (str_split($bits, 4) as $nib) {
        $hex .= dechex(bindec($nib));
    }
    return $hex;
}

function hash_distance(string $a, string $b): int
{
    if (strlen($a) !== 16 || strlen($b) !== 16) {
        return 64;
    }
    $d = 0;
    foreach ([0, 8] as $o) {
        $x = hexdec(substr($a, $o, 8)) ^ hexdec(substr($b, $o, 8));
        $d += substr_count(decbin($x), '1');
    }
    return $d;
}

/* ───────────── Watermark: office logo (or site icon) in the corner ───────────── */

function apply_watermark(GdImage $img, string $logoPath): void
{
    if (!is_file($logoPath)) {
        return;
    }
    $logo = str_ends_with($logoPath, '.webp') ? @imagecreatefromwebp($logoPath) : @imagecreatefrompng($logoPath);
    if (!$logo instanceof GdImage) {
        return;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $size = (int) max(40, round(min($w, $h) * 0.11));
    $mark = new_canvas($size, $size);
    imagecopyresampled($mark, $logo, 0, 0, 0, 0, $size, $size, imagesx($logo), imagesy($logo));
    imagedestroy($logo);
    // 70 % opacity: scale every pixel's alpha
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $c = imagecolorat($mark, $x, $y);
            $a = ($c >> 24) & 0x7F;
            $na = (int) (127 - (127 - $a) * 0.7);
            imagesetpixel($mark, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
        }
    }
    $pad = (int) round($size * 0.25);
    $x = $w - $size - $pad;
    $y = $h - $size - $pad;
    imagealphablending($img, true);
    // soft white plate behind the logo for legibility on dark photos
    $plate = imagecolorallocatealpha($img, 255, 255, 255, 95);
    imagefilledellipse($img, $x + intdiv($size, 2), $y + intdiv($size, 2), (int) ($size * 1.25), (int) ($size * 1.25), $plate);
    imagecopy($img, $mark, $x, $y, 0, 0, $size, $size);
    imagedestroy($mark);
}

/* ───────────── Videos (stored as uploaded; validated by MIME and container magic bytes) ───────────── */

const ALLOWED_VIDEO_MIME = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];

/** @return array{filename:string, mime:string, size:int} */
function process_video_upload(array $file): array
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $mb = (int) (config('max_video_bytes') / 1048576);
    if ($err !== UPLOAD_ERR_OK) {
        throw new ImageUploadException(in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? t('video_too_large', ['mb' => $mb]) : t('img_failed'));
    }
    $tmp = (string) $file['tmp_name'];
    if (!is_uploaded_file($tmp) && !defined('AQARAT_CLI') && !defined('AQARAT_SEEDING')) {
        throw new ImageUploadException(t('img_failed'));
    }
    $size = (int) filesize($tmp);
    if ($size <= 0 || $size > config('max_video_bytes')) {
        throw new ImageUploadException(t('video_too_large', ['mb' => $mb]));
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $head = (string) file_get_contents($tmp, false, null, 0, 16);
    $isIsoBmff = substr($head, 4, 4) === 'ftyp';                 // MP4 / MOV
    $isWebm = str_starts_with($head, "\x1A\x45\xDF\xA3");      // EBML (WebM/Matroska)
    if (!isset(ALLOWED_VIDEO_MIME[$mime]) || ($mime === 'video/webm' ? !$isWebm : !$isIsoBmff)) {
        throw new ImageUploadException(t('video_bad_type'));
    }
    $dir = ensure_upload_dir();
    $name = bin2hex(random_bytes(16)) . '.' . ALLOWED_VIDEO_MIME[$mime];
    $ok = (defined('AQARAT_CLI') || defined('AQARAT_SEEDING')) ? copy($tmp, $dir . '/' . $name) : move_uploaded_file($tmp, $dir . '/' . $name);
    if (!$ok) {
        throw new ImageUploadException(t('img_failed'));
    }
    @chmod($dir . '/' . $name, 0644);
    return ['filename' => $name, 'mime' => $mime, 'size' => $size];
}

function delete_video_file(array $v): void
{
    $name = basename((string) ($v['filename'] ?? ''));
    if (preg_match('/^[a-f0-9]{32}\.(mp4|mov|webm)$/', $name)) {
        @unlink(config('upload_dir') . '/' . $name);
    }
}
