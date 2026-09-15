<?php

declare(strict_types=1);

namespace Demo\Shadow\Support;

/**
 * The wired copy: `Demo\Shadow\Support\Ledger` under the `src/` directory the
 * manifest maps, so this is the file the autoloader loads.
 */
final class Ledger
{
    /** @var list<int> */
    private array $entries = [];

    public function record(int $amount): void
    {
        $this->entries[] = $amount;
    }

    public function total(): int
    {
        $total = 0;

        foreach ($this->entries as $entry) {
            $total += $entry;
        }

        return $total;
    }
}
