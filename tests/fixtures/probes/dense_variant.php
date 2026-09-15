<?php

declare(strict_types=1);

// Probe 3 (variant): an edit every ~12 tokens — denser than the 16-token seed, so most runs cannot carry one.

function probe(int $seed, array $rows): int
{
    $c500 = $c500 + $seed;
    $c501 = $c501 + $seed;
    $c502 = $c502 + $seed;
    if ($seed > 0) { $g503 = $g503 + 1; }
    $c504 = $c504 + $seed;
    $c505 = $c505 + $seed;
    $c506 = $c506 + $seed;
    if ($seed > 0) { $g507 = $g507 + 1; }
    $c508 = $c508 + $seed;
    $c509 = $c509 + $seed;
    $c510 = $c510 + $seed;
    if ($seed > 0) { $g511 = $g511 + 1; }
    $c512 = $c512 + $seed;
    $c513 = $c513 + $seed;
    $c514 = $c514 + $seed;
    if ($seed > 0) { $g515 = $g515 + 1; }
    $c516 = $c516 + $seed;
    $c517 = $c517 + $seed;
    $c518 = $c518 + $seed;
    if ($seed > 0) { $g519 = $g519 + 1; }
    $c520 = $c520 + $seed;
    $c521 = $c521 + $seed;
    $c522 = $c522 + $seed;
    if ($seed > 0) { $g523 = $g523 + 1; }
    return $seed;
}
