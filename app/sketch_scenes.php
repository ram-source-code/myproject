<?php
declare(strict_types=1);

/**
 * Demo listing "photos" as ink-and-watercolour sketches of what property in the Kurdistan Region actually looks like:
 * stone-clad villas with a columned porch, a front wall with an iron gate and rooftop water tanks, solar heater and
 * satellite dish; concrete houses with a flat roof and a yard wall; gated compounds of apartment towers; shop rows
 * with roller shutters, split AC units and neighbourhood-generator cables; plots with survey stakes, concrete frames
 * going up and the mountains behind; and furnished rooms (living room with a kilim rug, kitchen).
 */

require_once __DIR__ . '/sketch.php';

const SK_W = 1200;
const SK_H = 800;

/** Palettes (watercolour pigments, not screen colours). */
function sk_pal(): array
{
    return [
        'sky'    => [150, 186, 214],
        'stone'  => [[224, 204, 168], [232, 218, 190], [214, 190, 150], [228, 210, 180]],
        'plaster' => [[226, 206, 170], [210, 184, 146], [232, 222, 200], [204, 196, 182], [218, 176, 140]],
        'shade'  => [104, 112, 150],
        'glass'  => [116, 152, 176],
        'green'  => [124, 152, 92],
        'dkgreen' => [74, 112, 76],
        'olive'  => [138, 150, 100],
        'ground' => [214, 194, 156],
        'road'   => [168, 166, 164],
        'mount'  => [156, 154, 182],
        'wood'   => [168, 118, 80],
        'metal'  => [96, 100, 108],
        'red'    => [184, 70, 56],
        'saffron' => [228, 168, 60],
    ];
}

/* ───────── Shapes in the world ───────── */

/**
 * A box (building mass) standing on the ground: front face from A along angle φ, depth going away.
 * Returns the two visible faces; $side is 'right' or 'left'.
 */
function sk_mass(SketchCam $c, array $A, float $phi, float $w, float $d, float $h, string $side): array
{
    $u = [cos($phi), 0.0, sin($phi)];
    $dd = [-$u[2], 0.0, $u[0]];
    $front = new SketchFace($c, $A, $u, $w, $h);
    if ($side === 'right') {
        $B = [$A[0] + $u[0] * $w, $A[1], $A[2] + $u[2] * $w];
        $sf = new SketchFace($c, $B, $dd, $d, $h);
    } else {
        $C = [$A[0] + $dd[0] * $d, $A[1], $A[2] + $dd[2] * $d];
        $sf = new SketchFace($c, $C, [-$dd[0], 0.0, -$dd[2]], $d, $h);
    }
    return [$front, $sf];
}

/** Knock out, ink and wash a mass. $lit is the front colour; the side gets the same colour plus a shadow glaze. */
function sk_draw_mass(Sketch $s, SketchFace $front, SketchFace $side, array $lit, bool $sideInShade = true): void
{
    $fq = $front->quad(0, 0, $front->w, $front->h);
    $sq = $side->quad(0, 0, $side->w, $side->h);
    $s->knockout($fq);
    $s->knockout($sq);
    $s->poly($fq, true, 1.8);
    $s->poly($sq, true, 1.8);
    $s->wash($fq, $lit, 86);
    $s->wash($sq, $lit, 84);
    $p = sk_pal();
    $s->wash($sideInShade ? $sq : $fq, $p['shade'], 100, 2);
    if ($sideInShade) {          // cast shadow on the ground, thrown away from the light
        $len = min(6.0, $side->h * 0.45);
        $s->wash([$side->pt(0, 0), $side->pt($side->w, 0), $side->pt($side->w * 1.1, 0, $len), $side->pt($side->w * 0.1, 0, $len * 0.8)], $p['shade'], 100, 3, 0.5);
    }
}

/** Window on a face: frame, glass wash with a darker lower half, sill, and optional arch / iron grille / shutters. */
function sk_window(Sketch $s, SketchFace $f, float $a, float $b, float $w, float $h, string $style = 'plain', bool $grille = false): void
{
    $p = sk_pal();
    $top = $b + $h;
    if ($style === 'arch' || $style === 'pointed') {
        $pts = [$f->pt($a, $b), $f->pt($a, $top - $w / 2)];
        for ($i = 1; $i < 10; $i++) {
            $t = M_PI - M_PI * $i / 10;
            $rise = $style === 'pointed' ? $w * 0.62 * (1 - abs(cos($t)) ** 1.6 * 0.15) : $w / 2;
            $pts[] = $f->pt($a + $w / 2 + cos($t) * $w / 2, $top - $w / 2 + sin($t) * $rise);
        }
        $pts[] = $f->pt($a + $w, $top - $w / 2);
        $pts[] = $f->pt($a + $w, $b);
        $s->curve($pts, 1.5, 10, 0.4);
        $s->line($f->pt($a, $b), $f->pt($a + $w, $b), 1.4);
        $glass = $pts;
    } else {
        $glass = $f->quad($a, $b, $a + $w, $top);
        $s->poly($glass, true, 1.5);
        // a mullion or transom, as most aluminium windows here have
        if ($w > 0.9) {
            $s->line($f->pt($a + $w / 2, $b), $f->pt($a + $w / 2, $top), 1.0, 1);
        }
    }
    $s->wash($glass, $p['glass'], 82, 1.5);
    $s->wash([$f->pt($a, $b), $f->pt($a + $w, $b), $f->pt($a + $w, $b + $h * 0.45), $f->pt($a, $b + $h * 0.7)], [70, 90, 116], 98, 1);
    // sill sticks out a little
    $s->line($f->pt($a - 0.08, $b - 0.04, 0.08), $f->pt($a + $w + 0.08, $b - 0.04, 0.08), 1.4);
    if ($grille) {
        for ($x = $a + 0.14; $x < $a + $w - 0.06; $x += 0.16) {
            $s->line($f->pt($x, $b), $f->pt($x, $top - ($style === 'plain' ? 0 : $w * 0.4)), 0.7, 1);
        }
    }
}

/** Iron railing along a face edge (balcony, roof, wall top). */
function sk_railing(Sketch $s, SketchFace $f, float $a0, float $a1, float $b, float $h, float $c = 0.0, float $step = 0.16): void
{
    $s->line($f->pt($a0, $b + $h, $c), $f->pt($a1, $b + $h, $c), 1.4);
    $s->line($f->pt($a0, $b + $h * 0.85, $c), $f->pt($a1, $b + $h * 0.85, $c), 0.8, 1);
    for ($a = $a0; $a <= $a1 + 0.01; $a += $step) {
        $s->line($f->pt($a, $b, $c), $f->pt($a, $b + $h, $c), 0.6, 1);
    }
}

/** Outdoor unit of a split air conditioner: grey box with a round fan grille — on nearly every wall here. */
function sk_ac(Sketch $s, SketchFace $f, float $a, float $b): void
{
    $q = $f->quad($a, $b, $a + 0.8, $b + 0.55, 0.3);
    $s->knockout($q);
    $s->poly($q, true, 1.2);
    $s->wash($q, [214, 214, 210], 90, 1);
    $c1 = $f->pt($a + 0.32, $b + 0.27, 0.3);
    $c2 = $f->pt($a + 0.56, $b + 0.27, 0.3);
    $r = abs($c2[0] - $c1[0]);
    $s->ellipse($c1[0], $c1[1], $r * 0.9, $r * 0.9, 0.9);
    $s->line($f->pt($a + 0.1, $b, 0.3), $f->pt($a + 0.1, $b - 0.12, 0.3), 0.9, 1);
}

/* ───────── Things drawn flat on screen at a given scale (px per metre) ───────── */

/** Plastic water tank on a steel stand — the rooftop of almost every house in Iraq and Kurdistan. */
function sk_tank(Sketch $s, float $x, float $y, float $m, array $rgb): void
{
    $r = 0.55 * $m;
    $h = 1.15 * $m;
    $leg = 0.6 * $m;
    $s->line([$x - $r * 0.8, $y], [$x - $r * 0.7, $y - $leg], 1.0);
    $s->line([$x + $r * 0.8, $y], [$x + $r * 0.7, $y - $leg], 1.0);
    $s->line([$x - $r * 0.8, $y - $leg * 0.4], [$x + $r * 0.8, $y - $leg * 0.4], 0.8, 1);
    $top = $y - $leg - $h;
    $body = [[$x - $r, $y - $leg], [$x - $r, $top], [$x + $r, $top], [$x + $r, $y - $leg]];
    $s->knockout($body);
    $s->line([$x - $r, $y - $leg], [$x - $r, $top], 1.3);
    $s->line([$x + $r, $y - $leg], [$x + $r, $top], 1.3);
    $s->ellipse($x, $top, $r, $r * 0.28, 1.2, 0.01, 6.5);
    $s->ellipse($x, $y - $leg, $r, $r * 0.28, 1.2, 0.2, 2.9);
    for ($k = 1; $k < 4; $k++) {
        $yy = $top + $h * $k / 4;
        $s->ellipse($x, $yy, $r, $r * 0.24, 0.6, 0.25, 2.6);
    }
    $s->wash($body, $rgb, 84, 1.5);
    $s->wash([[$x + $r * 0.2, $y - $leg], [$x + $r * 0.2, $top], [$x + $r, $top], [$x + $r, $y - $leg]], sk_pal()['shade'], 104, 1);
}

/** Solar water heater: tilted collector with the horizontal tank along its top. */
function sk_solar(Sketch $s, float $x, float $y, float $m): void
{
    $w = 1.9 * $m;
    $panel = [[$x, $y], [$x + $w, $y], [$x + $w * 0.92, $y - 0.9 * $m], [$x + $w * 0.08, $y - 0.9 * $m]];
    $s->knockout($panel);
    $s->poly($panel, true, 1.2);
    for ($i = 1; $i < 8; $i++) {
        $t = $i / 8;
        $s->line([$x + $w * $t, $y], [$x + $w * 0.08 + $w * 0.84 * $t, $y - 0.9 * $m], 0.5, 1);
    }
    $s->wash($panel, [80, 96, 126], 88, 1);
    $ty = $y - 1.05 * $m;
    $tank = [[$x + $w * 0.04, $ty - 0.2 * $m], [$x + $w * 0.96, $ty - 0.2 * $m], [$x + $w * 0.96, $ty + 0.18 * $m], [$x + $w * 0.04, $ty + 0.18 * $m]];
    $s->knockout($tank);
    $s->poly($tank, true, 1.2);
    $s->wash($tank, [200, 200, 204], 92, 1);
    $s->line([$x + $w, $y], [$x + $w * 0.96, $ty + 0.18 * $m], 0.9, 1);
}

function sk_dish(Sketch $s, float $x, float $y, float $m): void
{
    $s->line([$x, $y], [$x, $y - 0.7 * $m], 1.1);
    $s->ellipse($x - 0.15 * $m, $y - 1.0 * $m, 0.28 * $m, 0.4 * $m, 1.3);
    $s->line([$x - 0.12 * $m, $y - 1.0 * $m], [$x + 0.35 * $m, $y - 0.85 * $m], 0.8, 1);
}

