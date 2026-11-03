<?php
declare(strict_types=1);
/**
 * A small "pen" for hand-drawn SVG: lines are drawn as two slightly different strokes with wobble and overshoot,
 * areas are shaded with uneven hatching instead of flat fills. Deterministic (seeded) so builds are repeatable.
 * Used by bin/build-art.php only (build time); the site ships the resulting SVG.
 */

final class Pen
{
    /** @var array<string, string[]> path data per layer (layer = CSS class) */
    private array $layers = [];

    public function __construct(int $seed, private float $wobble = 1.0)
    {
        mt_srand($seed);
    }

    private static function r(float $a, float $b): float
    {
        return $a + (mt_rand() / mt_getrandmax()) * ($b - $a);
    }

    private static function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }

    private function add(string $layer, string $d): void
    {
        $this->layers[$layer][] = $d;
    }

    /** One pen stroke from A to B: a cubic curve bowed a little off the straight line. */
    private function stroke(float $x1, float $y1, float $x2, float $y2, float $jit): string
    {
        $len = hypot($x2 - $x1, $y2 - $y1) ?: 1;
        $nx = -($y2 - $y1) / $len;
        $ny = ($x2 - $x1) / $len;
        $bow = min(2.6, $len * 0.018) * $this->wobble;
        $o1 = self::r(-$bow, $bow);
        $o2 = self::r(-$bow, $bow);
        $ax = $x1 + self::r(-$jit, $jit);
        $ay = $y1 + self::r(-$jit, $jit);
        $bx = $x2 + self::r(-$jit, $jit);
        $by = $y2 + self::r(-$jit, $jit);
        $c1x = $x1 + ($x2 - $x1) * 0.33 + $nx * $o1;
        $c1y = $y1 + ($y2 - $y1) * 0.33 + $ny * $o1;
        $c2x = $x1 + ($x2 - $x1) * 0.66 + $nx * $o2;
        $c2y = $y1 + ($y2 - $y1) * 0.66 + $ny * $o2;
        return 'M' . self::n($ax) . ' ' . self::n($ay) . 'C' . self::n($c1x) . ' ' . self::n($c1y) . ' ' . self::n($c2x) . ' ' . self::n($c2y) . ' ' . self::n($bx) . ' ' . self::n($by);
    }

    /** A line drawn the way a hand does: two passes that don't quite agree. */
    public function line(float $x1, float $y1, float $x2, float $y2, string $layer = 'ink', int $passes = 2): void
    {
        $len = hypot($x2 - $x1, $y2 - $y1);
        $jit = min(1.4, 0.5 + $len * 0.004) * $this->wobble;
        for ($i = 0; $i < $passes; $i++) {
            // overshoot a touch past each end, as a quick pen does
            $ux = ($x2 - $x1) / ($len ?: 1);
            $uy = ($y2 - $y1) / ($len ?: 1);
            $over = $i === 0 ? self::r(0.4, 1.6) : self::r(-0.6, 0.8);
            $this->add($layer, $this->stroke($x1 - $ux * $over, $y1 - $uy * $over, $x2 + $ux * $over, $y2 + $uy * $over, $jit));
        }
    }

    /** @param array<int, array{0: float, 1: float}> $pts */
    public function poly(array $pts, bool $closed = false, string $layer = 'ink', int $passes = 2): void
    {
        $count = count($pts);
        for ($i = 0; $i < $count - 1; $i++) {
            $this->line($pts[$i][0], $pts[$i][1], $pts[$i + 1][0], $pts[$i + 1][1], $layer, $passes);
        }
        if ($closed) {
            $this->line($pts[$count - 1][0], $pts[$count - 1][1], $pts[0][0], $pts[0][1], $layer, $passes);
        }
    }

    public function rect(float $x, float $y, float $w, float $h, string $layer = 'ink', int $passes = 2): void
    {
        $this->poly([[$x, $y], [$x + $w, $y], [$x + $w, $y + $h], [$x, $y + $h]], true, $layer, $passes);
    }

    /** A smooth hand-drawn curve through points (one or two passes), for hills, ellipses and arches. */
    public function curve(array $pts, string $layer = 'ink', int $passes = 2, float $jit = 0.9): void
    {
        for ($p = 0; $p < $passes; $p++) {
            $q = array_map(fn($pt) => [$pt[0] + self::r(-$jit, $jit) * $this->wobble, $pt[1] + self::r(-$jit, $jit) * $this->wobble], $pts);
            $d = 'M' . self::n($q[0][0]) . ' ' . self::n($q[0][1]);
            for ($i = 1, $c = count($q); $i < $c - 1; $i++) {
                $mx = ($q[$i][0] + $q[$i + 1][0]) / 2;
                $my = ($q[$i][1] + $q[$i + 1][1]) / 2;
                $d .= 'Q' . self::n($q[$i][0]) . ' ' . self::n($q[$i][1]) . ' ' . self::n($mx) . ' ' . self::n($my);
            }
            $last = $q[count($q) - 1];
            $d .= 'L' . self::n($last[0]) . ' ' . self::n($last[1]);
            $this->add($layer, $d);
        }
    }

    /** An ellipse that doesn't close perfectly — the start and end overlap a little. */
    public function ellipse(float $cx, float $cy, float $rx, float $ry, string $layer = 'ink', int $passes = 1): void
    {
        for ($p = 0; $p < $passes; $p++) {
            $start = self::r(0, M_PI * 2);
            $steps = 18;
            $sweep = M_PI * 2 + self::r(0.15, 0.45);
            $pts = [];
            for ($i = 0; $i <= $steps; $i++) {
                $a = $start + $sweep * $i / $steps;
                $k = 1 + self::r(-0.04, 0.04);
                $pts[] = [$cx + cos($a) * $rx * $k, $cy + sin($a) * $ry * $k];
            }
            $this->curve($pts, $layer, 1, 0.3);
        }
    }

    /** Pointed (Islamic) arch from (x, base) of width w and height h, as an open curve. */
    public function pointedArch(float $x, float $base, float $w, float $h, string $layer = 'ink', int $passes = 1): void
    {
        $spring = $base - $h * 0.55;
        $this->curve([
            [$x, $base], [$x, $spring], [$x + $w * 0.08, $spring - $h * 0.28], [$x + $w * 0.5, $base - $h],
            [$x + $w * 0.92, $spring - $h * 0.28], [$x + $w, $spring], [$x + $w, $base],
        ], $layer, $passes, 0.35);
    }

    /** Round arch window/door. */
    public function roundArch(float $x, float $base, float $w, float $h, string $layer = 'ink', int $passes = 1): void
    {
        $r = $w / 2;
        $pts = [[$x, $base], [$x, $base - $h + $r]];
        for ($i = 1; $i < 8; $i++) {
            $a = M_PI - M_PI * $i / 8;
            $pts[] = [$x + $r + cos($a) * $r, $base - $h + $r - sin($a) * $r];
        }
        $pts[] = [$x + $w, $base - $h + $r];
        $pts[] = [$x + $w, $base];
        $this->curve($pts, $layer, $passes, 0.3);
    }

    /**
     * Hatch the inside of a polygon with uneven parallel strokes (the shading a hand gives instead of a fill).
     * @param array<int, array{0: float, 1: float}> $poly
     */
    public function hatch(array $poly, float $angleDeg, float $gap, string $layer = 'light', float $keep = 0.9): void
    {
        $a = deg2rad($angleDeg);
        $dx = cos($a);
        $dy = sin($a);
        // project polygon onto the normal to find the sweep range
        $nxv = -$dy;
        $nyv = $dx;
        $ds = array_map(fn($p) => $p[0] * $nxv + $p[1] * $nyv, $poly);
        for ($t = min($ds) + $gap * 0.5; $t < max($ds); $t += $gap * self::r(0.8, 1.25)) {
            $hits = [];
            $c = count($poly);
            for ($i = 0; $i < $c; $i++) {
                [$x1, $y1] = $poly[$i];
                [$x2, $y2] = $poly[($i + 1) % $c];
                $d1 = $x1 * $nxv + $y1 * $nyv - $t;
                $d2 = $x2 * $nxv + $y2 * $nyv - $t;
                if (($d1 < 0) !== ($d2 < 0)) {
                    $f = $d1 / ($d1 - $d2);
                    $hits[] = [$x1 + ($x2 - $x1) * $f, $y1 + ($y2 - $y1) * $f];
                }
            }
            usort($hits, fn($p, $q) => ($p[0] * $dx + $p[1] * $dy) <=> ($q[0] * $dx + $q[1] * $dy));
            for ($i = 0; $i + 1 < count($hits); $i += 2) {
                if (self::r(0, 1) > $keep) {
                    continue;                                   // the hand skips a stroke now and then
                }
                [$ax, $ay] = $hits[$i];
                [$bx, $by] = $hits[$i + 1];
                $len = hypot($bx - $ax, $by - $ay);
                if ($len < 2) {
                    continue;
                }
                $inset = min($len * 0.18, self::r(0.5, 2.5));   // strokes stop short of the outline
                $ux = ($bx - $ax) / $len;
                $uy = ($by - $ay) / $len;
                $this->add($layer, $this->stroke($ax + $ux * $inset, $ay + $uy * $inset, $bx - $ux * $inset * self::r(0.3, 1.6), $by - $uy * $inset, 0.5));
            }
        }
    }

    /** Render as inline SVG: one <path> per layer; colours come from CSS classes (art-ink, art-light, …). */
    public function svg(int $w, int $h, string $class, string $label = ''): string
    {
        $out = '<svg class="art ' . $class . '" viewBox="0 0 ' . $w . ' ' . $h . '" xmlns="http://www.w3.org/2000/svg"'
            . ($label !== '' ? ' role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '"' : ' aria-hidden="true" focusable="false"') . '>';
        foreach ($this->layers as $layer => $ds) {
            $out .= '<path class="art-' . $layer . '" d="' . implode('', $ds) . '"/>';
        }
        return $out . '</svg>';
    }

    /** Standalone SVG file with fixed colours (for CSS backgrounds, where currentColor can't reach). */
    public function svgFile(int $w, int $h, array $colors, array $widths): string
    {
        $out = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" fill="none" stroke-linecap="round" stroke-linejoin="round">';
        foreach ($this->layers as $layer => $ds) {
            $out .= '<path stroke="' . ($colors[$layer] ?? '#1f5641') . '" stroke-width="' . ($widths[$layer] ?? 1.4) . '" d="' . implode('', $ds) . '"/>';
        }
        return $out . "</svg>\n";
    }
}
