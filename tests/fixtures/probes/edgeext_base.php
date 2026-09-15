<?php

declare(strict_types=1);

// Probe 5 (base, bounded-edge-divergence positive): the shared core ends the
// function immediately — nothing trails it in this copy.

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
    return $seed;
}