function sk_palm(Sketch $s, float $x, float $y, float $m, float $h): void
{
    $lean = Sketch::r(-0.08, 0.08) * $h * $m;
    $top = [$x + $lean, $y - $h * $m];
    $trunk = [];
    for ($i = 0; $i <= 8; $i++) {
        $t = $i / 8;
        $trunk[] = [$x + $lean * $t * $t, $y - $h * $m * $t];
    }
    $s->curve(array_map(fn($p) => [$p[0] - 0.12 * $m, $p[1]], $trunk), 1.3, 12, 0.3);
    $s->curve(array_map(fn($p) => [$p[0] + 0.12 * $m, $p[1]], $trunk), 1.3, 12, 0.3);
    for ($i = 1; $i < 14; $i++) {             // the cut frond bases ring the trunk
        $p = $trunk[(int) ($i * 8 / 14)];
        $s->line([$p[0] - 0.12 * $m, $p[1]], [$p[0] + 0.12 * $m, $p[1] - 0.06 * $m], 0.6, 1);
    }
    $s->wash([[$trunk[0][0] - 0.12 * $m, $y], [$top[0] - 0.12 * $m, $top[1]], [$top[0] + 0.12 * $m, $top[1]], [$trunk[0][0] + 0.12 * $m, $y]], [150, 120, 90], 90, 1);
    $fronds = mt_rand(9, 12);
    for ($k = 0; $k < $fronds; $k++) {
        $ang = -M_PI / 2 + ($k / ($fronds - 1) - 0.5) * M_PI * 1.5 + Sketch::r(-0.15, 0.15);
        $len = Sketch::r(1.6, 2.4) * $m;
        $pts = [];
        $leaf = [];
        for ($i = 0; $i <= 6; $i++) {
            $t = $i / 6;
            $droop = $t * $t * $len * 0.55;
            $px = $top[0] + cos($ang) * $len * $t;
            $py = $top[1] + sin($ang) * $len * $t + $droop;
            $pts[] = [$px, $py];
            if ($i > 0) {
                $leaf[] = [$px, $py];
                $s->line([$px, $py], [$px + cos($ang + 1.3) * 0.35 * $m * (1 - $t * 0.5), $py + 0.3 * $m], 0.5, 1);
                $s->line([$px, $py], [$px + cos($ang - 1.3) * 0.35 * $m * (1 - $t * 0.5), $py + 0.32 * $m], 0.5, 1);
            }
        }
        $s->curve($pts, 1.1, 14, 0.2);
        $s->wash(array_merge($leaf, array_map(fn($p) => [$p[0], $p[1] + 0.3 * $m], array_reverse($leaf))), sk_pal()['dkgreen'], 94, 1);
    }
}

function sk_cypress(Sketch $s, float $x, float $y, float $m, float $h): void
{
    $w = $h * 0.14 * $m;
    $pts = [];
    $n = 16;
    for ($i = 0; $i <= $n; $i++) {
        $t = $i / $n;
        $a = M_PI * $t;
        $pts[] = [$x - sin($a) ** 0.8 * $w * (1 + Sketch::r(-0.12, 0.12)), $y - 0.4 * $m - $h * $m * (1 - $t) * 0.95 - (1 - $t) * 0];
    }
    $outline = [];
    for ($i = 0; $i <= $n; $i++) {
        $t = $i / $n;
        $outline[] = [$x - sin(M_PI * $t) ** 0.7 * $w * (1 + Sketch::r(-0.15, 0.15)), $y - 0.3 * $m - $h * $m * $t];
    }
    $right = [];
    for ($i = $n; $i >= 0; $i--) {
        $t = $i / $n;
        $right[] = [$x + sin(M_PI * $t) ** 0.7 * $w * (1 + Sketch::r(-0.15, 0.15)), $y - 0.3 * $m - $h * $m * $t];
    }
    $shape = array_merge($outline, $right);
    $s->knockout($shape);
    $s->curve($outline, 1.2, 14, 0.6);
    $s->curve($right, 1.2, 14, 0.6);
    $s->hatch($shape, -75, 6, 0.6, 0.5, 50);
    $s->wash($shape, sk_pal()['dkgreen'], 82, 3);
    $s->line([$x, $y], [$x, $y - 0.35 * $m], 1.4);
}

/** Rounded tree (olive, mulberry): scalloped crown, short trunk. */
function sk_tree(Sketch $s, float $x, float $y, float $m, float $h, ?array $rgb = null): void
{
    $rx = $h * 0.38 * $m;
    $ry = $h * 0.32 * $m;
    $cy = $y - $h * $m + $ry;
    $s->line([$x - 0.1 * $m, $y], [$x - 0.05 * $m, $cy + $ry * 0.4], 1.4);
    $s->line([$x + 0.1 * $m, $y], [$x + 0.12 * $m, $cy + $ry * 0.4], 1.4);
    $crown = [];
    $n = 22;
    for ($i = 0; $i <= $n; $i++) {
        $a = M_PI * 2 * $i / $n;
        $k = 1 + Sketch::r(-0.1, 0.12) + 0.06 * sin($a * 5);
        $crown[] = [$x + cos($a) * $rx * $k, $cy + sin($a) * $ry * $k];
    }
    $s->knockout($crown);
    // scallops: small bumps around the crown, the way a quick hand draws leaves
    for ($i = 0; $i < $n; $i += 2) {
        $a = $crown[$i];
        $b = $crown[min($n, $i + 2)];
        $mx = ($a[0] + $b[0]) / 2;
        $my = ($a[1] + $b[1]) / 2;
        $ox = ($mx - $x) * 0.12;
        $oy = ($my - $cy) * 0.12;
        $s->curve([$a, [$mx + $ox, $my + $oy], $b], 1.0, 16, 0.4);
    }
    $s->hatch(array_slice($crown, 0, (int) ($n * 0.55)), 60, 5, 0.6, 0.6, 56);
    $s->wash($crown, $rgb ?? sk_pal()['olive'], 80, 4);
    $s->wash(array_slice($crown, 0, (int) ($n * 0.5)), sk_pal()['dkgreen'], 100, 2);
}

/** Wooden/concrete power pole with crossarm; returns the top attachment points for wires. */
function sk_pole(Sketch $s, float $x, float $y, float $m): array
{
    $h = 8.5 * $m;
    $s->line([$x - 0.08 * $m, $y], [$x - 0.05 * $m, $y - $h], 1.4);
    $s->line([$x + 0.08 * $m, $y], [$x + 0.05 * $m, $y - $h], 1.0, 1);
    $s->line([$x - 0.9 * $m, $y - $h + 0.4 * $m], [$x + 0.9 * $m, $y - $h + 0.4 * $m], 1.3);
    return [[$x - 0.8 * $m, $y - $h + 0.4 * $m], [$x + 0.8 * $m, $y - $h + 0.4 * $m], [$x, $y - $h + 1.2 * $m]];
}

/** A sagging wire between two points. */
function sk_wire(Sketch $s, array $a, array $b, float $sag): void
{
    $pts = [];
    for ($i = 0; $i <= 8; $i++) {
        $t = $i / 8;
        $pts[] = [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t + sin(M_PI * $t) * $sag];
    }
    $s->curve($pts, 0.6, 30, 0.2);
}

function sk_tufts(Sketch $s, float $y0, float $y1, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        $x = Sketch::r(0, $s->W);
        $y = Sketch::r($y0, $y1);
        $k = ($y - $y0) / max(1, $y1 - $y0) * 1.5 + 0.5;
        for ($j = 0; $j < 3; $j++) {
            $s->line([$x + $j * 3 * $k, $y], [$x + ($j - 1) * 6 * $k + Sketch::r(-2, 2), $y - Sketch::r(6, 14) * $k * $s->S], 0.6, 1);
        }
    }
}

function sk_camera(Sketch $s, float $horizonK, float $fK = 0.95, float $eye = 1.6): SketchCam
{
    return new SketchCam($s->W * $fK, $s->W / 2 + Sketch::r(-0.04, 0.04) * $s->W, $s->H * $horizonK, $eye);
}

/** Street and pavement in front of a building: kerb line and pavement joints in perspective. */
function sk_street(Sketch $s, SketchCam $c, float $zKerb, float $zWall): void
{
    $p = sk_pal();
    $k1 = $c->p(-60, 0, $zKerb);
    $k2 = $c->p(60, 0, $zKerb);
    $s->line([-20, $k1[1]], [$s->W + 20, $k2[1]], 1.4);
    $kb = $c->p(-60, 0.18, $zKerb);
    $s->line([-20, $kb[1]], [$s->W + 20, $kb[1]], 1.1, 1);
    for ($x = -30; $x <= 30; $x += 1.6) {
        $s->line($c->p($x, 0.18, $zKerb), $c->p($x, 0.18, $zWall), 0.6, 1);
    }
    $s->wash([[-20, $kb[1]], [$s->W + 20, $kb[1]], [$s->W + 20, $s->H + 20], [-20, $s->H + 20]], $p['road'], 94, 4, 0.75);
    $lane = $zKerb - Sketch::r(3.2, 4);
    for ($x = -30 + Sketch::r(0, 3); $x < 30; $x += 6) {
        $s->line($c->p($x, 0, $lane), $c->p($x + 3, 0, $lane), 1.0, 1);
    }
    $s->wash([[-20, $c->p(0, 0, $zWall)[1]], [$s->W + 20, $c->p(0, 0, $zWall)[1]], [$s->W + 20, $kb[1]], [-20, $kb[1]]], $p['ground'], 100, 3);
}

/* ───────── Scenes ───────── */

