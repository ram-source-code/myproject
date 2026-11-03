<?php
declare(strict_types=1);

/**
 * Ink-and-watercolour sketching with GD only (no ImageMagick, no external tools), so the installer can draw
 * demo photos on any XAMPP. Drawn at 2× and scaled down for soft edges. Lines are tapered ribbons of ink
 * (two passes that don't quite agree), colour is laid as uneven transparent washes with darker pooled edges,
 * and the whole sheet gets paper grain at the end. Scenes are built in real perspective (see SketchCam).
 */

final class SketchCam
{
    public function __construct(
        public float $f,            // focal length, px per metre at 1 m
        public float $cx,           // screen centre x
        public float $horizon,      // horizon y on screen
        public float $eye = 1.6,    // eye height in metres
        public float $yaw = 0.0,    // camera turn (radians)
        public float $camX = 0.0,
        public float $camZ = 0.0,
    ) {
    }

    /** World (x right, y up, z away) → screen [x, y, depth]. */
    public function p(float $x, float $y, float $z): array
    {
        $dx = $x - $this->camX;
        $dz = $z - $this->camZ;
        $c = cos($this->yaw);
        $s = sin($this->yaw);
        $X = $dx * $c - $dz * $s;
        $Z = max(0.15, $dx * $s + $dz * $c);
        return [$this->cx + $this->f * $X / $Z, $this->horizon - $this->f * ($y - $this->eye) / $Z, $Z];
    }

    /** Pixels per metre at a world point (for small things drawn flat on screen). */
    public function scaleAt(float $x, float $z): float
    {
        return $this->f / $this->p($x, 0, $z)[2];
    }
}

/** A vertical rectangle in the world: origin, unit direction along it (horizontal), width and height. */
final class SketchFace
{
    public function __construct(public SketchCam $cam, public array $o, public array $u, public float $w, public float $h)
    {
    }

    /** Point on the face at (a metres along, b metres up, c metres out of the face towards the viewer). */
    public function pt(float $a, float $b, float $c = 0.0): array
    {
        // outward normal is u rotated −90° about y (faces drawn with u running left→right as seen)
        $nx = $this->u[2];
        $nz = -$this->u[0];
        $p = $this->cam->p($this->o[0] + $this->u[0] * $a + $nx * $c, $this->o[1] + $b, $this->o[2] + $this->u[2] * $a + $nz * $c);
        return [$p[0], $p[1]];
    }

    /** @return array<int, array{0: float, 1: float}> */
    public function quad(float $a0, float $b0, float $a1, float $b1, float $c = 0.0): array
    {
        return [$this->pt($a0, $b0, $c), $this->pt($a1, $b0, $c), $this->pt($a1, $b1, $c), $this->pt($a0, $b1, $c)];
    }
}

final class Sketch
{
    public \GdImage $im;
    public int $W;
    public int $H;
    public float $S = 2.0;                       // supersampling factor
    private array $paper = [246, 241, 230];
    private array $ink = [38, 36, 44];
    private array $alloc = [];

    public function __construct(private int $outW, private int $outH, int $seed)
    {
        mt_srand($seed);
        $this->W = (int) ($outW * $this->S);
        $this->H = (int) ($outH * $this->S);
        $this->im = imagecreatetruecolor($this->W, $this->H);
        imagealphablending($this->im, true);
        imagefilledrectangle($this->im, 0, 0, $this->W, $this->H, $this->col($this->paper));
        $this->ink = [mt_rand(30, 44), mt_rand(32, 40), mt_rand(40, 52)];   // each "sketcher" has their own ink
    }

    public static function r(float $a, float $b): float
    {
        return $a + (mt_rand() / mt_getrandmax()) * ($b - $a);
    }

    public static function pick(array $a): mixed
    {
        return $a[array_rand($a)];
    }

    private function col(array $rgb, int $alpha = 0): int
    {
        $k = "$rgb[0],$rgb[1],$rgb[2],$alpha";
        return $this->alloc[$k] ??= imagecolorallocatealpha($this->im, max(0, min(255, (int) $rgb[0])), max(0, min(255, (int) $rgb[1])), max(0, min(255, (int) $rgb[2])), max(0, min(127, $alpha)));
    }

