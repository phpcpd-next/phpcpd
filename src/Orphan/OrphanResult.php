<?php

declare(strict_types=1);
/*
 * This file is part of PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LucianoPereira\PhpcpdNext\Orphan;

use function array_filter;
use function array_values;
use function count;

/**
 * The outcome of an orphan scan: the definite dead symbols, the possible ones,
 * and the totals needed for the summary line. Immutable and I/O-free, so both
 * the CLI reporter and an embedding tool read the same model — the same
 * separation of concerns the clone side keeps between CodeCloneMap and Log\Text.
 */
final readonly class OrphanResult
{
    /** @param list<Orphan> $orphans */
    public function __construct(
        private array $orphans,
        public int $filesScanned,
        public int $symbolsScanned,
    ) {}

    /** @return list<Orphan> every finding, definite and possible */
    public function all(): array
    {
        return $this->orphans;
    }

    /** @return list<Orphan> only the safe-to-delete findings */
    public function definite(): array
    {
        return array_values(array_filter(
            $this->orphans,
            static fn(Orphan $o): bool => $o->isDefinite(),
        ));
    }

    /** @return list<Orphan> only the review-me findings */
    public function possible(): array
    {
        return array_values(array_filter(
            $this->orphans,
            static fn(Orphan $o): bool => !$o->isDefinite(),
        ));
    }

    public function count(): int
    {
        return count($this->orphans);
    }

    public function hasDefiniteOrphans(): bool
    {
        return $this->definite() !== [];
    }

    public function isEmpty(): bool
    {
        return $this->orphans === [];
    }
}