/** Villa: two floors, stone cladding, columned porch, balcony, roof parapet with tanks; front wall with iron gate. */
function sk_scene_villa(int $seed, bool $alt = false): string
{
    $s = new Sketch(SK_W, SK_H, $seed);
    $p = sk_pal();
    $c = sk_camera($s, Sketch::r(0.66, 0.7), 0.9);
    $s->sky($c->horizon * 0.85);
    $s->ridge($c->horizon - 6 * $s->S, Sketch::r(60, 120) * $s->S, $p['mount']);
    $right = $alt ? mt_rand(0, 1) === 1 : (bool) ($seed % 2);
    $w = Sketch::r(11, 13.5);
    $z = Sketch::r(19, 22);
    // the end wall shows on the side the facade turns towards us
    $phi = Sketch::r(0.22, 0.38) * ($right ? -1 : 1);
    $A = [-$w * ($right ? 0.62 : 0.38) * cos($phi) + Sketch::r(-0.6, 0.6), 0.0, $z];
    $h = 7.6;
    [$front, $side] = sk_mass($c, $A, $phi, $w, Sketch::r(9, 12), $h, $right ? 'right' : 'left');
    $stone = Sketch::pick($p['stone']);
    sk_draw_mass($s, $front, $side, $stone);
    // parapet coping and floor band
    $s->line($front->pt(-0.1, $h - 0.2, 0.08), $front->pt($w + 0.1, $h - 0.2, 0.08), 1.4);
    $s->line($front->pt(-0.1, 3.5, 0.06), $front->pt($w + 0.1, 3.5, 0.06), 1.4);
    $s->line($front->pt(0, 3.3, 0.06), $front->pt($w, 3.3, 0.06), 0.9, 1);
    $s->line($side->pt(0, $h - 0.2), $side->pt($side->w, $h - 0.2), 1.0, 1);
    $s->line($side->pt(0, 3.5), $side->pt($side->w, 3.5), 1.0, 1);
    // stone coursing on part of the front (a sketcher suggests texture, doesn't fill it)
    $a0 = Sketch::r(0, $w * 0.3);
    for ($b = 0.45; $b < 3.2; $b += 0.42) {
        $a1 = $a0 + Sketch::r(1.2, 3.0);
        $s->line($front->pt($a0, $b), $front->pt(min($w, $a1), $b), 0.6, 1);
        for ($a = $a0 + Sketch::r(0.2, 0.5); $a < min($w, $a1); $a += Sketch::r(0.5, 0.9)) {
            $s->line($front->pt($a, $b), $front->pt($a, $b + 0.42), 0.5, 1);
        }
    }
    // windows: ground floor arched with grilles, upper floor plain with a central balcony
    $bays = $w > 12.5 ? 5 : 4;
    $style = Sketch::pick(['arch', 'pointed', 'plain']);
    $door = (int) floor($bays / 2);
    for ($i = 0; $i < $bays; $i++) {
        $a = $w * ($i + 0.5) / $bays;
        if ($i === $door) {
            // entrance: double door under a porch with two round columns
            $dq = $front->quad($a - 0.8, 0.3, $a + 0.8, 2.7);
            $s->poly($dq, true, 1.6);
            $s->line($front->pt($a, 0.3), $front->pt($a, 2.7), 1.0, 1);
            $s->wash($dq, $p['wood'], 80, 1);
            $s->hatch($dq, 90, 5, 0.5, 0.7, 60);
            foreach ([-1.5, 1.5] as $o) {
                $s->line($front->pt($a + $o - 0.13, 0.3, 1.8), $front->pt($a + $o - 0.13, 3.15, 1.8), 1.4);
                $s->line($front->pt($a + $o + 0.13, 0.3, 1.8), $front->pt($a + $o + 0.13, 3.15, 1.8), 1.4);
                $s->line($front->pt($a + $o - 0.2, 3.15, 1.8), $front->pt($a + $o + 0.2, 3.15, 1.8), 1.2);
            }
            $roof = [$front->pt($a - 1.9, 3.15, 1.9), $front->pt($a + 1.9, 3.15, 1.9), $front->pt($a + 1.9, 3.45, 1.9), $front->pt($a - 1.9, 3.45, 1.9)];
            $s->knockout($roof);
            $s->poly($roof, true, 1.6);
            $s->wash($roof, [236, 230, 214], 80, 1);
            $s->line($front->pt($a - 1.9, 3.15, 1.9), $front->pt($a - 1.9, 3.15, 0), 1.0, 1);
            // steps
            for ($k = 0; $k < 3; $k++) {
                $s->line($front->pt($a - 1.6, 0.3 - $k * 0.1, 0.4 + $k * 0.35), $front->pt($a + 1.6, 0.3 - $k * 0.1, 0.4 + $k * 0.35), 1.1, 1);
            }
            // balcony above the porch
            sk_window($s, $front, $a - 0.7, 3.8, 1.4, 2.4, 'plain');
            sk_railing($s, $front, $a - 1.9, $a + 1.9, 3.45, 1.0, 1.9, 0.18);
            continue;
        }
        sk_window($s, $front, $a - 0.6, 0.9, 1.2, 1.9, $style, true);
        sk_window($s, $front, $a - 0.6, 4.3, 1.2, 1.7, 'plain');
    }
    // side wall: two small windows, a split-AC unit and a drainpipe
    sk_window($s, $side, $side->w * 0.3, 1.1, 0.9, 1.4, 'plain', true);
    sk_window($s, $side, $side->w * 0.62, 4.4, 0.9, 1.4, 'plain');
    sk_ac($s, $side, $side->w * 0.62, 3.75);
    sk_ac($s, $front, $w * Sketch::r(0.05, 0.12), 6.1);
    $s->line($side->pt($side->w * 0.05, 0, 0.1), $side->pt($side->w * 0.05, $h - 0.2, 0.1), 1.0);
    // roof clutter: tanks, a solar heater, a dish
    $roofY = $front->pt($w * 0.5, $h)[1];
    $m = $c->scaleAt($A[0] + $w / 2, $z + 3);
    $tx = $front->pt($w * Sketch::r(0.55, 0.8), $h, -3)[0];
    $tankColors = [[226, 226, 224], [70, 72, 78], [220, 196, 120]];
    for ($k = 0; $k < mt_rand(1, 3); $k++) {
        sk_tank($s, $tx + $k * 1.25 * $m, $roofY - 0.05 * $m, $m, Sketch::pick($tankColors));
    }
    if (mt_rand(0, 2) > 0) {
        sk_solar($s, $front->pt($w * Sketch::r(0.12, 0.3), $h, -2)[0], $roofY - 0.1 * $m, $m);
    }
    sk_dish($s, $front->pt($w * 0.92, $h, -1)[0], $roofY, $m);
    // front wall with an iron railing on top and a steel gate; stops before the camera
    $wallZ = $z - Sketch::r(4.5, 6);
    $wall = new SketchFace($c, [$A[0] - 2, 0.0, $wallZ - 0.5], [cos($phi), 0.0, sin($phi)], $w + 4, 1.1);
    $gateA = ($w + 4) * Sketch::r(0.62, 0.78);
    $wq = $wall->quad(0, 0, $wall->w, 1.1);
    $s->knockout($wq);
    $s->poly([$wall->pt(0, 0), $wall->pt(0, 1.1), $wall->pt($gateA - 0.3, 1.1), $wall->pt($gateA - 0.3, 0)], false, 1.6);
    $s->poly([$wall->pt($gateA + 3.8, 0), $wall->pt($gateA + 3.8, 1.1), $wall->pt($wall->w, 1.1), $wall->pt($wall->w, 0)], false, 1.6);
    $s->wash($wq, Sketch::pick($p['stone']), 84, 2);
    $s->hatch([$wall->pt(0, 0), $wall->pt($wall->w, 0), $wall->pt($wall->w, 0.5), $wall->pt(0, 0.4)], 15, 4, 0.5, 0.5, 64);
    sk_railing($s, $wall, 0.1, $gateA - 0.4, 1.1, 0.9, 0, 0.14);
    sk_railing($s, $wall, $gateA + 3.9, $wall->w, 1.1, 0.9, 0, 0.14);
    foreach ([$gateA - 0.3, $gateA + 3.5] as $pa) {           // gate pillars with caps
        $pq = $wall->quad($pa, 0, $pa + 0.3, 2.4);
        $s->knockout($pq);
        $s->poly($pq, true, 1.6);
        $s->wash($pq, $stone, 80, 1);
        $s->poly($wall->quad($pa - 0.06, 2.4, $pa + 0.36, 2.55), true, 1.3);
    }
    $gq = $wall->quad($gateA, 0.05, $gateA + 3.5, 2.1);
    $s->poly($gq, true, 1.5);
    $s->line($wall->pt($gateA + 1.75, 0.05), $wall->pt($gateA + 1.75, 2.1), 1.2);
    for ($a = $gateA + 0.15; $a < $gateA + 3.45; $a += 0.13) {
        $s->line($wall->pt($a, 0.05), $wall->pt($a, 2.1 - abs(sin(($a - $gateA) / 3.5 * M_PI)) * -0.15), 0.6, 1);
    }
    $s->line($wall->pt($gateA, 1.2), $wall->pt($gateA + 3.5, 1.2), 1.0, 1);
    $s->wash($gq, $p['metal'], 96, 1);
    // garden: a palm or a cypress behind the wall, an olive in front
    $gm = $c->scaleAt($A[0] + 1, $wallZ + 2);
    $g = $wall->pt(Sketch::r(0.8, 2.5), 0, -2);
    if (mt_rand(0, 1)) {
        sk_palm($s, $g[0], $g[1], $gm, Sketch::r(6.5, 8));
    } else {
        sk_cypress($s, $g[0], $g[1], $gm, Sketch::r(5.5, 7));
    }
    sk_street($s, $c, $wallZ - Sketch::r(2.5, 3.5), $wallZ - 0.5);
    $t = $wall->pt($wall->w * Sketch::r(0.88, 1.0), 0, 1.2);
    sk_tree($s, $t[0], $t[1], $c->scaleAt($t[0] / $c->f, $wallZ - 1), Sketch::r(4, 5.2));
    sk_tufts($s, $c->p(0, 0, $wallZ - 0.5)[1], $c->p(0, 0, $wallZ - 0.6)[1] + 6, 12);
    return $s->save();
}

