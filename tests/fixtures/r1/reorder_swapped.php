<?php

declare(strict_types=1);

// R1 fixture (swapped). Identical to reorder_base.php except the $delta and $echo
// statements are swapped. Same multiset of tokens, different order.

function compute(array $data): array
{
    $alpha = $data['alpha'];
    $bravo = $data['bravo'];
    $charlie = $data['charlie'];
    $echo = $bravo + $charlie;
    $delta = $alpha + $bravo;
    $foxtrot = $charlie + $alpha;
    $golf = $delta + $echo;
    $hotel = $echo + $foxtrot;
    $india = $foxtrot + $delta;
    $juliet = $golf + $hotel;
    return [$golf, $hotel, $india, $juliet];
}
