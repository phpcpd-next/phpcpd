<?php

declare(strict_types=1);

// Type-2 fixture (base). Structurally identical to rename_variant.php but every
// identifier, literal, and the function name differ. Raw-content matching cannot
// see the clone; token-type normalisation (--fuzzy) can.

function summarize(array $rows): array
{
    $total = 0;
    $count = 0;
    $peak  = 0;

    foreach ($rows as $row) {
        $value = (int) $row['value'];
        $total = $total + $value;
        $count = $count + 1;

        if ($value > $peak) {
            $peak = $value;
        }
    }

    $average           = $count > 0 ? $total / $count : 0;
    $result            = [];
    $result['total']   = $total;
    $result['count']   = $count;
    $result['peak']    = $peak;
    $result['average'] = $average;

    return $result;
}
