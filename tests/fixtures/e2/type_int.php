<?php
declare(strict_types=1);
function compute(int $value, int $factor): int {
    $base   = $value;
    $scaled = $base + $factor;
    $result = $scaled + $value;
    $out    = $result + $factor;
    $sum    = $out + $base;
    $fin    = $sum + $scaled;
    return $fin;
}