/** House: concrete/plaster, one or two floors, flat roof with railing and tanks, yard wall with a sheet-steel door. */
function sk_scene_house(int $seed, bool $alt = false): string
{
    $s = new Sketch(SK_W, SK_H, $seed);
    $p = sk_pal();
    $c = sk_camera($s, Sketch::r(0.64, 0.7), 0.85);
    $s->sky($c->horizon * 0.8);
    $s->ridge($c->horizon - 4 * $s->S, Sketch::r(40, 100) * $s->S, $p['mount']);
    $right = (bool) (($seed + ($alt ? 1 : 0)) % 2);
    $z = Sketch::r(17, 19);
    $w = Sketch::r(9, 11);
    $floors = mt_rand(1, 2);
    $h = $floors * 3.3 + 0.9;
    $phi = Sketch::r(0.2, 0.34) * ($right ? -1 : 1);
    $A = [-$w * ($right ? 0.6 : 0.4) * cos($phi) + Sketch::r(-0.5, 0.5), 0.0, $z];
    // the neighbour's house, further back
    $u0 = [cos($phi), 0.0, sin($phi)];
    $nA = $right ? [$A[0] + $u0[0] * ($w + 2), 0.0, $A[2] + $u0[2] * ($w + 2) + 3] : [$A[0] - $u0[0] * 11, 0.0, $A[2] - $u0[2] * 11 + 3];
    $nA[2] += 5;
    $nf = new SketchFace($c, $nA, $u0, 9, 6.8);          // only its front shows past ours
    $nq = $nf->quad(0, 0, 9, 6.8);
    $s->poly($nq, true, 1.5);
    $s->wash($nq, Sketch::pick($p['plaster']), 88, 2);
    $s->wash($nq, $p['shade'], 110, 2);
    sk_window($s, $nf, 2, 4.0, 1.2, 1.1);
    sk_window($s, $nf, 5.5, 4.0, 1.2, 1.1);
    [$front, $side] = sk_mass($c, $A, $phi, $w, Sketch::r(9, 11), $h, $right ? 'right' : 'left');
    $col = Sketch::pick($p['plaster']);
    sk_draw_mass($s, $front, $side, $col);
    $s->line($front->pt(-0.05, $h - 0.9, 0.05), $front->pt($w + 0.05, $h - 0.9, 0.05), 1.3);
    sk_railing($s, $front, 0, $w, $h - 0.9, 0.9, 0, 0.2);
    if ($floors === 2) {
        $s->line($front->pt(0, 3.3, 0.05), $front->pt($w, 3.3, 0.05), 1.1, 1);
    }
    // a few bricks showing where the plaster has come off — common on older houses
    $bp = [$front->pt($w * 0.7, 0.4), $front->pt($w * 0.9, 0.35), $front->pt($w * 0.88, 1.0), $front->pt($w * 0.72, 0.9)];
    $s->hatch($bp, 0, 3, 0.5, 0.8, 56);
    for ($f = 0; $f < $floors; $f++) {
        $b = 0.9 + $f * 3.3;
        $n = 3;
        for ($i = 0; $i < $n; $i++) {
            $a = $w * ($i + 0.5) / $n;
            if ($f === 0 && $i === 1) {
                $dq = $front->quad($a - 0.5, 0.15, $a + 0.5, 2.3);
                $s->poly($dq, true, 1.6);
                $s->wash($dq, Sketch::pick([[120, 86, 64], [84, 104, 116], [146, 70, 54]]), 82, 1);
                $s->poly($front->quad($a - 0.7, 2.35, $a + 0.7, 2.55, 0.6), true, 1.3);
                continue;
            }
            sk_window($s, $front, $a - 0.65, $b, 1.3, 1.5, 'plain', $f === 0);
        }
    }
    sk_window($s, $side, $side->w * 0.4, 1.0, 1.0, 1.4, 'plain', true);
    sk_ac($s, $side, $side->w * 0.6, 2.6);
    if ($floors === 2) {
        sk_ac($s, $front, $w * 0.08, 5.0);
    }
    $roofY = $front->pt($w * 0.5, $h - 0.9)[1];
    $m = $c->scaleAt($A[0] + $w / 2, $z + 3);
    $tx = $front->pt($w * Sketch::r(0.3, 0.7), $h, -3)[0];
    for ($k = 0; $k < mt_rand(2, 3); $k++) {
        sk_tank($s, $tx + $k * 1.25 * $m, $roofY, $m, Sketch::pick([[226, 226, 224], [70, 72, 78], [90, 120, 160]]));
    }
    sk_dish($s, $front->pt($w * 0.1, $h, -1)[0], $roofY, $m);
    // neighbourhood generator cable coming in to the roof
    $pole = $c->p($A[0] + ($right ? $w + 1 : -1.5), 0, $z - 3);
    $tops = sk_pole($s, $pole[0], $pole[1], $c->scaleAt($pole[0], $z - 3));
    sk_wire($s, $tops[0], $front->pt($right ? $w : 0, $h - 1.2), 12 * $s->S);
    sk_wire($s, $tops[1], [$right ? $s->W + 20 : -20, $tops[1][1] + 30 * $s->S], 18 * $s->S);
    sk_wire($s, $tops[2], [$right ? -20 : $s->W + 20, $tops[2][1] - 10 * $s->S], 26 * $s->S);
    // yard wall with a sheet-steel door, a vine or tree over it
    $wallZ = $z - Sketch::r(3.0, 3.8);
    $wall = new SketchFace($c, [$A[0] - 1.5, 0.0, $wallZ], [cos($phi), 0.0, sin($phi)], $w + 3, 1.6);
    $wq = $wall->quad(0, 0, $wall->w, 1.6);
    $s->knockout($wq);
    $s->poly($wq, true, 1.6);
    $s->wash($wq, $col, 84, 2);
    $da = $wall->w * Sketch::r(0.25, 0.4);
    $dq = $wall->quad($da, 0, $da + 1.1, 1.95);
    $s->poly($dq, true, 1.5);
    $s->wash($dq, Sketch::pick([[60, 96, 120], [120, 60, 50], [70, 100, 80]]), 80, 1);
    for ($b = 0.4; $b < 1.9; $b += 0.45) {
        $s->line($wall->pt($da + 0.1, $b), $wall->pt($da + 1.0, $b), 0.6, 1);
    }
    $s->line($wall->pt(0, 1.6, 0.05), $wall->pt($wall->w, 1.6, 0.05), 1.3);
    $t = $wall->pt($wall->w * Sketch::r(0.65, 0.85), 0, -1.5);
    sk_tree($s, $t[0], $t[1], $c->scaleAt($A[0] + $w * 0.7, $wallZ + 1.5), Sketch::r(4.5, 6), Sketch::pick([$p['green'], $p['olive']]));
    $s->knockout([$wall->pt(0, 0), $wall->pt($wall->w, 0), $wall->pt($wall->w, 1.1), $wall->pt(0, 1.1)]);
    $s->poly([$wall->pt(0, 1.1), $wall->pt(0, 0), $wall->pt($wall->w, 0), $wall->pt($wall->w, 1.1)], false, 1.5);
    $s->wash([$wall->pt(0, 0), $wall->pt($wall->w, 0), $wall->pt($wall->w, 1.1), $wall->pt(0, 1.1)], $col, 84, 2);
    $dq = $wall->quad($da, 0, $da + 1.1, 1.1);
    $s->poly($dq, false, 1.5);
    $s->wash($dq, [80, 100, 116], 84, 1);
    sk_street($s, $c, $wallZ - Sketch::r(2, 3), $wallZ);
    sk_tufts($s, $c->p(0, 0, $wallZ)[1], $c->p(0, 0, $wallZ)[1] + 8, 10);
    return $s->save();
}

/** Gated compound of residential towers: balconies in rows, a crown on top, entrance gate and young trees. */
function sk_scene_towers(int $seed, bool $alt = false): string
{
    $s = new Sketch(SK_W, SK_H, $seed);
    $p = sk_pal();
    $c = sk_camera($s, Sketch::r(0.76, 0.82), 0.62);
    $s->sky($c->horizon * 0.9);
    $s->ridge($c->horizon - 2 * $s->S, Sketch::r(30, 60) * $s->S, $p['mount'], false);
    $count = mt_rand(2, 3);
    $phi = Sketch::r(-0.45, -0.2) * ($alt ? -1 : 1);
    $col = Sketch::pick([[232, 220, 196], [222, 206, 178], [236, 230, 218], [214, 200, 186]]);
    $accent = Sketch::pick([[176, 120, 90], [120, 140, 150], [190, 160, 110]]);
    // far to near so nearer towers cover farther ones
    $towers = [];
    for ($i = 0; $i < $count; $i++) {
        $towers[] = [($i - ($count - 1) / 2) * 26 - 10 + Sketch::r(-4, 4), Sketch::r(40, 58) + ($i % 2) * 14, mt_rand(12, 20)];
    }
    usort($towers, fn($a, $b) => $b[1] <=> $a[1]);
    foreach ($towers as [$x, $z, $floors]) {
        $fh = 3.1;
        $h = $floors * $fh + 2;
        $w = Sketch::r(18, 24);
        $side = $x + $w / 2 < 0 ? 'right' : 'left';
        [$f, $sd] = sk_mass($c, [$x, 0.0, $z], $phi, $w, Sketch::r(14, 18), $h, $side);
        sk_draw_mass($s, $f, $sd, $col);
        // vertical accent strips (stone/HPL) and balcony rows
        foreach ([0.0, $w - 2.4] as $a) {
            $q = $f->quad($a, 0, $a + 2.4, $h);
            $s->wash($q, $accent, 92, 1);
            $s->poly($q, true, 1.1, 1);
        }
        for ($k = 1; $k < $floors; $k++) {
            $b = $k * $fh;
            $s->line($f->pt(2.4, $b, 1.4), $f->pt($w - 2.4, $b, 1.4), 0.9, 1);
            $s->line($f->pt(2.4, $b + 1.05, 1.4), $f->pt($w - 2.4, $b + 1.05, 1.4), 0.6, 1);
            for ($a = 3.2; $a < $w - 3; $a += Sketch::r(2.8, 3.4)) {
                $g = $f->quad($a, $b + 0.2, $a + 1.6, $b + 2.4);
                $s->wash($g, $p['glass'], 90, 0.5);
                if ($k % 3 === 0) {
                    $s->poly($g, true, 0.7, 1);
                }
            }
            for ($a = 1; $a < $sd->w - 1; $a += 3.5) {
                $s->line($sd->pt($a, $b + 0.6), $sd->pt($a, $b + 2.2), 0.7, 1);
            }
        }
        $s->poly($f->quad(1.8, $h - 1.4, $w - 1.8, $h + 0.4, 0.1), true, 1.3);   // crown
        $s->hatch($f->quad(2.4, 0.15, $w - 2.4, $fh - 0.3), 90, 4, 0.6, 0.6, 52);  // lobby glazing
        if (mt_rand(0, 2) === 0) {          // a tower crane, because there always is one
            $base = $sd->pt($sd->w + 6, 0);
            $m = $c->scaleAt($x + $w, $z + 10);
            $top = [$base[0], $base[1] - ($h + 14) * $m];
            $s->line([$base[0] - 0.8 * $m, $base[1]], [$top[0] - 0.8 * $m, $top[1]], 1.1);
            $s->line([$base[0] + 0.8 * $m, $base[1]], [$top[0] + 0.8 * $m, $top[1]], 1.1);
            for ($y = $base[1]; $y > $top[1]; $y -= 1.6 * $m) {
                $s->line([$base[0] - 0.8 * $m, $y], [$base[0] + 0.8 * $m, $y - 1.6 * $m], 0.6, 1);
            }
            $s->line([$top[0] - 10 * $m, $top[1]], [$top[0] + 34 * $m, $top[1]], 1.3);
            $s->line([$top[0] - 10 * $m, $top[1] + 1.2 * $m], [$top[0] + 34 * $m, $top[1] + 0.4 * $m], 0.8, 1);
            $s->line([$top[0] + 24 * $m, $top[1]], [$top[0] + 24 * $m, $top[1] + 14 * $m], 0.6, 1);
        }
    }
    // compound fence and gatehouse, young trees along the road
    $zF = Sketch::r(16, 20);
    $fence = new SketchFace($c, [-40.0, 0.0, $zF], [1.0, 0.0, 0.0], 80, 2.2);
    $fq = $fence->quad(0, 0, 80, 0.7);
    $s->knockout($fq);
    $s->poly($fq, true, 1.4);
    $s->wash($fq, Sketch::pick($p['stone']), 86, 2);
    sk_railing($s, $fence, 0, 80, 0.7, 1.5, 0, 0.25);
    $gx = Sketch::r(-8, 4);
    $gh = [$c->p($gx, 0, $zF - 1), $c->p($gx + 6, 0, $zF - 1), $c->p($gx + 6, 3.4, $zF - 1), $c->p($gx, 3.4, $zF - 1)];
    $gh = array_map(fn($q) => [$q[0], $q[1]], $gh);
    $s->knockout($gh);
    $s->poly($gh, true, 1.6);
    $s->wash($gh, $accent, 84, 1);
    $win = array_map(fn($q) => [$q[0], $q[1]], [$c->p($gx + 1, 1.0, $zF - 1.05), $c->p($gx + 5, 1.0, $zF - 1.05), $c->p($gx + 5, 2.6, $zF - 1.05), $c->p($gx + 1, 2.6, $zF - 1.05)]);
    $s->poly($win, true, 1.1);
    $s->wash($win, $p['glass'], 84, 1);
    $bar = [$c->p($gx + 6, 1.0, $zF - 1), $c->p($gx + 13, 1.0, $zF - 1)];
    $s->line([$bar[0][0], $bar[0][1]], [$bar[1][0], $bar[1][1]], 1.6);
    $s->wash([[$bar[0][0], $bar[0][1] - 3], [$bar[1][0], $bar[1][1] - 3], [$bar[1][0], $bar[1][1] + 3], [$bar[0][0], $bar[0][1] + 3]], $p['red'], 70, 0);
    for ($x = -36; $x < 40; $x += Sketch::r(7, 10)) {
        if ($x > $gx - 2 && $x < $gx + 14) {
            continue;
        }
        $b = $c->p($x, 0, $zF - 2.5);
        sk_tree($s, $b[0], $b[1], $c->scaleAt($x, $zF - 2.5), Sketch::r(3.5, 4.8), Sketch::pick([$p['green'], $p['olive']]));
    }
    sk_street($s, $c, $zF - Sketch::r(5, 7), $zF - 0.2);
    return $s->save();
}

