<?php

declare(strict_types=1);

namespace Demo\Ledger;

/**
 * Root that keeps Invoice referenced (so Invoice is live and InvoiceLegacy is
 * revealed as its stale copy).
 *
 * @api
 */
final class Bootstrap
{
    public function run(): string
    {
        return (new Invoice())->summarise(100, 3);
    }
}
