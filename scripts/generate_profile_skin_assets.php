<?php

$baseDir = __DIR__ . '/../storage/app/public/profile-skins/neon-arcade';
if (! is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

function rgba($image, int $r, int $g, int $b, int $alpha = 0): int
{
    return imagecolorallocatealpha($image, $r, $g, $b, max(0, min(127, $alpha)));
}

function save_png($image, string $path): void
{
    imagepng($image, $path, 6);
    imagedestroy($image);
}

function draw_grid($image, int $w, int $h, int $step, int $color): void
{
    for ($x = 0; $x <= $w; $x += $step) {
        imageline($image, $x, 0, $x, $h, $color);
    }

    for ($y = 0; $y <= $h; $y += $step) {
        imageline($image, 0, $y, $w, $y, $color);
    }
}

function draw_neon_rect($image, int $x1, int $y1, int $x2, int $y2, int $color, int $thickness = 3): void
{
    imagesetthickness($image, $thickness);
    imagerectangle($image, $x1, $y1, $x2, $y2, $color);
    imagesetthickness($image, 1);
}

// Background: arcade cabinet neon room.
$bg = imagecreatetruecolor(1600, 900);
imagealphablending($bg, true);
imagesavealpha($bg, true);
for ($y = 0; $y < 900; $y++) {
    $t = $y / 899;
    $r = (int) (8 + 22 * $t);
    $g = (int) (4 + 8 * $t);
    $b = (int) (20 + 45 * $t);
    imageline($bg, 0, $y, 1600, $y, rgba($bg, $r, $g, $b));
}
draw_grid($bg, 1600, 900, 48, rgba($bg, 34, 211, 238, 108));
imagefilledrectangle($bg, 0, 620, 1600, 900, rgba($bg, 4, 8, 16, 18));
for ($i = 0; $i < 14; $i++) {
    $x = 70 + ($i * 112);
    imagefilledrectangle($bg, $x, 655, $x + 54, 790, rgba($bg, 15, 23, 42, 8));
    draw_neon_rect($bg, $x, 655, $x + 54, 790, rgba($bg, $i % 2 ? 255 : 34, $i % 2 ? 61 : 211, $i % 2 ? 172 : 238, 28), 2);
}
imagefilledellipse($bg, 1240, 185, 520, 520, rgba($bg, 168, 85, 247, 92));
imagefilledellipse($bg, 1330, 145, 280, 280, rgba($bg, 34, 211, 238, 94));
save_png($bg, "$baseDir/background.png");

// Panel: repeatable-ish arcade monitor panel.
$panel = imagecreatetruecolor(1200, 720);
imagealphablending($panel, true);
imagesavealpha($panel, true);
imagefilledrectangle($panel, 0, 0, 1200, 720, rgba($panel, 7, 11, 22, 0));
draw_grid($panel, 1200, 720, 36, rgba($panel, 148, 163, 184, 116));
imagefilledrectangle($panel, 40, 40, 1160, 680, rgba($panel, 15, 23, 42, 18));
draw_neon_rect($panel, 40, 40, 1160, 680, rgba($panel, 34, 211, 238, 20), 6);
draw_neon_rect($panel, 68, 68, 1132, 652, rgba($panel, 244, 114, 182, 40), 2);
for ($i = 0; $i < 12; $i++) {
    imagefilledrectangle($panel, 90 + $i * 88, 600, 145 + $i * 88, 628, rgba($panel, 168, 85, 247, 50));
}
save_png($panel, "$baseDir/panel.png");

// Avatar frame: transparent arcade bezel.
$frame = imagecreatetruecolor(512, 512);
imagealphablending($frame, false);
imagesavealpha($frame, true);
imagefilledrectangle($frame, 0, 0, 512, 512, rgba($frame, 0, 0, 0, 127));
imagealphablending($frame, true);
for ($i = 0; $i < 14; $i++) {
    draw_neon_rect($frame, 54 - $i, 54 - $i, 458 + $i, 458 + $i, rgba($frame, 34, 211, 238, 108 - min(100, $i * 7)), 2);
}
draw_neon_rect($frame, 42, 42, 470, 470, rgba($frame, 244, 114, 182, 12), 8);
draw_neon_rect($frame, 76, 76, 436, 436, rgba($frame, 34, 211, 238, 0), 5);
imagefilledrectangle($frame, 88, 22, 424, 56, rgba($frame, 9, 13, 28, 0));
draw_neon_rect($frame, 88, 22, 424, 56, rgba($frame, 168, 85, 247, 18), 3);
save_png($frame, "$baseDir/avatar-frame.png");

// Decoration: transparent neon joystick and coin glyph shapes.
$decor = imagecreatetruecolor(700, 700);
imagealphablending($decor, false);
imagesavealpha($decor, true);
imagefilledrectangle($decor, 0, 0, 700, 700, rgba($decor, 0, 0, 0, 127));
imagealphablending($decor, true);
imagefilledellipse($decor, 350, 350, 520, 520, rgba($decor, 168, 85, 247, 112));
draw_neon_rect($decor, 210, 430, 490, 508, rgba($decor, 34, 211, 238, 18), 7);
imagefilledellipse($decor, 284, 468, 42, 42, rgba($decor, 244, 114, 182, 12));
imagefilledellipse($decor, 354, 468, 42, 42, rgba($decor, 34, 211, 238, 10));
imagefilledellipse($decor, 424, 468, 42, 42, rgba($decor, 250, 204, 21, 10));
imagesetthickness($decor, 16);
imageline($decor, 350, 420, 350, 270, rgba($decor, 34, 211, 238, 18));
imagefilledellipse($decor, 350, 230, 95, 95, rgba($decor, 244, 114, 182, 6));
imagesetthickness($decor, 1);
save_png($decor, "$baseDir/decoration.png");

// Preview: compact card seen in admin/shop.
$preview = imagecreatetruecolor(800, 450);
imagecopyresampled($preview, imagecreatefrompng("$baseDir/background.png"), 0, 0, 0, 0, 800, 450, 1600, 900);
imagefilledrectangle($preview, 44, 50, 360, 390, rgba($preview, 3, 7, 18, 22));
draw_neon_rect($preview, 44, 50, 360, 390, rgba($preview, 34, 211, 238, 8), 5);
imagefilledellipse($preview, 205, 205, 180, 180, rgba($preview, 168, 85, 247, 56));
imagefilledrectangle($preview, 420, 88, 730, 144, rgba($preview, 15, 23, 42, 20));
imagefilledrectangle($preview, 420, 176, 700, 225, rgba($preview, 15, 23, 42, 30));
imagefilledrectangle($preview, 420, 256, 760, 330, rgba($preview, 15, 23, 42, 42));
draw_neon_rect($preview, 405, 62, 770, 364, rgba($preview, 244, 114, 182, 30), 3);
save_png($preview, "$baseDir/preview.png");

echo "Generated Neon Arcade profile skin assets in {$baseDir}" . PHP_EOL;
