<?php

declare(strict_types=1);

// Type-3 fixture (gapped). Identical to clone_base.php except for the single
// inserted statement marked below. This one inserted statement is the "gap"
// that defeats Rabin-Karp exact windows but is bridged by the suffix tree's
// edit-distance budget.

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
    $report['count']   = count($entries); // <-- the single inserted statement (the gap)
    $report['balance'] = $balance;
    $report['largest'] = $largest;
    $report['net']     = $credits - $debits;

    return $report;
}
