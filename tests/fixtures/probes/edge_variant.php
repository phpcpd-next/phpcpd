<?php

declare(strict_types=1);

// Probe 1 (variant): a structurally different first and last statement — a foreach where the base has an if — around the same exact core.

function probe(int $seed, array $rows): int
{
    foreach ($rows as $row) { $l1 = $row; }
    $c100 = $c100 + $seed;
    $c101 = $c101 + $seed;
    $c102 = $c102 + $seed;
    $c103 = $c103 + $seed;
    $c104 = $c104 + $seed;
    $c105 = $c105 + $seed;
    $c106 = $c106 + $seed;
    $c107 = $c107 + $seed;
    $c108 = $c108 + $seed;
    $c109 = $c109 + $seed;
    $c110 = $c110 + $seed;
    $c111 = $c111 + $seed;
    $c112 = $c112 + $seed;
    $c113 = $c113 + $seed;
    $c114 = $c114 + $seed;
    $c115 = $c115 + $seed;
    $c116 = $c116 + $seed;
    $c117 = $c117 + $seed;
    $c118 = $c118 + $seed;
    $c119 = $c119 + $seed;
    foreach ($rows as $row) { $l2 = $row; }
    return $seed;
}
