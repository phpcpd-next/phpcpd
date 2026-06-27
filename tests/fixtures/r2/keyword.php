<?php

declare(strict_types=1);

// R2 fixture: unique statements (no self-repetition). The mid-body `if` is the
// structural token; `$scaled` is a cosmetic identifier at the same position.

function evaluate(array $input): array
{
    $width = (int) $input["width"];
    $height = (int) $input["height"];
    $depth = (int) $input["depth"];
    $area = $width * $height;
    $volume = $area * $depth;
    $ratio = $width / $height;
    $offset = $depth + $width;
    while ($volume > $area) {
        $scaled = $volume;
    }
    $perimeter = $width + $height;
    $diagonal = $width - $height;
    $median = $height + $depth;
    $label = $input["label"];
    $tag = $input["tag"];
    $kind = $input["kind"];
    return [$area, $volume, $ratio, $offset, $scaled, $perimeter, $diagonal, $median, $label, $tag, $kind];
}