/** A shop row: roller shutters (one rolled up with goods outside), signboards, flats above, AC units, generator cables. */
function sk_scene_shops(int $seed, bool $tall = false): string
{
    $s = new Sketch(SK_W, SK_H, $seed);
    $p = sk_pal();
    $c = sk_camera($s, Sketch::r(0.62, 0.68), 0.62);
    $s->sky($c->horizon * 0.6);
    $phi = Sketch::r(0.42, 0.62) * (mt_rand(0, 1) ? 1 : -1);
    $floors = $tall ? mt_rand(4, 6) : mt_rand(2, 3);
    $h = 4.2 + ($floors - 1) * 3.2 + 0.6;
    $len = 34.0;
    $A = [-7.0, 0.0, 11.0];
    $u = [cos($phi), 0.0, sin($phi)];
    if ($phi < 0) {
        $A = [-$len * $u[0] * 0.75 + 7, 0.0, 11.0 - $len * $u[2]];
    }
    $f = new SketchFace($c, $A, $u, $len, $h);
    $q = $f->quad(0, 0, $len, $h);
    $s->knockout($q);
    $s->poly($q, true, 1.8);
    $col = $tall ? Sketch::pick([[214, 206, 194], [226, 214, 192]]) : Sketch::pick($p['plaster']);
    $s->wash($q, $col, 86, 2);
    if ($tall) {          // glass curtain wall over the shops (offices / commercial building)
        $g = $f->quad($len * 0.18, 4.6, $len * 0.82, $h - 0.6);
        $s->wash($g, $p['glass'], 80, 1);
        $s->poly($g, true, 1.4);
        for ($a = $len * 0.18; $a < $len * 0.82; $a += 1.5) {
            $s->line($f->pt($a, 4.6), $f->pt($a, $h - 0.6), 0.6, 1);
        }
        for ($b = 4.6; $b < $h - 0.6; $b += 3.2) {
            $s->line($f->pt($len * 0.18, $b), $f->pt($len * 0.82, $b), 0.9, 1);
        }
        $s->wash([$f->pt($len * 0.18, 4.6), $f->pt($len * 0.5, 4.6), $f->pt($len * 0.3, $h - 0.6), $f->pt($len * 0.18, $h - 0.6)], [210, 226, 234], 90, 1);
    }
    $shopW = Sketch::r(4.2, 5.2);
    $open = mt_rand(1, 3);
    for ($i = 0, $a = 0.6; $a + $shopW < $len; $i++, $a += $shopW + 0.5) {
        // signboard: coloured panel with lettering suggested by squiggles (never real words)
        $sb = $f->quad($a, 3.3, $a + $shopW, 4.1, 0.15);
        $s->knockout($sb);
        $s->poly($sb, true, 1.4);
        $s->wash($sb, Sketch::pick([[184, 70, 56], [40, 96, 140], [228, 168, 60], [60, 120, 90], [236, 236, 230]]), 76, 1);
        $lx = $a + $shopW * 0.15;
        $pts = [];
        while ($lx < $a + $shopW * 0.85) {
            $pts[] = $f->pt($lx, 3.7 + Sketch::r(-0.15, 0.15), 0.15);
            $lx += Sketch::r(0.12, 0.3);
        }
        $s->curve($pts, 1.2, 20, 0.3);
        $sh = $f->quad($a, 0.1, $a + $shopW, 3.2);
        if ($i === $open) {
            // rolled-up shutter: dark interior, shelves, goods set out on the pavement
            $s->wash($sh, [80, 70, 64], 72, 1);
            $s->poly($sh, true, 1.5);
            for ($b = 0.9; $b < 3.0; $b += 0.7) {
                $s->line($f->pt($a + 0.3, $b), $f->pt($a + $shopW - 0.3, $b), 0.8, 1);
            }
            for ($k = 0; $k < 4; $k++) {
                $bx = $a + 0.5 + $k * 1.0;
                $bq = $f->quad($bx, 0.1, $bx + 0.7, 0.6, 1.0);
                $s->knockout($bq);
                $s->poly($bq, true, 1.1);
                $s->wash($bq, Sketch::pick([[214, 160, 70], [130, 160, 80], [190, 90, 60]]), 76, 1);
            }
            $s->poly($f->quad($a, 3.1, $a + $shopW, 3.3, 0.1), true, 1.2);
        } else {
            $s->poly($sh, true, 1.5);
            for ($b = 0.3; $b < 3.1; $b += 0.17) {
                $s->line($f->pt($a + 0.05, $b), $f->pt($a + $shopW - 0.05, $b), 0.45, 1);
            }
            $s->wash($sh, [176, 178, 180], 88, 1);
            $s->poly($f->quad($a + $shopW / 2 - 0.2, 0.1, $a + $shopW / 2 + 0.2, 0.25), true, 0.9, 1);
        }
        if (!$tall || $i % 3 === 0) {
            for ($fl = 1; $fl < $floors; $fl++) {
                $b = 4.6 + ($fl - 1) * 3.2;
                if ($tall && $a > $len * 0.16 && $a < $len * 0.8) {
                    continue;
                }
                sk_window($s, $f, $a + 0.6, $b, 1.4, 1.6, 'plain');
                if (($i + $fl) % 2 === 0) {
                    sk_ac($s, $f, $a + 2.6, $b + 0.3);
                }
            }
        }
    }
    $s->line($f->pt(0, $h - 0.3, 0.1), $f->pt($len, $h - 0.3, 0.1), 1.3);
    // generator cables strung along the facade and across the street
    $poleW = $f->pt(Sketch::r(6, 12), 0, 4.5);
    $tops = sk_pole($s, $poleW[0], $poleW[1], $c->scaleAt(0, $A[2] + 4));
    for ($k = 0; $k < 4; $k++) {
        $tgt = $f->pt(Sketch::r(1, $len), 3.0 + Sketch::r(0, 0.8));
        sk_wire($s, $tops[$k % 3], $tgt, Sketch::r(6, 22) * $s->S);
    }
    sk_wire($s, $tops[0], [-20, $tops[0][1] + 60 * $s->S], 20 * $s->S);
    sk_wire($s, $tops[1], [$s->W + 20, $tops[1][1] - 40 * $s->S], 30 * $s->S);
    // pavement and road along the row
    $k0 = $f->pt(-20, 0, 4.0);
    $k1 = $f->pt($len + 30, 0, 4.0);
    $s->line($k0, $k1, 1.4);
    $s->wash([$f->pt(-20, 0, 0), $f->pt($len + 30, 0, 0), $k1, $k0], $p['ground'], 98, 3);
    $s->wash([$k0, $k1, [$k1[0], $s->H + 20], [$k0[0], $s->H + 20]], $p['road'], 94, 3, 0.75);
    for ($a = -4; $a < $len + 10; $a += 1.4) {
        $s->line($f->pt($a, 0, 0), $f->pt($a, 0, 4.0), 0.5, 1);
    }
    return $s->save();
}

/** A plot: survey stakes and string, a block wall begun, concrete frames rising next door, poles, the mountains. */
function sk_scene_land(int $seed, bool $farm = false): string
{
    $s = new Sketch(SK_W, SK_H, $seed);
    $p = sk_pal();
    $c = sk_camera($s, Sketch::r(0.5, 0.58), 0.9);
    $s->sky($c->horizon * 0.85);
    $s->ridge($c->horizon - 2 * $s->S, Sketch::r(110, 180) * $s->S, [168, 164, 190]);
    $s->ridge($c->horizon + 2 * $s->S, Sketch::r(40, 80) * $s->S, [150, 150, 168]);
    $hz = $c->horizon;
    $s->wash([[-20, $hz], [$s->W + 20, $hz], [$s->W + 20, $s->H + 20], [-20, $s->H + 20]], $farm ? [196, 190, 130] : $p['ground'], 90, 2);
    if ($farm) {
        // furrows converging, a line of poplars at the field edge
        for ($x = -60; $x <= 60; $x += 3) {
            $s->line($c->p($x, 0, 6), $c->p($x * 1.0, 0, 120), 0.5, 1);
        }
        for ($x = -50; $x < 50; $x += Sketch::r(3, 5)) {
            $b = $c->p($x, 0, 90);
            sk_cypress($s, $b[0], $b[1], $c->scaleAt($x, 90), Sketch::r(9, 13));
        }
    } else {
        // half-built concrete frames on the next plots — columns, slabs, rebar sticking up
        for ($k = 0; $k < mt_rand(1, 3); $k++) {
            $bx = Sketch::r(-30, 18);
            $bz = Sketch::r(45, 70);
            $fl = mt_rand(1, 3);
            $bw = Sketch::r(10, 14);
            for ($i = 0; $i <= $fl; $i++) {
                $y = $i * 3.2;
                $slab = array_map(fn($q) => [$q[0], $q[1]], [$c->p($bx, $y, $bz), $c->p($bx + $bw, $y, $bz), $c->p($bx + $bw, $y + 0.3, $bz), $c->p($bx, $y + 0.3, $bz)]);
                $s->knockout($slab);
                $s->poly($slab, true, 1.1);
                $s->wash($slab, [190, 188, 182], 80, 1);
            }
            for ($cx = 0; $cx <= $bw; $cx += $bw / 3) {
                for ($i = 0; $i < $fl; $i++) {
                    $s->line($c->p($bx + $cx, $i * 3.2 + 0.3, $bz), $c->p($bx + $cx, ($i + 1) * 3.2, $bz), 1.3);
                }
                $top = $c->p($bx + $cx, $fl * 3.2 + 0.3, $bz);
                for ($r = -1; $r <= 1; $r++) {
                    $s->line([$top[0] + $r * 2 * $s->S, $top[1]], [$top[0] + $r * 3 * $s->S, $top[1] - 14 * $s->S], 0.5, 1);
                }
            }
            $s->wash(array_map(fn($q) => [$q[0], $q[1]], [$c->p($bx, 0, $bz), $c->p($bx + $bw, 0, $bz), $c->p($bx + $bw, $fl * 3.2, $bz), $c->p($bx, $fl * 3.2, $bz)]), $p['shade'], 110, 1);
        }
        // a finished house or two further along
        $hx = Sketch::r(10, 30) * (mt_rand(0, 1) ? 1 : -1);
        [$hf, $hs] = sk_mass($c, [$hx, 0.0, 80.0], 0.0, 10, 10, 7, $hx < 0 ? 'right' : 'left');
        sk_draw_mass($s, $hf, $hs, Sketch::pick($p['plaster']));
        sk_window($s, $hf, 2, 4, 1.4, 1.4);
        sk_window($s, $hf, 6, 4, 1.4, 1.4);
        // plot corners: painted steel stakes with string between them
        $px = Sketch::r(-9, -5);
        $pz0 = Sketch::r(9, 12);
        $pw = Sketch::r(12, 18);
        $pd = Sketch::r(18, 26);
        $corners = [[$px, $pz0], [$px + $pw, $pz0], [$px + $pw, $pz0 + $pd], [$px, $pz0 + $pd]];
        $tops = [];
        foreach ($corners as [$x, $z]) {
            $b = $c->p($x, 0, $z);
            $t = $c->p($x, 1.3, $z);
            $s->line([$b[0], $b[1]], [$t[0], $t[1]], 2.2);
            $s->wash([[$t[0] - 4, $t[1]], [$t[0] + 4, $t[1]], [$t[0] + 4, $t[1] + ($b[1] - $t[1]) * 0.3], [$t[0] - 4, $t[1] + ($b[1] - $t[1]) * 0.3]], $p['red'], 60, 0);
            $tops[] = [$t[0], $t[1] + 3];
        }
        for ($i = 0; $i < 4; $i++) {
            sk_wire($s, $tops[$i], $tops[($i + 1) % 4], 2 * $s->S);
        }
        // a few courses of block wall begun along the front
        $wall = new SketchFace($c, [$px, 0.0, $pz0], [1.0, 0.0, 0.0], $pw * Sketch::r(0.3, 0.6), 0.6);
        $wq = $wall->quad(0, 0, $wall->w, 0.6);
        $s->knockout($wq);
        $s->poly($wq, true, 1.4);
        for ($b = 0.2; $b < 0.6; $b += 0.2) {
            $s->line($wall->pt(0, $b), $wall->pt($wall->w, $b), 0.6, 1);
            for ($a = ($b * 5) % 2 ? 0.2 : 0.4; $a < $wall->w; $a += 0.4) {
                $s->line($wall->pt($a, $b - 0.2), $wall->pt($a, $b), 0.5, 1);
            }
        }
        $s->wash($wq, [196, 196, 190], 84, 1);
        // sale board on two legs (lettering only suggested)
        $bx = $px + $pw + Sketch::r(1, 3);
        $bz = $pz0 + 1;
        $l1 = $c->p($bx, 0, $bz);
        $l2 = $c->p($bx + 1.6, 0, $bz);
        $bt = array_map(fn($q) => [$q[0], $q[1]], [$c->p($bx - 0.2, 1.3, $bz), $c->p($bx + 1.8, 1.3, $bz), $c->p($bx + 1.8, 2.3, $bz), $c->p($bx - 0.2, 2.3, $bz)]);
        $s->line([$l1[0], $l1[1]], $bt[0], 1.3);
        $s->line([$l2[0], $l2[1]], $bt[1], 1.3);
        $s->knockout($bt);
        $s->poly($bt, true, 1.5);
        $s->wash($bt, $p['saffron'], 72, 1);
        $ly = $bt[0][1] - ($bt[0][1] - $bt[3][1]) * 0.6;
        $s->curve([[$bt[0][0] + 10, $ly], [$bt[0][0] + 30, $ly - 6], [$bt[0][0] + 50, $ly + 4], [$bt[1][0] - 10, $ly - 2]], 1.6, 10, 1.5);
        $s->line([$bt[0][0] + 12, $ly + 18], [$bt[1][0] - 20, $ly + 18], 1.0, 1);
    }
    // power line marching off towards the hills
    $tops = null;
    $side = mt_rand(0, 1) ? 1 : -1;
    for ($k = 0; $k < 6; $k++) {
        $z = 12 + $k * 18;
        $b = $c->p($side * Sketch::r(14, 16), 0, $z);
        $t = sk_pole($s, $b[0], $b[1], $c->scaleAt($b[0], $z));
        if ($tops) {
            sk_wire($s, $tops[0], $t[0], 8 * $s->S / ($k + 1));
            sk_wire($s, $tops[1], $t[1], 8 * $s->S / ($k + 1));
        }
        $tops = $t;
    }
    sk_tufts($s, $hz + 20 * $s->S, $s->H, 46);
    $s->hatch([[0, $s->H * 0.85], [$s->W * 0.4, $s->H * 0.82], [$s->W * 0.5, $s->H], [0, $s->H]], 10, 7, 0.6, 0.45, 70);
    return $s->save();
}

