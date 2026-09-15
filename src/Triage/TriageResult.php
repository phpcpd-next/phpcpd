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

namespace LucianoPereira\PhpcpdNext\Triage;

use function count;

/**
 * What Stage 0 decided about a file set: what is program text, and what was
 * removed with the reason for each removal.
 *
 * Immutable and I/O-free, like {@see \LucianoPereira\PhpcpdNext\Orphan\OrphanResult}
 * next to it — the caller decides whether to print the discards, gate on them,
 * or ignore them.
 */
final readonly class TriageResult
{
    /**
     * @param list<string>          $kept      program text, in the caller's order
     * @param list<TriageDecision>  $discarded everything removed, and why
     */
    public function __construct(
        public array $kept,
        public array $discarded,
    ) {}

    /** @return list<TriageDecision> the discards of one reason */
    public function because(string $reason): array
    {
        $matching = [];

        foreach ($this->discarded as $decision) {
            if ($decision->reason === $reason) {
                $matching[] = $decision;
            }
        }

        return $matching;
    }

    /** @return array<string, int> reason => how many files it removed */
    public function counts(): array
    {
        $counts = [
            TriageDecision::DERIVED  => 0,
            TriageDecision::UNWIRED  => 0,
            TriageDecision::SHADOWED => 0,
            TriageDecision::FOREIGN  => 0,
        ];

        foreach ($this->discarded as $decision) {
            $counts[$decision->reason] = ($counts[$decision->reason] ?? 0) + 1;
        }

        return $counts;
    }

    public function discardedCount(): int
    {
        return count($this->discarded);
    }
}
