<?php

declare(strict_types=1);

// Probe 1 (base): the clone diverges at its first and last statement. Neither divergence has agreeing material beyond it to anchor against.

function probe(int $seed, array $rows): int
{
    if ($seed > 0) { $g1 = $g1 + 1; }
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
    if ($seed > 0) { $g2 = $g2 + 1; }
    return $seed;
}