/** A room in one-point perspective; returns the camera and the room box so the caller can furnish it. */
function sk_room(Sketch $s, float $wd, float $ht, float $dp, array $wall): array
{
    $c = new SketchCam($s->W * 0.62, $s->W / 2 + Sketch::r(-0.06, 0.06) * $s->W, $s->H * Sketch::r(0.46, 0.52), Sketch::r(1.3, 1.5), 0.0, Sketch::r(-0.4, 0.4), 0.0);
    $x0 = -$wd / 2;
    $x1 = $wd / 2;
    $P = fn($x, $y, $z) => array_slice($c->p($x, $y, $z), 0, 2);
    $back = [$P($x0, 0, $dp), $P($x1, 0, $dp), $P($x1, $ht, $dp), $P($x0, $ht, $dp)];
    $near = 0.6;
    $s->poly($back, true, 1.6);
    foreach ([[$x0, 0], [$x1, 0], [$x1, $ht], [$x0, $ht]] as [$x, $y]) {
        $s->line($P($x, $y, $near), $P($x, $y, $dp), 1.6);
    }
    $s->wash($back, $wall, 84, 2);
    $s->wash([$P($x0, 0, $near), $P($x0, 0, $dp), $P($x0, $ht, $dp), $P($x0, $ht, $near)], $wall, 84, 2);
    $s->wash([$P($x1, 0, $near), $P($x1, 0, $dp), $P($x1, $ht, $dp), $P($x1, $ht, $near)], $wall, 84, 2);
    $s->wash([$P($x1, 0, $near), $P($x1, 0, $dp), $P($x1, $ht, $dp), $P($x1, $ht, $near)], sk_pal()['shade'], 108, 2);
    // floor tiles (porcelain/marble, the norm here) in perspective
    $floor = [$P($x0, 0, $near), $P($x1, 0, $near), $P($x1, 0, $dp), $P($x0, 0, $dp)];
    $s->wash($floor, Sketch::pick([[220, 212, 198], [206, 196, 182], [228, 224, 216]]), 86, 2);
    for ($x = $x0; $x <= $x1 + 0.01; $x += 0.6) {
        $s->line($P($x, 0, $near), $P($x, 0, $dp), 0.5, 1);
    }
    for ($z = 1.2; $z < $dp; $z += 0.6) {
        $s->line($P($x0, 0, $z), $P($x1, 0, $z), 0.5, 1);
    }
    // cornice line under the ceiling
    $s->line($P($x0, $ht - 0.12, $dp), $P($x1, $ht - 0.12, $dp), 0.8, 1);
    return [$c, $P, $x0, $x1, $near];
}

