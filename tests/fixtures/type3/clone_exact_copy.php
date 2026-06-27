<?php

declare(strict_types=1);

// Exact (Type-1) copy of clone_base.php — byte-identical body, same function name.
// Engine B finds this with zero edits, so the clone is NOT gapped (isGapped() === false).

function processLedger(array $entries): array
{
    $debits  = 0;
    $credits = 0;
    $balance = 0;
    $largest = 0;

    foreach ($entries as $entry) {
        $amount = (int) $entry['amount'];
        $type   = (string) $entry['type'];

        if ($type === 'debit') {
            $debits  += $amount;
            $balance -= $amount;
        } else {
            $credits += $amount;
            $balance += $amount;
        }

        if ($amount > $largest) {
            $largest = $amount;
        }
    }

    $report            = [];
    $report['debits']  = $debits;
    $report['credits'] = $credits;
    $report['balance'] = $balance;
    $report['largest'] = $largest;
    $report['net']     = $credits - $debits;

    return $report;
}
