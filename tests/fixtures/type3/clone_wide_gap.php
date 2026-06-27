<?php

declare(strict_types=1);

// Type-3 fixture (wider gap). Identical to clone_base.php except for the TWO
// inserted statements marked below. A wider gap costs more edits to bridge, so
// it requires a larger --edit-distance budget than the single-statement gap in
// clone_gapped.php — this is the recall-vs-edit-distance relationship.

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
    $report['count']   = count($entries);               // <-- inserted statement 1 (the wider gap)
    $report['ratio']   = $credits > 0 ? $debits : 0;    // <-- inserted statement 2
    $report['balance'] = $balance;
    $report['largest'] = $largest;
    $report['net']     = $credits - $debits;

    return $report;
}
