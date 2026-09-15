<?php

declare(strict_types=1);

namespace Demo\Shadow\Support;

/**
 * Negative 1 — a name no other file declares. Wired, unique, kept.
 */
final class Register
{
    public function open(Ledger $ledger): Ledger
    {
        $ledger->record(0);

        return $ledger;
    }
}
