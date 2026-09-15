<?php

declare(strict_types=1);

// Probe 5 (variant, bounded-edge-divergence positive): the same shared core,
// then this copy keeps going with a block of guards the base never had —
// CurrencyController gaining an endpoint TagController never received is the
// real-world shape this stands in for.

function probe(int $seed, array $rows): int
{
    $c900 = $c900 + $seed;
    $c901 = $c901 + $seed;
    $c902 = $c902 + $seed;
    $c903 = $c903 + $seed;
    $c904 = $c904 + $seed;
    $c905 = $c905 + $seed;
    $c906 = $c906 + $seed;
    $c907 = $c907 + $seed;
    $c908 = $c908 + $seed;
    $c909 = $c909 + $seed;
    $c910 = $c910 + $seed;
    $c911 = $c911 + $seed;
    $c912 = $c912 + $seed;
    $c913 = $c913 + $seed;
    if ($seed > 0) { $g950 = $g950 + 1; }
    if ($seed > 0) { $g951 = $g951 + 1; }
    if ($seed > 0) { $g952 = $g952 + 1; }
    if ($seed > 0) { $g953 = $g953 + 1; }
    if ($seed > 0) { $g954 = $g954 + 1; }
    if ($seed > 0) { $g955 = $g955 + 1; }
    return $seed;
}
