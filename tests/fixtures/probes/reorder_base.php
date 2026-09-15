<?php

declare(strict_types=1);

// Probe 4 (base): a 24-token block of assignments then a 40-token block of guards, flanked by shared context.

function probe(int $seed, array $rows): int
{
    $c700 = $c700 + $seed;
    $c701 = $c701 + $seed;
    $c702 = $c702 + $seed;
    $c703 = $c703 + $seed;
    $c704 = $c704 + $seed;
    $c705 = $c705 + $seed;
    $c706 = $c706 + $seed;
    $c707 = $c707 + $seed;
    $c600 = $c600 + $seed;
    $c601 = $c601 + $seed;
    $c602 = $c602 + $seed;
    $c603 = $c603 + $seed;
    $c604 = $c604 + $seed;
    $c605 = $c605 + $seed;
    $c606 = $c606 + $seed;
    $c607 = $c607 + $seed;
    if ($seed > 0) { $g900 = $g900 + 1; }
    if ($seed > 0) { $g901 = $g901 + 1; }
    if ($seed > 0) { $g902 = $g902 + 1; }
    if ($seed > 0) { $g903 = $g903 + 1; }
    if ($seed > 0) { $g904 = $g904 + 1; }
    if ($seed > 0) { $g905 = $g905 + 1; }
    if ($seed > 0) { $g906 = $g906 + 1; }
    if ($seed > 0) { $g907 = $g907 + 1; }
    $c800 = $c800 + $seed;
    $c801 = $c801 + $seed;
    $c802 = $c802 + $seed;
    $c803 = $c803 + $seed;
    $c804 = $c804 + $seed;
    $c805 = $c805 + $seed;
    $c806 = $c806 + $seed;
    $c807 = $c807 + $seed;
    return $seed;
}