/** Living room: L-shaped sofa, kilim rug, coffee table, curtained window, split-AC on the wall, chandelier. */
function sk_scene_living(int $seed): string
{
    $s = new Sketch(SK_W, SK_H, $seed);
    $p = sk_pal();
    $wd = Sketch::r(4.6, 5.6);
    $ht = 3.0;
    $dp = Sketch::r(5.2, 6.4);
    [$c, $P, $x0, $x1] = sk_room($s, $wd, $ht, $dp, Sketch::pick([[236, 226, 206], [226, 222, 214], [232, 214, 196], [214, 222, 216]]));
    // window on the back wall with curtains and a view of the neighbours
    $wx = Sketch::r($x0 + 0.4, $x1 - 2.6);
    $win = [$P($wx, 0.9, $dp), $P($wx + 2.2, 0.9, $dp), $P($wx + 2.2, 2.5, $dp), $P($wx, 2.5, $dp)];
    $s->knockout($win);
    $s->wash($win, [196, 216, 230], 76, 1);
    $hz = $win[0][1] - ($win[0][1] - $win[3][1]) * 0.45;
    $s->curve([[$win[0][0], $hz], [$win[0][0] + ($win[1][0] - $win[0][0]) * 0.3, $hz - 18 * $s->S], [$win[0][0] + ($win[1][0] - $win[0][0]) * 0.6, $hz - 6 * $s->S], [$win[1][0], $hz - 20 * $s->S]], 0.8, 40);
    $s->poly($win, true, 1.5);
    $s->line($P($wx + 1.1, 0.9, $dp), $P($wx + 1.1, 2.5, $dp), 1.0);
    $curtain = Sketch::pick([[176, 80, 66], [196, 170, 120], [100, 120, 140], [150, 110, 140]]);
    foreach ([[$wx - 0.5, $wx + 0.2], [$wx + 2.0, $wx + 2.7]] as [$ca, $cb]) {
        $q = [$P($ca, 0.1, $dp - 0.05), $P($cb, 0.1, $dp - 0.05), $P($cb, 2.7, $dp - 0.05), $P($ca, 2.7, $dp - 0.05)];
        $s->knockout($q);
        $s->wash($q, $curtain, 76, 1);
        for ($k = 0; $k <= 4; $k++) {
            $x = $ca + ($cb - $ca) * $k / 4;
            $s->curve([$P($x, 2.7, $dp - 0.05), $P($x + 0.04, 1.6, $dp - 0.05), $P($x - 0.03, 0.6, $dp - 0.05), $P($x + 0.02, 0.1, $dp - 0.05)], 0.8, 30, 0.3);
        }
    }
    $s->line($P($wx - 0.6, 2.75, $dp - 0.05), $P($wx + 2.8, 2.75, $dp - 0.05), 1.4);
    // split AC indoor unit high on the side wall
    $sw = mt_rand(0, 1) ? $x0 : $x1;
    $az = $dp * 0.62;
    $ac = [$P($sw, 2.35, $az), $P($sw, 2.35, $az + 0.9), $P($sw, 2.62, $az + 0.9), $P($sw, 2.62, $az)];
    $s->knockout($ac);
    $s->poly($ac, true, 1.3);
    $s->wash($ac, [240, 240, 236], 96, 1);
    $s->line($P($sw, 2.42, $az + 0.05), $P($sw, 2.42, $az + 0.85), 0.6, 1);
    // chandelier
    $ch = $P(0, $ht, $dp * 0.55);
    $cl = $P(0, $ht - 0.55, $dp * 0.55);
    $m = $c->scaleAt(0, $dp * 0.55);
    $s->line($ch, $cl, 0.9);
    $s->ellipse($cl[0], $cl[1], 0.45 * $m, 0.1 * $m, 1.1);
    for ($k = -2; $k <= 2; $k++) {
        $s->curve([[$cl[0] + $k * 0.18 * $m, $cl[1]], [$cl[0] + $k * 0.2 * $m, $cl[1] + 0.12 * $m], [$cl[0] + $k * 0.16 * $m, $cl[1] + 0.25 * $m]], 0.7, 30, 0.2);
    }
    $s->wash([[$cl[0] - 0.5 * $m, $cl[1] - 0.1 * $m], [$cl[0] + 0.5 * $m, $cl[1] - 0.1 * $m], [$cl[0] + 0.3 * $m, $cl[1] + 0.3 * $m], [$cl[0] - 0.3 * $m, $cl[1] + 0.3 * $m]], $p['saffron'], 100, 2);
    // kilim rug: diamonds along the centre and a border, in perspective on the floor
    $rz0 = $dp * 0.32;
    $rz1 = $dp * 0.78;
    $rx0 = -1.2;
    $rx1 = 1.2;
    $rug = [$P($rx0, 0.01, $rz0), $P($rx1, 0.01, $rz0), $P($rx1, 0.01, $rz1), $P($rx0, 0.01, $rz1)];
    $s->knockout($rug);
    $rc = Sketch::pick([[168, 64, 52], [150, 54, 60], [176, 90, 50]]);
    $s->wash($rug, $rc, 70, 1);
    $s->poly($rug, true, 1.3);
    $s->poly([$P($rx0 + 0.15, 0.01, $rz0 + 0.15), $P($rx1 - 0.15, 0.01, $rz0 + 0.15), $P($rx1 - 0.15, 0.01, $rz1 - 0.15), $P($rx0 + 0.15, 0.01, $rz1 - 0.15)], true, 0.8, 1);
    $nd = 4;
    for ($k = 0; $k < $nd; $k++) {
        $zc = $rz0 + ($rz1 - $rz0) * ($k + 0.5) / $nd;
        $hz = ($rz1 - $rz0) / $nd * 0.42;
        $d = [$P(0, 0.01, $zc - $hz), $P(0.55, 0.01, $zc), $P(0, 0.01, $zc + $hz), $P(-0.55, 0.01, $zc)];
        $s->poly($d, true, 1.0, 1);
        $s->wash($d, $k % 2 ? [228, 168, 60] : [236, 226, 206], 70, 0.5);
        $s->poly([$P(0, 0.01, $zc - $hz * 0.45), $P(0.25, 0.01, $zc), $P(0, 0.01, $zc + $hz * 0.45), $P(-0.25, 0.01, $zc)], true, 0.7, 1);
    }
    // coffee table
    $tz = $dp * 0.5;
    $top = [$P(-0.6, 0.45, $tz - 0.35), $P(0.6, 0.45, $tz - 0.35), $P(0.6, 0.45, $tz + 0.35), $P(-0.6, 0.45, $tz + 0.35)];
    foreach ([[-0.55, -0.3], [0.55, -0.3]] as [$lx, $lz]) {
        $s->line($P($lx, 0, $tz + $lz), $P($lx, 0.45, $tz + $lz), 1.3);
    }
    $s->knockout($top);
    $s->poly($top, true, 1.5);
    $s->wash($top, $p['wood'], 76, 1);
    $s->ellipse(...[...$P(0.2, 0.47, $tz), 0.08 * $c->scaleAt(0, $tz), 0.03 * $c->scaleAt(0, $tz), 1.0]);
    // L-shaped sofa: along the back wall and down one side
    $sofa = Sketch::pick([[190, 176, 150], [120, 132, 140], [150, 96, 84], [96, 116, 104]]);
    $sx = mt_rand(0, 1) ? -1 : 1;
    $bz = $dp - 0.95;
    $xa = $sx < 0 ? $x0 + 0.1 : $x1 - 2.6;
    $seat = [$P($xa, 0.45, $bz), $P($xa + 2.5, 0.45, $bz), $P($xa + 2.5, 0.45, $bz + 0.85), $P($xa, 0.45, $bz + 0.85)];
    $back = [$P($xa, 0.45, $bz + 0.85), $P($xa + 2.5, 0.45, $bz + 0.85), $P($xa + 2.5, 0.95, $bz + 0.85), $P($xa, 0.95, $bz + 0.85)];
    $frontQ = [$P($xa, 0.05, $bz), $P($xa + 2.5, 0.05, $bz), $P($xa + 2.5, 0.45, $bz), $P($xa, 0.45, $bz)];
    foreach ([$back, $seat, $frontQ] as $q) {
        $s->knockout($q);
        $s->poly($q, true, 1.4);
        $s->wash($q, $sofa, 74, 1);
    }
    $s->hatch($frontQ, 80, 4, 0.5, 0.6, 60);
    for ($k = 1; $k < 3; $k++) {
        $s->line($P($xa + $k * 2.5 / 3, 0.45, $bz), $P($xa + $k * 2.5 / 3, 0.45, $bz + 0.85), 0.8, 1);
        $s->line($P($xa + $k * 2.5 / 3, 0.45, $bz + 0.85), $P($xa + $k * 2.5 / 3, 0.95, $bz + 0.85), 0.8, 1);
    }
    // cushions
    for ($k = 0; $k < 2; $k++) {
        $cx = $xa + 0.4 + $k * 1.5;
        $cq = [$P($cx, 0.5, $bz + 0.75), $P($cx + 0.45, 0.5, $bz + 0.75), $P($cx + 0.45, 0.9, $bz + 0.75), $P($cx, 0.9, $bz + 0.75)];
        $s->knockout($cq);
        $s->poly($cq, true, 1.1);
        $s->wash($cq, Sketch::pick([[228, 168, 60], [184, 70, 56], [236, 230, 214]]), 70, 1);
    }
    // the side arm of the L, nearer the viewer
    $ax = $sx < 0 ? $x0 + 0.1 : $x1 - 0.95;
    $za = $bz - 2.0;
    $seatS = [$P($ax, 0.45, $za), $P($ax + 0.85, 0.45, $za), $P($ax + 0.85, 0.45, $bz), $P($ax, 0.45, $bz)];
    $frontS = $sx < 0
        ? [$P($ax + 0.85, 0.05, $za), $P($ax + 0.85, 0.05, $bz), $P($ax + 0.85, 0.45, $bz), $P($ax + 0.85, 0.45, $za)]
        : [$P($ax, 0.05, $za), $P($ax, 0.05, $bz), $P($ax, 0.45, $bz), $P($ax, 0.45, $za)];
    $endS = [$P($ax, 0.05, $za), $P($ax + 0.85, 0.05, $za), $P($ax + 0.85, 0.6, $za), $P($ax, 0.6, $za)];
    foreach ([$seatS, $frontS, $endS] as $q) {
        $s->knockout($q);
        $s->poly($q, true, 1.4);
        $s->wash($q, $sofa, 74, 1);
    }
    $s->wash($frontS, $p['shade'], 106, 1);
    // a plant in the corner
    $pc = $P($sx < 0 ? $x1 - 0.4 : $x0 + 0.4, 0, $dp - 0.4);
    $pm = $c->scaleAt(0, $dp - 0.4);
    $pot = [[$pc[0] - 0.2 * $pm, $pc[1]], [$pc[0] + 0.2 * $pm, $pc[1]], [$pc[0] + 0.25 * $pm, $pc[1] - 0.4 * $pm], [$pc[0] - 0.25 * $pm, $pc[1] - 0.4 * $pm]];
    $s->knockout($pot);
    $s->poly($pot, true, 1.3);
    $s->wash($pot, [190, 110, 80], 76, 1);
    for ($k = 0; $k < 9; $k++) {
        $a = -M_PI / 2 + Sketch::r(-1.1, 1.1);
        $l = Sketch::r(0.5, 1.1) * $pm;
        $b = [$pc[0], $pc[1] - 0.4 * $pm];
        $e = [$b[0] + cos($a) * $l, $b[1] + sin($a) * $l];
        $s->curve([$b, [($b[0] + $e[0]) / 2 + Sketch::r(-8, 8), ($b[1] + $e[1]) / 2], $e], 1.0, 20, 0.3);
        $s->wash([[$e[0], $e[1]], [($b[0] + $e[0]) / 2 + 10, ($b[1] + $e[1]) / 2], $b, [($b[0] + $e[0]) / 2 - 10, ($b[1] + $e[1]) / 2]], $p['green'], 80, 1);
    }
    return $s->save();
}

/** Kitchen: base and wall cabinets on two walls, worktop, tiled splashback, window over the sink, fridge. */
function sk_scene_kitchen(int $seed): string
{
    $s = new Sketch(SK_W, SK_H, $seed);
    $p = sk_pal();
    $wd = Sketch::r(3.4, 4.2);
    $dp = Sketch::r(4.2, 5.2);
    [$c, $P, $x0, $x1] = sk_room($s, $wd, 2.9, $dp, Sketch::pick([[236, 230, 216], [226, 226, 220], [234, 222, 204]]));
    $cab = Sketch::pick([[236, 232, 222], [168, 118, 80], [110, 128, 120], [74, 84, 96], [210, 196, 170]]);
    $L = mt_rand(0, 1) ? $x0 : $x1;
    $sgn = $L === $x0 ? 1 : -1;
    // back wall run
    $bz = $dp;
    $runs = [
        // [x from, x to, z from, z to, faces z?]
        ['back', $x0 + 0.02, $x1 - 0.02],
    ];
    $tile = [$P($x0, 0.9, $bz - 0.01), $P($x1, 0.9, $bz - 0.01), $P($x1, 1.5, $bz - 0.01), $P($x0, 1.5, $bz - 0.01)];
    $s->hatch($tile, 0, 5, 0.5, 0.9, 70);
    $s->hatch($tile, 90, 5, 0.5, 0.9, 70);
    $s->wash($tile, Sketch::pick([[190, 210, 214], [226, 220, 206], [200, 190, 170]]), 88, 1);
    // window over the sink
    $wx = Sketch::r($x0 + 0.6, $x1 - 1.8);
    $win = [$P($wx, 1.5, $bz), $P($wx + 1.2, 1.5, $bz), $P($wx + 1.2, 2.5, $bz), $P($wx, 2.5, $bz)];
    $s->knockout($win);
    $s->wash($win, [196, 216, 230], 76, 1);
    $s->poly($win, true, 1.5);
    $s->line($P($wx + 0.6, 1.5, $bz), $P($wx + 0.6, 2.5, $bz), 1.0);
    // wall cabinets either side of the window
    foreach ([[$x0 + 0.02, $wx - 0.1], [$wx + 1.3, $x1 - 0.02]] as [$a, $b]) {
        if ($b - $a < 0.3) {
            continue;
        }
        $q = [$P($a, 1.65, $bz - 0.35), $P($b, 1.65, $bz - 0.35), $P($b, 2.5, $bz - 0.35), $P($a, 2.5, $bz - 0.35)];
        $s->knockout($q);
        $s->poly($q, true, 1.4);
        $s->wash($q, $cab, 78, 1);
        for ($x = $a + 0.6; $x < $b - 0.1; $x += 0.6) {
            $s->line($P($x, 1.65, $bz - 0.35), $P($x, 2.5, $bz - 0.35), 0.8, 1);
        }
        $s->line($P($a, 1.65, $bz - 0.35), $P($a, 1.65, $bz), 0.9, 1);
    }
    // base cabinets and worktop along the back
    $front = [$P($x0 + 0.02, 0.1, $bz - 0.6), $P($x1 - 0.02, 0.1, $bz - 0.6), $P($x1 - 0.02, 0.86, $bz - 0.6), $P($x0 + 0.02, 0.86, $bz - 0.6)];
    $s->knockout($front);
    $s->poly($front, true, 1.5);
    $s->wash($front, $cab, 76, 1);
    for ($x = $x0 + 0.6; $x < $x1 - 0.1; $x += 0.6) {
        $s->line($P($x, 0.1, $bz - 0.6), $P($x, 0.86, $bz - 0.6), 0.9, 1);
        $s->line($P($x - 0.1, 0.7, $bz - 0.62), $P($x - 0.1, 0.78, $bz - 0.62), 1.2, 1);
    }
    $top = [$P($x0, 0.9, $bz - 0.62), $P($x1, 0.9, $bz - 0.62), $P($x1, 0.9, $bz), $P($x0, 0.9, $bz)];
    $s->knockout([$P($x0, 0.86, $bz - 0.62), $P($x1, 0.86, $bz - 0.62), $P($x1, 0.9, $bz - 0.62), $P($x0, 0.9, $bz - 0.62)]);
    $s->poly($top, true, 1.3);
    $s->line($P($x0, 0.86, $bz - 0.62), $P($x1, 0.86, $bz - 0.62), 1.3);
    $s->wash($top, Sketch::pick([[60, 60, 64], [226, 222, 214], [150, 140, 128]]), 80, 1);
    // sink and tap under the window, hob on the worktop
    $sk = [$P($wx + 0.2, 0.9, $bz - 0.5), $P($wx + 1.0, 0.9, $bz - 0.5), $P($wx + 1.0, 0.9, $bz - 0.12), $P($wx + 0.2, 0.9, $bz - 0.12)];
    $s->poly($sk, true, 1.1);
    $s->wash($sk, [180, 186, 190], 80, 0.5);
    $s->curve([$P($wx + 0.6, 0.9, $bz - 0.06), $P($wx + 0.6, 1.2, $bz - 0.06), $P($wx + 0.6, 1.22, $bz - 0.25), $P($wx + 0.6, 1.1, $bz - 0.3)], 1.2, 10, 0.2);
    $hx = $wx > 0 ? $x0 + 0.5 : $x1 - 1.1;
    for ($k = 0; $k < 4; $k++) {
        $cpt = $P($hx + ($k % 2) * 0.32, 0.9, $bz - 0.45 + intdiv($k, 2) * 0.25);
        $m = $c->scaleAt(0, $bz - 0.3);
        $s->ellipse($cpt[0], $cpt[1], 0.1 * $m, 0.035 * $m, 1.0);
    }
    $hood = [$P($hx - 0.1, 2.0, $bz - 0.25), $P($hx + 0.5, 2.0, $bz - 0.25), $P($hx + 0.5, 2.0, $bz - 0.25), $P($hx + 0.5, 2.0, $bz - 0.25)];
    // fridge standing against the side wall near the back
    $fz = $bz - 0.65;
    $fx = $L === $x0 ? $x0 + 0.02 : $x1 - 0.72;
    $ff = [$P($fx, 0, $fz - 0.7), $P($fx + 0.7, 0, $fz - 0.7), $P($fx + 0.7, 1.85, $fz - 0.7), $P($fx, 1.85, $fz - 0.7)];
    $fs = $L === $x0
        ? [$P($fx + 0.7, 0, $fz - 0.7), $P($fx + 0.7, 0, $fz), $P($fx + 0.7, 1.85, $fz), $P($fx + 0.7, 1.85, $fz - 0.7)]
        : [$P($fx, 0, $fz - 0.7), $P($fx, 0, $fz), $P($fx, 1.85, $fz), $P($fx, 1.85, $fz - 0.7)];
    foreach ([$fs, $ff] as $q) {
        $s->knockout($q);
        $s->poly($q, true, 1.5);
        $s->wash($q, Sketch::pick([[214, 216, 218], [232, 232, 230]]), 86, 1);
    }
    $s->wash($fs, $p['shade'], 106, 1);
    $s->line($P($fx, 1.2, $fz - 0.7), $P($fx + 0.7, 1.2, $fz - 0.7), 1.0, 1);
    $s->line($P($fx + ($L === $x0 ? 0.6 : 0.1), 1.3, $fz - 0.71), $P($fx + ($L === $x0 ? 0.6 : 0.1), 1.6, $fz - 0.71), 1.6, 1);
    // pendant lights
    for ($k = 0; $k < 2; $k++) {
        $px = -0.5 + $k;
        $a = $P($px, 2.9, $dp * 0.5);
        $b = $P($px, 2.2, $dp * 0.5);
        $m = $c->scaleAt($px, $dp * 0.5);
        $s->line($a, $b, 0.8, 1);
        $shade = [[$b[0] - 0.08 * $m, $b[1]], [$b[0] + 0.08 * $m, $b[1]], [$b[0] + 0.18 * $m, $b[1] + 0.2 * $m], [$b[0] - 0.18 * $m, $b[1] + 0.2 * $m]];
        $s->knockout($shade);
        $s->poly($shade, true, 1.2);
        $s->wash($shade, Sketch::pick([[60, 60, 64], [228, 168, 60]]), 76, 0.5);
    }
    return $s->save();
}

