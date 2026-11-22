<?php

$directory = dirname(__DIR__).'/public/icons';
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}

foreach (['icon-192' => 192, 'icon-512' => 512, 'maskable-512' => 512, 'apple-touch-icon' => 180] as $name => $size) {
    $canvas = imagecreatetruecolor($size, $size);
    $background = imagecolorallocate($canvas, 22, 18, 22);
    $tile = imagecolorallocate($canvas, 53, 36, 36);
    $edge = imagecolorallocate($canvas, 116, 72, 61);
    $mark = imagecolorallocate($canvas, 255, 158, 124);
    $center = intdiv($size, 2);
    imagefill($canvas, 0, 0, $background);
    imagefilledellipse($canvas, $center, $center, (int) ($size * .73), (int) ($size * .73), $edge);
    imagefilledellipse($canvas, $center, $center, (int) ($size * .71), (int) ($size * .71), $tile);
    imagesetthickness($canvas, max(5, (int) ($size * .032)));
    $length = $size * .23;
    foreach ([0, M_PI / 4, M_PI / 2, M_PI * 3 / 4] as $angle) {
        $x = (int) round($length * cos($angle));
        $y = (int) round($length * sin($angle));
        imageline($canvas, $center - $x, $center - $y, $center + $x, $center + $y, $mark);
    }
    imagepng($canvas, $directory.'/'.$name.'.png', 9);
}
