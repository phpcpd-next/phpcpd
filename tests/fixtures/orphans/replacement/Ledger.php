<?php

declare(strict_types=1);

namespace Demo\Ledger;

// Invoice is live (constructed by Bootstrap). InvoiceLegacy is an unreferenced
// near-duplicate left behind by a refactor — the "superseded copy" case. They
// share a token-identical body, so the clone engine links them and the detector
// can say InvoiceLegacy is a stale copy of Invoice. Because this file also holds
// a live class, it is NOT reported as a wholly-unwired file.
final class Invoice
{
    public function summarise(int $cents, int $count): string
    {
        $total = 0;

        for ($i = 0; $i < $count; $i++) {
            $total = $total + $cents;
        }

        $average = $count > 0 ? $total / $count : 0;

        return 'total=' . $total . ' average=' . $average . ' count=' . $count;
    }
}

final class InvoiceLegacy
{
    public function summarise(int $cents, int $count): string
    {
        $total = 0;

        for ($i = 0; $i < $count; $i++) {
            $total = $total + $cents;
        }

        $average = $count > 0 ? $total / $count : 0;

        return 'total=' . $total . ' average=' . $average . ' count=' . $count;
    }
}
