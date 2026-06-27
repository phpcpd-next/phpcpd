<?php

declare(strict_types=1);

// Type-3 fixture (base). Identical to clone_gapped.php except clone_gapped.php
// inserts ONE statement mid-body. Same function name to isolate the gap as the
// only difference. Never loaded — phpcpd only tokenises these files.

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
