<?php

declare(strict_types=1);

// R1 fixture (base). reorder_swapped.php is this exact function with two adjacent
// statements swapped: identical token bag, different order. The token-bag engine
// detects it; the contiguous matchers (rabin-karp, suffixtree) do not.

function compute(array $data): array
{
    $alpha = $data['alpha'];
    $bravo = $data['bravo'];
    $charlie = $data['charlie'];
    $delta = $alpha + $bravo;
    $echo = $bravo + $charlie;
    $foxtrot = $charlie + $alpha;
    $golf = $delta + $echo;
    $hotel = $echo + $foxtrot;
    $india = $foxtrot + $delta;
    $juliet = $golf + $hotel;
    return [$golf, $hotel, $india, $juliet];
}
