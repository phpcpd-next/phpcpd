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
 * Ordinary program text with a real clone: not a table, not a registration file,
 * so it is ASSERTED. The stratification has to leave normal duplication alone or
 * it is not a lens, it is a filter.
 */


final class ServiceA
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
