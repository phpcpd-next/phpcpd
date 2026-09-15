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

namespace LucianoPereira\PhpcpdNext\Presentation;

use function array_filter;
use function array_values;
use function count;

use LucianoPereira\PhpcpdNext\CodeCloneMap;

/**
 * A whole report's findings, in the order the report presents them, with the
 * counts a reader is owed.
 *
 * **The set is the map's set.** Every clone the detector produced is here,
 * exactly once. Nothing in the presentation tier filters, and
 * `PresentationTest` asserts the identity rather than trusting it — a tier that
 * could quietly drop a finding would be a suppression mechanism wearing a
 * report's clothes.
 *
 * That invariant is why `--min-confidence` is a property of this object rather
 * than a step before it. The threshold partitions the findings into
 * {@see visible()} and {@see hidden()}; it never removes one. The whole set is
 * still here, still counted, still stratified, and still gates the exit code —
 * a reader who asks for a quieter report gets a quieter report and a line
 * saying what it is quieter *by*, which is the same bargain
 * `--no-suppress`/`--suppressed` already strikes for the comment markers.
 *
 * The threshold is deliberately not a default. The M5 pre-commitment measured
 * that no rule derivable from the project's own labels reaches the 0.80 bar by
 * silencing, and that finding stands: it is an argument against the *tool*
 * choosing to hide, not against a reader choosing to. Unset, nothing is hidden
 * and every count below is what it always was.
 */
final readonly class Findings
{
    /**
     * @param CodeCloneMap  $clones   the map these findings present, carried so
     *                                every format still reaches the corpus-level
     *                                summary (duplicated lines, percentage) it
     *                                has always reported
     * @param list<Finding> $findings one per clone in the map, in report order
     * @param list<string>  $stale    ledger entries no finding matched, named by
     *                                the note they were written with — reported,
     *                                never dropped
     * @param bool          $ledger   was a ledger consulted at all? The counts
     *                                are printed whenever it was, zeroes included
     * @param ?float $minConfidence the log-odds below which a finding is
     *        rendered only on request. Null — the default — hides nothing.
     */
    public function __construct(
        public CodeCloneMap $clones,
        public array $findings,
        public array $stale = [],
        public bool $ledger = false,
        public ?float $minConfidence = null,
    ) {}

    /**
     * The findings a report renders: all of them, unless a threshold was asked
     * for.
     *
     * Every format goes through here rather than reading `$findings` directly,
     * so the console and the log files cannot disagree about what was shown —
     * the same reason one {@see \LucianoPereira\PhpcpdNext\Presentation\Presenter}
     * serves all four.
     *
     * @return list<Finding>
     */
    public function visible(): array
    {
        if ($this->minConfidence === null) {
            return $this->findings;
        }

        return array_values(array_filter(
            $this->findings,
            fn(Finding $finding): bool => $finding->confidence >= $this->minConfidence,
        ));
    }

    /**
     * The findings the threshold held back — never dropped, and listable with
     * `--hidden`.
     *
     * @return list<Finding>
     */
    public function hidden(): array
    {
        if ($this->minConfidence === null) {
            return [];
        }

        return array_values(array_filter(
            $this->findings,
            fn(Finding $finding): bool => $finding->confidence < $this->minConfidence,
        ));
    }

    /** How many findings the threshold held back. */
    public function hiddenCount(): int
    {
        return count($this->hidden());
    }

    /** How many findings this run's ledger acknowledged. */
    public function acknowledged(): int
    {
        $acknowledged = 0;

        foreach ($this->findings as $finding) {
            $acknowledged += $finding->acknowledged ? 1 : 0;
        }

        return $acknowledged;
    }

    public function count(): int
    {
        return count($this->findings);
    }

    public function demoted(): int
    {
        $demoted = 0;

        foreach ($this->findings as $finding) {
            $demoted += $finding->demoted() ? 1 : 0;
        }

        return $demoted;
    }

    public function asserted(): int
    {
        return $this->count() - $this->demoted();
    }

    /**
     * How many findings each demote stratum reached, in {@see Strata::NAMES}
     * order. A stratum that reached nothing still reports its zero: a count that
     * appears only when it is non-zero is a count a reader cannot calibrate.
     *
     * Acknowledgments are deliberately absent: the three strata are the M5
     * charter's pre-registered classes, and an acknowledgment is a decision a
     * project made rather than a class a finding belongs to. Mixing them would
     * make the stratum counts unreadable as evidence.
     *
     * @return array<string, int>
     */
    public function perStratum(): array
    {
        $counts = [];

        foreach (Strata::NAMES as $name) {
            $counts[$name] = 0;
        }

        foreach ($this->findings as $finding) {
            foreach ($finding->strata as $stratum) {
                $counts[$stratum] = ($counts[$stratum] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