/** Inside a shop or an office: plain, tiled, lit — what a tenant is actually renting. */
function sk_scene_interior_work(int $seed, bool $office): string
{
    $s = new Sketch(SK_W, SK_H, $seed);
    $p = sk_pal();
    $wd = Sketch::r(4.5, 6);
    $dp = Sketch::r(6, 9);
    [$c, $P, $x0, $x1] = sk_room($s, $wd, 3.2, $dp, Sketch::pick([[236, 234, 228], [226, 226, 222]]));
    // ceiling panel lights
    for ($z = 1.6; $z < $dp - 0.5; $z += 1.8) {
        foreach ([-1.0, 1.0] as $x) {
            $q = [$P($x - 0.3, 3.19, $z - 0.3), $P($x + 0.3, 3.19, $z - 0.3), $P($x + 0.3, 3.19, $z + 0.3), $P($x - 0.3, 3.19, $z + 0.3)];
            $s->poly($q, true, 0.9, 1);
        }
    }
    if ($office) {
        // a window wall at the back, desks with chairs and screens
        $win = [$P($x0 + 0.3, 0.8, $dp), $P($x1 - 0.3, 0.8, $dp), $P($x1 - 0.3, 2.8, $dp), $P($x0 + 0.3, 2.8, $dp)];
        $s->knockout($win);
        $s->wash($win, [196, 216, 230], 76, 1);
        $s->poly($win, true, 1.5);
        for ($x = $x0 + 0.3; $x < $x1 - 0.3; $x += 1.0) {
            $s->line($P($x, 0.8, $dp), $P($x, 2.8, $dp), 0.9, 1);
        }
        $sky = $P(0, 1.6, $dp);
        $s->curve([[$win[0][0], $sky[1]], [$win[0][0] + 40 * $s->S, $sky[1] - 30 * $s->S], [$win[0][0] + 90 * $s->S, $sky[1] - 10 * $s->S], [$win[1][0], $sky[1] - 26 * $s->S]], 0.9, 30);
        foreach ([$dp * 0.72, $dp * 0.45] as $dz) {
            foreach ([-1.2, 1.2] as $dx) {
                $ch = [$P($dx - 0.22, 0.45, $dz + 0.75), $P($dx + 0.22, 0.45, $dz + 0.75), $P($dx + 0.22, 1.1, $dz + 0.75), $P($dx - 0.22, 1.1, $dz + 0.75)];
                $s->knockout($ch);
                $s->poly($ch, true, 1.3);
                $s->wash($ch, [60, 64, 70], 80, 1);
                $top = [$P($dx - 0.7, 0.75, $dz - 0.4), $P($dx + 0.7, 0.75, $dz - 0.4), $P($dx + 0.7, 0.75, $dz + 0.4), $P($dx - 0.7, 0.75, $dz + 0.4)];
                foreach ([-0.65, 0.65] as $lx) {
                    $s->line($P($dx + $lx, 0, $dz - 0.35), $P($dx + $lx, 0.75, $dz - 0.35), 1.2);
                }
                $s->knockout($top);
                $s->poly($top, true, 1.4);
                $s->wash($top, Sketch::pick([[214, 190, 160], [236, 236, 232]]), 78, 1);
                $scr = [$P($dx - 0.3, 0.8, $dz + 0.1), $P($dx + 0.3, 0.8, $dz + 0.1), $P($dx + 0.3, 1.15, $dz + 0.1), $P($dx - 0.3, 1.15, $dz + 0.1)];
                $s->knockout($scr);
                $s->poly($scr, true, 1.2);
                $s->wash($scr, [60, 64, 74], 76, 0.5);
                $s->line($P($dx, 0.75, $dz + 0.12), $P($dx, 0.8, $dz + 0.12), 1.2);
                $kb = [$P($dx - 0.25, 0.76, $dz - 0.25), $P($dx + 0.25, 0.76, $dz - 0.25), $P($dx + 0.25, 0.76, $dz - 0.1), $P($dx - 0.25, 0.76, $dz - 0.1)];
                $s->poly($kb, true, 0.8, 1);
            }
        }
    } else {
        // empty shop: shelving along one wall, a counter near the door, rolled-up shutter at the front edge
        $sw = mt_rand(0, 1) ? $x0 : $x1;
        $sgn = $sw === $x0 ? 1 : -1;
        for ($b = 0.4; $b < 2.6; $b += 0.55) {
            $s->line($P($sw + $sgn * 0.4, $b, 1.2), $P($sw + $sgn * 0.4, $b, $dp - 0.2), 1.1);
            $s->line($P($sw, $b + 0.03, 1.2), $P($sw + $sgn * 0.4, $b, 1.2), 0.8, 1);
        }
        for ($z = 1.2; $z < $dp; $z += 1.5) {
            $s->line($P($sw + $sgn * 0.4, 0, $z), $P($sw + $sgn * 0.4, 2.6, $z), 1.1);
        }
        $cx = -$sgn * ($wd / 2 - 1.0);
        $cq = [$P($cx - 0.8, 0, $dp * 0.55), $P($cx + 0.8, 0, $dp * 0.55), $P($cx + 0.8, 1.0, $dp * 0.55), $P($cx - 0.8, 1.0, $dp * 0.55)];
        $s->knockout($cq);
        $s->poly($cq, true, 1.5);
        $s->wash($cq, $p['wood'], 76, 1);
        $s->poly([$P($cx - 0.85, 1.0, $dp * 0.55), $P($cx + 0.85, 1.0, $dp * 0.55), $P($cx + 0.85, 1.0, $dp * 0.55 + 0.6), $P($cx - 0.85, 1.0, $dp * 0.55 + 0.6)], true, 1.2);
        $door = [$P(-0.9, 0, $dp), $P(0.9, 0, $dp), $P(0.9, 2.3, $dp), $P(-0.9, 2.3, $dp)];
        $s->poly($door, true, 1.4);
        $s->wash($door, [150, 130, 110], 84, 1);
        $sh = [$P($x0, 2.9, 0.7), $P($x1, 2.9, 0.7), $P($x1, 3.2, 0.7), $P($x0, 3.2, 0.7)];
        $s->hatch($sh, 0, 3, 0.6, 1, 40);
    }
    return $s->save();
}

/** Which drawings a listing of this type gets, in order (cover first). */
function sk_scenes_for(string $type): array
{
    return match ($type) {
        'villa'     => ['villa', 'living', 'kitchen', 'villa_alt'],
        'house'     => ['house', 'living', 'kitchen', 'house_alt'],
        'apartment' => ['towers', 'living', 'kitchen', 'towers_alt'],
        'shop'      => ['shops', 'shop_inside', 'shops'],
        'office'    => ['offices', 'office_inside', 'offices'],
        'building'  => ['offices', 'shops', 'office_inside'],
        'land'      => ['land', 'land', 'land'],
        default     => ['house', 'living', 'kitchen'],
    };
}

function sk_render(string $scene, int $seed, bool $farm = false): string
{
    return match ($scene) {
        'villa'         => sk_scene_villa($seed),
        'villa_alt'     => sk_scene_villa($seed, true),
        'house'         => sk_scene_house($seed),
        'house_alt'     => sk_scene_house($seed, true),
        'towers'        => sk_scene_towers($seed),
        'towers_alt'    => sk_scene_towers($seed, true),
        'shops'         => sk_scene_shops($seed),
        'offices'       => sk_scene_shops($seed, true),
        'shop_inside'   => sk_scene_interior_work($seed, false),
        'office_inside' => sk_scene_interior_work($seed, true),
        'living'        => sk_scene_living($seed),
        'kitchen'       => sk_scene_kitchen($seed),
        'land'          => sk_scene_land($seed, $farm),
        default         => sk_scene_house($seed),
    };
}
