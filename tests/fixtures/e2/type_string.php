<?php
declare(strict_types=1);
function compute(string $value, string $factor): string {
    $base   = $value;
    $scaled = $base + $factor;
    $result = $scaled + $value;
    $out    = $result + $factor;
    $sum    = $out + $base;
    $fin    = $sum + $scaled;
    return $fin;
}
