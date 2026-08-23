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
 * The outcome of an orphan scan, split into four tiers: dead and possible are
 * FINDINGS that need a decision; suppressed and planned are symbols already
 * accounted for, kept in the result so a rule that misfires stays visible and
 * countable instead of silently deleting a real finding from the report.
 *
 * all() / definite() / possible() / count() / isEmpty() speak only about
 * findings, so a scan that suppresses everything still reads as "no orphans".
 *
 * Immutable and I/O-free, so both the CLI reporter and an embedding tool read
 * the same model — the same separation of concerns the clone side keeps between
 * CodeCloneMap and Log\Text.
 */
final readonly class OrphanResult
{
    /** @param list<Orphan> $entries every classification, all four tiers */
    public function __construct(
        private array $entries,
        public int $filesScanned,
        public int $symbolsScanned,
    ) {}

    /** @return list<Orphan> every finding, definite and possible */
    public function all(): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn(Orphan $o): bool => $o->isFinding(),
        ));
    }

    /** @return list<Orphan> every entry, findings and accounted-for alike */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return list<Orphan> symbols a rule accounts for; reported, never gating by default */
    public function suppressed(): array
    {
        return $this->tier(Orphan::CONFIDENCE_SUPPRESSED);
    }

    /** @return list<Orphan> symbols declared deliberately unwired via @phpcpd-planned */
    public function planned(): array
    {
        return $this->tier(Orphan::CONFIDENCE_PLANNED);
    }

    /** @return list<Orphan> */
    public function tier(string $confidence): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn(Orphan $o): bool => $o->confidence === $confidence,
        ));
    }

    /** @return list<Orphan> only the safe-to-delete findings */
    public function definite(): array
    {
        return $this->tier(Orphan::CONFIDENCE_DEAD);
    }

    /** @return list<Orphan> only the review-me findings */
    public function possible(): array
    {
        return $this->tier(Orphan::CONFIDENCE_POSSIBLE);
    }

    public function count(): int
    {
        return count($this->all());
    }

    public function hasDefiniteOrphans(): bool
    {
        return $this->definite() !== [];
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    /**
     * Does this run fail its gate? Only the tiers named in --fail-on count, which
     * is 'dead' alone unless the user widened it.
     */
    public function fails(OrphanConfiguration $config): bool
    {
        foreach ([Orphan::CONFIDENCE_DEAD, Orphan::CONFIDENCE_POSSIBLE, Orphan::CONFIDENCE_SUPPRESSED, Orphan::CONFIDENCE_PLANNED] as $tier) {
            if ($config->gatesOn($tier) && $this->tier($tier) !== []) {
                return true;
            }
        }

        return false;
    }
}
