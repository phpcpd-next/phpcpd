<?php
declare(strict_types=1);
function compute(int $x, int $y): int {
    $a = $x;
    $b = $a + $y;
    $c = $b + $x;
    $d = $c + $y;
    $e = $d + $a;
    $f = $e + $b;
    return $f;
}
