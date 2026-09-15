<?php

declare(strict_types=1);
/*
 * This file is part of PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * The second half of the asserted pair.
 */


final class ServiceB
{
    public function totals(array $rows): array
    {
        $sum = 0;
        $count = 0;
        $largest = 0;
        $smallest = 0;
        $taxed = 0;
        $refunded = 0;

        foreach ($rows as $row) {
            $sum += $row['amount'];
            $count++;

            if ($row['amount'] > $largest) {
                $largest = $row['amount'];
            }

            if ($row['amount'] < $smallest) {
                $smallest = $row['amount'];
            }

            if ($row['taxable']) {
                $taxed += $row['amount'];
            }

            if ($row['refunded']) {
                $refunded += $row['amount'];
            }
        }

        if ($count === 0) {
            return ['sum' => 0, 'average' => 0, 'largest' => 0, 'smallest' => 0];
        }

        return [
            'sum' => $sum,
            'average' => $sum / $count,
            'largest' => $largest,
            'smallest' => $smallest,
            'taxed' => $taxed,
            'refunded' => $refunded,
        ];
    }
}
