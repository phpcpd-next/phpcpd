<?php

declare(strict_types=1);

// Type-2 fixture (variant). Same structure as rename_base.php with every
// identifier, literal, and the function name renamed. Under token-type
// normalisation the two collapse to identical token streams.

function aggregate(array $items): array
{
    $sum     = 0;
    $number  = 0;
    $maximum = 0;

    foreach ($items as $item) {
        $amount = (int) $item['amount'];
        $sum    = $sum + $amount;
        $number = $number + 1;

        if ($amount > $maximum) {
            $maximum = $amount;
        }
    }

    $mean              = $number > 0 ? $sum / $number : 0;
    $output            = [];
    $output['sum']     = $sum;
    $output['number']  = $number;
    $output['maximum'] = $maximum;
    $output['mean']    = $mean;

    return $output;
}
