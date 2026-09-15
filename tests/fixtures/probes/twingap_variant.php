<?php

declare(strict_types=1);

// Probe 2 (variant): one statement inserted before that 12-token middle run and one after it. The middle is shorter than S, so no seed can land in it.

function probe(int $seed, array $rows): int
{
    $c200 = $c200 + $seed;
    $c201 = $c201 + $seed;
    $c202 = $c202 + $seed;
    $c203 = $c203 + $seed;
    $c204 = $c204 + $seed;
    $c205 = $c205 + $seed;
    $c206 = $c206 + $seed;
    $c207 = $c207 + $seed;
    $c208 = $c208 + $seed;
    $c209 = $c209 + $seed;
    if ($seed > 0) { $g11 = $g11 + 1; }
    $c300 = $c300 + $seed;
    $c301 = $c301 + $seed;
    $c302 = $c302 + $seed;
    $c303 = $c303 + $seed;
    if ($seed > 0) { $g12 = $g12 + 1; }
    $c400 = $c400 + $seed;
    $c401 = $c401 + $seed;
    $c402 = $c402 + $seed;
    $c403 = $c403 + $seed;
    $c404 = $c404 + $seed;
    $c405 = $c405 + $seed;
    $c406 = $c406 + $seed;
    $c407 = $c407 + $seed;
    $c408 = $c408 + $seed;
    $c409 = $c409 + $seed;
    return $seed;
}
