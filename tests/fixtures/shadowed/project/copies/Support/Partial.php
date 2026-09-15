<?php

declare(strict_types=1);

namespace Demo\Shadow\Support;

/**
 * Negative 3 — an unmapped file that declares one shadowed name AND one of its
 * own. `Demo\Shadow\Support\Escrow` is declared nowhere else, so this file holds
 * code that would be lost with it and the rung's "all its declared symbols" is
 * not satisfied. Kept.
 */
final class Ledger
{
    public function total(): int
    {
        return 0;
    }
}

final class Escrow
{
    public function hold(int $amount): int
    {
        return $amount;
    }
}
