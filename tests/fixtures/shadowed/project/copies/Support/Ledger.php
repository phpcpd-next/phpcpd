<?php

declare(strict_types=1);

namespace Demo\Shadow\Support;

/**
 * The positive case. It declares exactly the same name as src/Support/Ledger.php
 * and sits under no mapped directory, so the autoloader has no route to it.
 * Nothing about the directory's name says so — the manifest does.
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