    private static function flat(array $pts): array
    {
        $out = [];
        foreach ($pts as $p) {
            $out[] = (int) round($p[0]);
            $out[] = (int) round($p[1]);
        }
        return $out;
    }

    /* ───────── Ink ───────── */

    /** One tapered ribbon through points: thin at both ends, a little uneven in the middle, like a fibre-tip pen. */
    public function ribbon(array $pts, float $width, int $alpha = 8, ?array $rgb = null): void
    {
        $n = count($pts);
        if ($n < 2) {
            return;
        }
        $left = [];
        $right = [];
        $press = self::r(0.85, 1.1);
        for ($i = 0; $i < $n; $i++) {
            $a = $pts[max(0, $i - 1)];
            $b = $pts[min($n - 1, $i + 1)];
            $dx = $b[0] - $a[0];
            $dy = $b[1] - $a[1];
            $l = hypot($dx, $dy) ?: 1;
            $t = $i / ($n - 1);
            $w = $width * $this->S * 0.5 * $press * (0.35 + 0.65 * sqrt(sin(M_PI * min(1, max(0, $t * 0.92 + 0.04)))));
            $left[] = [$pts[$i][0] - $dy / $l * $w, $pts[$i][1] + $dx / $l * $w];
            $right[] = [$pts[$i][0] + $dy / $l * $w, $pts[$i][1] - $dx / $l * $w];
        }
        $poly = array_merge($left, array_reverse($right));
        imagefilledpolygon($this->im, self::flat($poly), $this->col($rgb ?? $this->ink, $alpha));
    }

