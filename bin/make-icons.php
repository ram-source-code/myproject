<?php
// Generates the PWA / notification icons into assets/img with GD.  php bin/make-icons.php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
$out = dirname(__DIR__) . '/assets/img';

function house(GdImage $im, float $cx, float $cy, float $s, int $color): void
{
    // roof + body + door cut-out drawn on a 24-unit grid
    $p = static fn(array $pts) => array_merge(...array_map(static fn($q) => [(int) round($cx + ($q[0] - 12) * $s), (int) round($cy + ($q[1] - 12) * $s)], $pts));
    imagefilledpolygon($im, $p([[2.5, 11], [12, 3], [21.5, 11], [19.5, 11], [12, 5.2], [4.5, 11]]), $color);
    imagefilledpolygon($im, $p([[5.5, 10.5], [12, 5.3], [18.5, 10.5], [18.5, 20.5], [14, 20.5], [14, 15], [10, 15], [10, 20.5], [5.5, 20.5]]), $color);
}

function icon(int $size, float $scale, bool $round, string $file, bool $badge = false): void
{
    $big = $size * 4;                       // supersample for smooth edges
    $im = imagecreatetruecolor($big, $big);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    if (!$badge) {
        $bg = imagecolorallocate($im, 31, 86, 65);
        if ($round) {
            $r = (int) ($big * 0.22);
            imagefilledrectangle($im, $r, 0, $big - $r, $big, $bg);
            imagefilledrectangle($im, 0, $r, $big, $big - $r, $bg);
            foreach ([[$r, $r], [$big - $r, $r], [$r, $big - $r], [$big - $r, $big - $r]] as [$x, $y]) {
                imagefilledellipse($im, $x, $y, 2 * $r, 2 * $r, $bg);
            }
        } else {
            imagefilledrectangle($im, 0, 0, $big, $big, $bg);
        }
        // subtle accent sun
        imagefilledellipse($im, (int) ($big * 0.74), (int) ($big * 0.27), (int) ($big * 0.13 * $scale), (int) ($big * 0.13 * $scale), imagecolorallocate($im, 233, 169, 43));
    }
    house($im, $big / 2, $big / 2 + $big * 0.02, $big / 24 * $scale, imagecolorallocate($im, 255, 255, 255));
    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $size, $size, $big, $big);
    imagepng($dst, $file, 9);
    echo basename($file), PHP_EOL;
}

icon(192, 0.72, true, "$out/icon-192.png");
icon(512, 0.72, true, "$out/icon-512.png");
icon(512, 0.55, false, "$out/icon-maskable-512.png");   // safe zone for maskable icons
icon(180, 0.68, false, "$out/apple-touch-icon.png");
icon(72, 0.9, false, "$out/badge-72.png", true);         // monochrome badge for Android status bar

// ---- iOS launch screens (apple-touch-startup-image), sizes listed in IOS_SPLASH (app/helpers.php)
$sizes = [
    [1320, 2868], [1206, 2622], [1290, 2796], [1179, 2556], [1284, 2778], [1170, 2532],
    [1125, 2436], [1242, 2688], [828, 1792], [750, 1334],
    [1488, 2266], [1640, 2360], [1668, 2388], [1620, 2160], [2048, 2732],
];
@mkdir("$out/splash", 0755, true);
$font = dirname(__DIR__) . '/app/fonts/Inter-Bold.ttf';
foreach ($sizes as [$w, $h]) {
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 31, 86, 65));
    $s = (int) (min($w, $h) * 0.28);
    $cx = intdiv($w, 2);
    $cy = intdiv($h, 2) - intdiv($s, 4);
    // rounded tile
    $tile = imagecolorallocate($im, 23, 63, 48);
    $r = (int) ($s * 0.22);
    imagefilledrectangle($im, $cx - intdiv($s, 2) + $r, $cy - intdiv($s, 2), $cx + intdiv($s, 2) - $r, $cy + intdiv($s, 2), $tile);
    imagefilledrectangle($im, $cx - intdiv($s, 2), $cy - intdiv($s, 2) + $r, $cx + intdiv($s, 2), $cy + intdiv($s, 2) - $r, $tile);
    foreach ([[-1, -1], [1, -1], [-1, 1], [1, 1]] as [$dx, $dy]) {
        imagefilledellipse($im, $cx + $dx * (intdiv($s, 2) - $r), $cy + $dy * (intdiv($s, 2) - $r), 2 * $r, 2 * $r, $tile);
    }
    house($im, $cx, $cy + $s * 0.03, $s / 24 * 0.72, imagecolorallocate($im, 255, 255, 255));
    imagefilledellipse($im, (int) ($cx + $s * 0.24), (int) ($cy - $s * 0.24), (int) ($s * 0.1), (int) ($s * 0.1), imagecolorallocate($im, 233, 169, 43));
    if (is_file($font)) {
        $fs = $s * 0.16;
        $bb = imagettfbbox($fs, 0, $font, 'AQARAT');
        imagettftext($im, $fs, 0, (int) ($cx - ($bb[2] - $bb[0]) / 2), (int) ($cy + $s * 0.85), imagecolorallocate($im, 213, 230, 220), $font, 'AQARAT');
    }
    imagepng($im, "$out/splash/splash-{$w}x{$h}.png", 9);
    imagedestroy($im);
}
echo count($sizes), " iOS launch screens\n";