    /** Points of a slightly bowed stroke from A to B. */
    private function bowed(float $x1, float $y1, float $x2, float $y2, float $jit): array
    {
        $len = hypot($x2 - $x1, $y2 - $y1) ?: 1;
        $nx = -($y2 - $y1) / $len;
        $ny = ($x2 - $x1) / $len;
        $bow = min(3.2 * $this->S, $len * 0.012);
        $o1 = self::r(-$bow, $bow);
        $o2 = self::r(-$bow, $bow);
        $x1 += self::r(-$jit, $jit);
        $y1 += self::r(-$jit, $jit);
        $x2 += self::r(-$jit, $jit);
        $y2 += self::r(-$jit, $jit);
        $steps = max(2, min(24, (int) ($len / (14 * $this->S))));
        $pts = [];
        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $mt = 1 - $t;
            // cubic with control points at 1/3 and 2/3 pushed off the line
            $bx = $mt ** 3 * $x1 + 3 * $mt * $mt * $t * ($x1 + ($x2 - $x1) / 3 + $nx * $o1) + 3 * $mt * $t * $t * ($x1 + ($x2 - $x1) * 2 / 3 + $nx * $o2) + $t ** 3 * $x2;
            $by = $mt ** 3 * $y1 + 3 * $mt * $mt * $t * ($y1 + ($y2 - $y1) / 3 + $ny * $o1) + 3 * $mt * $t * $t * ($y1 + ($y2 - $y1) * 2 / 3 + $ny * $o2) + $t ** 3 * $y2;
            $pts[] = [$bx, $by];
        }
        return $pts;
    }

    /** A hand-drawn line: a firm pass and a lighter one that overshoots a little. */
    public function line(array $a, array $b, float $w = 1.6, int $passes = 2): void
    {
        $len = hypot($b[0] - $a[0], $b[1] - $a[1]);
        if ($len < 0.5) {
            return;
        }
        $ux = ($b[0] - $a[0]) / $len;
        $uy = ($b[1] - $a[1]) / $len;
        $jit = min(1.6, 0.5 + $len * 0.002) * $this->S * 0.6;
        for ($i = 0; $i < $passes; $i++) {
            $over = ($i === 0 ? self::r(0.5, 3.0) : self::r(-1.5, 2.0)) * $this->S;
            $this->ribbon($this->bowed($a[0] - $ux * $over, $a[1] - $uy * $over, $b[0] + $ux * $over, $b[1] + $uy * $over, $jit),
                $i === 0 ? $w * 1.2 : $w * 0.65, $i === 0 ? 4 : 56);
        }
    }

    public function poly(array $pts, bool $closed = true, float $w = 1.6, int $passes = 2): void
    {
        $n = count($pts);
        for ($i = 0; $i < $n - ($closed ? 0 : 1); $i++) {
            $this->line($pts[$i], $pts[($i + 1) % $n], $w, $passes);
        }
    }

    /** Free curve through points (Chaikin-smoothed), for trees, fabric, hills. */
    public function curve(array $pts, float $w = 1.4, int $alpha = 12, float $jit = 0.8): void
    {
        $j = $jit * $this->S;
        $pts = array_map(fn($p) => [$p[0] + self::r(-$j, $j), $p[1] + self::r(-$j, $j)], $pts);
        for ($k = 0; $k < 2; $k++) {
            $out = [$pts[0]];
            for ($i = 0, $c = count($pts); $i < $c - 1; $i++) {
                [$ax, $ay] = $pts[$i];
                [$bx, $by] = $pts[$i + 1];
                $out[] = [$ax * 0.75 + $bx * 0.25, $ay * 0.75 + $by * 0.25];
                $out[] = [$ax * 0.25 + $bx * 0.75, $ay * 0.25 + $by * 0.75];
            }
            $out[] = $pts[count($pts) - 1];
            $pts = $out;
        }
        $this->ribbon($pts, $w, $alpha);
    }

    public function ellipse(float $cx, float $cy, float $rx, float $ry, float $w = 1.3, float $from = 0, float $sweep = 6.6): void
    {
        $pts = [];
        $steps = max(8, (int) (($rx + $ry) / (5 * $this->S)));
        $steps = min($steps, 40);
        $start = $from ?: self::r(0, M_PI * 2);
        for ($i = 0; $i <= $steps; $i++) {
            $a = $start + $sweep * $i / $steps;
            $pts[] = [$cx + cos($a) * $rx, $cy + sin($a) * $ry];
        }
        $this->curve($pts, $w, 14, 0.4);
    }

    /** Uneven parallel shading strokes inside a polygon (screen coords), at an angle in degrees. */
    public function hatch(array $poly, float $deg, float $gap, float $w = 0.8, float $keep = 0.85, int $alpha = 40): void
    {
        $a = deg2rad($deg);
        $dx = cos($a);
        $dy = sin($a);
        $nx = -$dy;
        $ny = $dx;
        $gap *= $this->S;
        $ds = array_map(fn($p) => $p[0] * $nx + $p[1] * $ny, $poly);
        $c = count($poly);
        for ($t = min($ds) + $gap * 0.5; $t < max($ds); $t += $gap * self::r(0.8, 1.3)) {
            $hits = [];
            for ($i = 0; $i < $c; $i++) {
                [$x1, $y1] = $poly[$i];
                [$x2, $y2] = $poly[($i + 1) % $c];
                $d1 = $x1 * $nx + $y1 * $ny - $t;
                $d2 = $x2 * $nx + $y2 * $ny - $t;
                if (($d1 < 0) !== ($d2 < 0)) {
                    $f = $d1 / ($d1 - $d2);
                    $hits[] = [$x1 + ($x2 - $x1) * $f, $y1 + ($y2 - $y1) * $f];
                }
            }
            usort($hits, fn($p, $q) => ($p[0] * $dx + $p[1] * $dy) <=> ($q[0] * $dx + $q[1] * $dy));
            for ($i = 0; $i + 1 < count($hits); $i += 2) {
                if (self::r(0, 1) > $keep) {
                    continue;
                }
                [$ax, $ay] = $hits[$i];
                [$bx, $by] = $hits[$i + 1];
                $len = hypot($bx - $ax, $by - $ay);
                if ($len < 3 * $this->S) {
                    continue;
                }
                $in = min($len * 0.2, self::r(1, 4) * $this->S);
                $ux = ($bx - $ax) / $len;
                $uy = ($by - $ay) / $len;
                $this->ribbon($this->bowed($ax + $ux * $in, $ay + $uy * $in, $bx - $ux * $in * self::r(0.3, 1.8), $by - $uy * $in, 0.6 * $this->S), $w, $alpha);
            }
        }
    }

    /* ───────── Watercolour ───────── */

    /** Wobble a polygon's outline: subdivide edges and push each point a little, the way a loaded brush wanders. */
    private function wander(array $poly, float $amt): array
    {
        $out = [];
        $c = count($poly);
        $seg = 30 * $this->S;
        for ($i = 0; $i < $c; $i++) {
            $a = $poly[$i];
            $b = $poly[($i + 1) % $c];
            $n = max(1, min(14, (int) (hypot($b[0] - $a[0], $b[1] - $a[1]) / $seg)));
            for ($k = 0; $k < $n; $k++) {
                $t = $k / $n;
                $out[] = [$a[0] + ($b[0] - $a[0]) * $t + self::r(-$amt, $amt), $a[1] + ($b[1] - $a[1]) * $t + self::r(-$amt, $amt)];
            }
        }
        return $out;
    }

    /** Keep the part of a polygon on one side of a line (n·p ≥ t) — Sutherland–Hodgman against one edge. */
    private static function clipHalf(array $poly, float $nx, float $ny, float $t): array
    {
        $out = [];
        $c = count($poly);
        for ($i = 0; $i < $c; $i++) {
            $a = $poly[$i];
            $b = $poly[($i + 1) % $c];
            $da = $a[0] * $nx + $a[1] * $ny - $t;
            $db = $b[0] * $nx + $b[1] * $ny - $t;
            if ($da >= 0) {
                $out[] = $a;
            }
            if (($da >= 0) !== ($db >= 0)) {
                $f = $da / ($da - $db);
                $out[] = [$a[0] + ($b[0] - $a[0]) * $f, $a[1] + ($b[1] - $a[1]) * $f];
            }
        }
        return $out;
    }

    /**
     * A transparent wash over a polygon, laid the way a brush lays it: in overlapping strokes across the shape,
     * each a little more or less loaded, often fading out towards one end, slightly off the ink, with pigment
     * pooled at part of the rim. $alpha is GD alpha (0 opaque … 127 clear); 75–100 reads as watercolour.
     */
    public function wash(array $poly, array $rgb, int $alpha = 88, float $spread = 3.0, ?float $fade = null): void
    {
        if (count($poly) < 3) {
            return;
        }
        $amt = $spread * $this->S;
        $dx = self::r(-2.5, 2.5) * $this->S;
        $dy = self::r(-2, 2) * $this->S;
        $poly = array_map(fn($p) => [$p[0] + $dx, $p[1] + $dy], $poly);
        $rgb = [$rgb[0] + mt_rand(-7, 7), $rgb[1] + mt_rand(-7, 7), $rgb[2] + mt_rand(-7, 7)];
        $p = $this->wander($poly, $amt);
        // brush direction: mostly horizontal-ish strokes, sometimes diagonal
        $ang = deg2rad(self::r(-20, 20) + (mt_rand(0, 3) === 0 ? 45 : 90));
        $nx = cos($ang);
        $ny = sin($ang);
        $ds = array_map(fn($q) => $q[0] * $nx + $q[1] * $ny, $p);
        $lo = min($ds);
        $hi = max($ds);
        $span = max(1.0, $hi - $lo);
        $fade ??= mt_rand(0, 2) === 0 ? self::r(0.35, 0.8) : 0.0;     // how much the wash dies away across the shape
        $bands = max(1, min(7, (int) ($span / (26 * $this->S))));
        $edges = [$lo - 1];
        for ($i = 1; $i < $bands; $i++) {
            $edges[] = $lo + $span * ($i + self::r(-0.25, 0.25)) / $bands;
        }
        $edges[] = $hi + 1;
        for ($i = 0; $i < $bands; $i++) {
            $part = self::clipHalf($p, $nx, $ny, $edges[$i] - self::r(0, 2) * $this->S);       // slight overlap = darker seam
            $part = self::clipHalf($part, -$nx, -$ny, -$edges[$i + 1]);
            if (count($part) < 3) {
                continue;
            }
            $t = ($i + 0.5) / $bands;
            $a = (int) round($alpha + mt_rand(-5, 6) + $fade * $t * (127 - $alpha) * 0.9);
            if ($a >= 124) {
                continue;
            }
            imagefilledpolygon($this->im, self::flat($part), $this->col($rgb, $a));
        }
        // a second, smaller glaze where pigment settles (towards the start of the wash)
        $glaze = self::clipHalf($this->wander($poly, $amt * 0.7), -$nx, -$ny, -($lo + $span * self::r(0.3, 0.6)));
        if (count($glaze) >= 3) {
            imagefilledpolygon($this->im, self::flat($glaze), $this->col($rgb, min(126, $alpha + 18)));
        }
        // pooled edge: a thin darker ribbon along part of the outline (pigment dries at the rim)
        $p[] = $p[0];
        $edge = [(int) ($rgb[0] * 0.84), (int) ($rgb[1] * 0.84), (int) ($rgb[2] * 0.86)];
        $from = mt_rand(0, max(0, count($p) - 3));
        $this->ribbon(array_slice(array_merge($p, $p), $from, max(3, (int) (count($p) * self::r(0.35, 0.8)))), 2.4, min(127, $alpha + 4), $edge);
    }

    /** Opaque paper over a shape: lets a nearer object hide what is behind it before it is inked. */
    public function knockout(array $poly): void
    {
        imagefilledpolygon($this->im, self::flat($poly), $this->col($this->paper));
    }

    /** A loose sky: a few wet blobs of blue, strongest at the top, leaving cloud-shaped paper between them. */
    public function sky(float $bottom, array $rgb = [150, 186, 214]): void
    {
        $n = mt_rand(3, 5);
        for ($i = 0; $i < $n; $i++) {
            $cx = $this->W * ($i + self::r(0.1, 0.9)) / $n;
            $cy = self::r(-0.1, 0.35) * $bottom;
            $rx = self::r(0.22, 0.4) * $this->W;
            $ry = self::r(0.22, 0.45) * $bottom;
            $pts = [];
            for ($k = 0; $k < 14; $k++) {
                $a = M_PI * 2 * $k / 14;
                $f = 1 + self::r(-0.3, 0.25);
                $pts[] = [$cx + cos($a) * $rx * $f, min($bottom, $cy + sin($a) * $ry * $f)];
            }
            $this->wash($pts, $rgb, (int) self::r(92, 106), 12, self::r(0.3, 0.7));
        }
        // a darker stroke or two across the very top
        $this->wash([[-20, -20], [$this->W + 20, -20], [$this->W + 20, $bottom * self::r(0.08, 0.2)], [-20, $bottom * self::r(0.15, 0.3)]], $rgb, 96, 10, 0.6);
    }

    /** Mountains along the horizon: ridge drawn in ink with a pale violet wash beneath. */
    public function ridge(float $baseY, float $height, array $rgb = [158, 156, 182], bool $ink = true): void
    {
        $pts = [];
        $x = -40 * $this->S;
        $y = $baseY - $height * self::r(0.3, 0.7);
        while ($x < $this->W + 40 * $this->S) {
            $pts[] = [$x, $y];
            $x += self::r(30, 90) * $this->S;
            $y = min($baseY - 4 * $this->S, max($baseY - $height, $y + self::r(-0.35, 0.35) * $height));
        }
        $this->wash(array_merge($pts, [[$this->W + 40 * $this->S, $baseY + 6 * $this->S], [-40 * $this->S, $baseY + 6 * $this->S]]), $rgb, 92, 2);
        if ($ink) {
            $this->curve($pts, 1.1, 40, 0.5);
        }
    }

    /** Finish: scale down (area-averaged, for soft even edges), lay paper grain over everything, write a JPEG. */
    public function save(): string
    {
        $out = imagecreatetruecolor($this->outW, $this->outH);
        imagecopyresampled($out, $this->im, 0, 0, 0, 0, $this->outW, $this->outH, $this->W, $this->H);
        imagedestroy($this->im);
        $gw = intdiv($this->outW, 4);
        $gh = intdiv($this->outH, 4);
        $g = imagecreatetruecolor($gw, $gh);
        for ($y = 0; $y < $gh; $y++) {
            for ($x = 0; $x < $gw; $x++) {
                $v = mt_rand(0, 255);
                imagesetpixel($g, $x, $y, ($v << 16) | ($v << 8) | $v);
            }
        }
        $grain = imagescale($g, $this->outW, $this->outH, IMG_BILINEAR_FIXED);
        imagedestroy($g);
        imagecopymerge($out, $grain, 0, 0, 0, 0, $this->outW, $this->outH, 7);
        imagedestroy($grain);
        $tmp = tempnam(sys_get_temp_dir(), 'aq') . '.jpg';
        imagejpeg($out, $tmp, 88);
        imagedestroy($out);
        return $tmp;
    }
}
