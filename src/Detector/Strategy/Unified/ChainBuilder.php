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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy\Unified;

use function array_fill;
use function array_flip;
use function array_keys;
use function array_reverse;
use function count;
use function sort;
use function usort;

use const PHP_INT_MIN;

/**
 * Stage D2: which anchors of a file pair belong to one clone.
 *
 * Stage C hands over a scatter of exact matches between two files. A clone whose
 * copies diverged in the middle appears as two or three of them on nearly the
 * same diagonal with gaps between; unrelated coincidences appear as anchors that
 * go backwards, or that sit far off the diagonal. Chaining separates the two:
 * find the highest-scoring set of anchors that increases in *both* files, and
 * that set is the clone, with its gaps being exactly where the copies diverged.
 *
 * Colinear chaining by sparse dynamic programming (Eppstein, Galil, Giancarlo &
 * Italiano, JACM 1992; textbook treatment in Ohlebusch, *Bioinformatics
 * Algorithms*, the chaining chapter), taken from the published description.
 *
 * ## Why a Fenwick tree rather than the obvious double loop
 *
 * The recurrence asks, for each anchor, for the best chain among all anchors
 * ending before it in both files — a two-dimensional dominance query. Answering
 * it by scanning every earlier anchor is O(m²), which is fine until one file pair
 * carries thousands of anchors, and a generated parser or a table of near-
 * identical test methods produces exactly that. Sweeping in A while admitting
 * anchors to the tree only once they have ended satisfies the first dimension by
 * construction, leaving a prefix maximum over the second, which a Fenwick tree
 * answers in O(log m). The whole pass is O(m log m).
 *
 * ## The two weightings, and why they are the same code
 *
 * The classifier needs two different questions answered about one anchor set:
 *
 *   - **with gap penalty** — what is the best chain? This is the candidate, and
 *     its gaps are the divergences a Type-3 report names.
 *   - **without gap penalty** — how many tokens can any colinear subset cover?
 *     This is the longest-increasing-subsequence measure of the reorder test
 *     (Fredman 1975), weighted by anchor length rather than counting anchors.
 *
 * They differ only in what the tree stores, so they are one method with a flag.
 * A clone whose statements were shuffled has high total anchor coverage and low
 * colinear coverage, and that gap between the two numbers *is* the reorder
 * signal — which is why both have to come from the same anchors, judged twice.
 *
 * ## The gap penalty
 *
 * A junction costs the tokens it skips: `gapA + gapB`, in the same unit as the
 * coverage it is subtracted from. There is deliberately no separate gap-opening
 * constant — a junction already pays for itself through the tokens it steps
 * over, and an opening cost would be a constant with no derivation behind it.
 * The score only *ranks* candidates in any case: whether a chain is reported is
 * decided by {@see BandedAligner}, on a real edit distance, not here.
 *
 * ## Equal scores, and why coverage settles them
 *
 * The score is a partial answer: two different chains through one anchor set can
 * reach exactly the same number, and the recurrence then has to pick one. It used
 * to pick whichever the sweep reached first, which is an iteration-order accident
 * dressed up as a rule — and it was measurably wrong. A trailing run recovered at
 * a flank gains as much coverage as the junction to it costs, so it ties exactly
 * with the chain that leaves it out, and leaving it out shortened the reported
 * span below `--min-tokens` on a clone whose copies are 96 % identical.
 *
 * Between two equally-scored readings the one covering more matched tokens is
 * taken. This is a **completion of the order, not a new criterion**: score stays
 * strictly primary, so no chain that scores lower is ever preferred, and coverage
 * is a quantity the recurrence already carries for the reorder test. The pair
 * (score, coverage) is compared lexicographically at all three places the
 * recurrence chooses — which predecessor to extend, whether to extend one at all,
 * and which chain to return — and a residual tie goes to the lower anchor index,
 * so the answer is a function of the anchor set and of nothing else. Recorded at
 * the M3 audit as ruling M(b); `bench/check-chaining.php` re-runs the brute-force
 * oracle over the same tie order.
 *
 * Optimality survives the refinement, and by the same exchange argument as before:
 * a chain's total is `length + score(predecessor) − gapA − gapB` and its coverage
 * is `length + coverage(predecessor)`, both increasing in the predecessor's own
 * pair, so a lexicographically best chain ending at an anchor extends a
 * lexicographically best chain ending at its predecessor.
 */
final readonly class ChainBuilder
{
    /**
     * The best chain through a set of anchors.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors posA, posB, length
     * @param bool $penalizeGaps subtract skipped tokens at each junction
     * @return array{indices: list<int>, chain: list<array{0: int, 1: int, 2: int}>, covered: int, score: int}
     *         indices into $anchors, ascending in both files; `chain` is the
     *         geometry actually used, which is the anchor as given or a
     *         start-trimmed copy of it — see {@see withTrimmedVariants()}
     */
    public function chain(array $anchors, bool $penalizeGaps = true): array
    {
        // Anchors are extended maximally and independently of one another, so
        // the two flanking a divergence routinely overlap by a token or two on
        // one side: the right flank's leftward extension runs back past the
        // point where the left flank stopped, over material that happens to
        // agree there as well. The recurrence below requires a predecessor to
        // end before its successor starts on *both* sides, so such a junction
        // is not merely penalized, it is unavailable — and a gapped clone
        // whose two flanks overlap by one token is reported as whichever flank
        // is longer, or not at all when neither reaches `minTokens` alone.
        //
        // The overlapping tokens are real evidence; only counting them twice
        // would be wrong. So the anchor set is augmented, not rewritten: each
        // anchor keeps its own geometry and gains a copy whose start is
        // advanced past everything a legal predecessor could already claim.
        // The recurrence then chooses between them on score, which is why this
        // can only find chains the previous set could not and never loses one
        // it could.
        [$work, $origin] = self::withTrimmedVariants($anchors);

        $count = count($work);

        if ($count === 0) {
            return ['indices' => [], 'chain' => [], 'covered' => 0, 'score' => 0];
        }

        $anchors = $work;

        // Two orders, and they have to be different ones. Anchors are *scored*
        // in order of where they start in A, because that is the order in which
        // a predecessor is always finished before the anchor that might use it
        // (a legal predecessor ends before this anchor starts, so it also starts
        // earlier). They are *inserted* into the tree in order of where they end
        // in A, because that is the order the eligibility test advances in.
        // Sorting by one and sweeping as if it were the other lets an anchor
        // that ends too late become a candidate predecessor.
        //
        // Both comparisons fall through to every coordinate, so neither order —
        // and therefore neither the chain nor its score — depends on how the
        // anchors happened to arrive.
        $byStart = self::sortedByStart($anchors);
        $byEnd   = self::sortedByEnd($anchors);

        // Fenwick trees index from 1, so compress every B coordinate the sweep
        // can query or insert into a dense rank.
        $coordinates = [];

        foreach ($anchors as [$posA, $posB, $length]) {
            $coordinates[$posB]           = true;
            $coordinates[$posB + $length] = true;
        }

        $ranks = array_keys($coordinates);
        sort($ranks);
        $rankOf = array_flip($ranks);
        $size   = count($ranks);

        /** @var array<int, int> $treeValue best score in each Fenwick node */
        $treeValue = array_fill(0, $size + 1, PHP_INT_MIN);
        /** @var array<int, int> $treeCovered coverage of the chain that achieved it */
        $treeCovered = array_fill(0, $size + 1, PHP_INT_MIN);
        /** @var array<int, int> $treeIndex which anchor achieved it */
        $treeIndex = array_fill(0, $size + 1, -1);

        /** @var array<int, int> $score */
        $score = [];
        /** @var array<int, int> $covered */
        $covered = [];
        /** @var array<int, int> $predecessor */
        $predecessor = [];

        $bestScore   = PHP_INT_MIN;
        $bestCovered = PHP_INT_MIN;
        $bestEnd     = -1;
        $inserted    = 0;

        foreach ($byStart as $index) {
            [$posA, $posB, $length] = $anchors[$index];

            // Insert every anchor that now ends at or before this one's start in
            // A. Both sequences advance monotonically, so each anchor is
            // inserted exactly once across the whole sweep.
            while ($inserted < $count) {
                $candidate           = $byEnd[$inserted];
                [$cA, $cB, $cLength] = $anchors[$candidate];

                if ($cA + $cLength > $posA) {
                    break;
                }

                $inserted++;

                // A trimmed variant that never found a predecessor is not a
                // reading of anything — it is an anchor needlessly shortened —
                // so it must not become one for something else either.
                if ($score[$candidate] === PHP_INT_MIN) {
                    continue;
                }

                $key = $penalizeGaps
                    ? $score[$candidate] + ($cA + $cLength) + ($cB + $cLength)
                    : $covered[$candidate];

                self::insert(
                    $treeValue,
                    $treeCovered,
                    $treeIndex,
                    $rankOf[$cB + $cLength] + 1,
                    $key,
                    $covered[$candidate],
                    $candidate,
                );
            }

            // Best predecessor ending at or before this anchor's start in B —
            // best by score, and among equal scores by the coverage it carries.
            [$bestKey, $predecessorCovered, $bestPredecessor] = self::query(
                $treeValue,
                $treeCovered,
                $treeIndex,
                $rankOf[$posB] + 1,
            );

            $fromScratch = $length;
            $joined      = null;
            $joinedCover = 0;

            if ($bestPredecessor !== -1) {
                $joined = $penalizeGaps
                    ? $length + $bestKey - $posA - $posB
                    : $length + $bestKey;

                $joinedCover = $predecessorCovered + $length;
            }

            // Extend when extending scores higher, and on an exact tie when it
            // covers more: a junction that pays for itself exactly is still
            // evidence, and dropping it was an iteration-order accident.
            $extend = $joined !== null
                && ($joined > $fromScratch || ($joined === $fromScratch && $joinedCover > $length));

            if ($extend) {
                $score[$index]       = $joined;
                $covered[$index]     = $joinedCover;
                $predecessor[$index] = $bestPredecessor;
            } elseif ($origin[$index] !== $index) {
                // A trimmed variant exists only to make a junction available
                // that the anchor it came from cannot reach. Starting a chain
                // on one would report a span shortened at its own outer edge
                // for no gain, which on phpunit's `NoticeTriggered` family cuts
                // 137 shared tokens to 129. Standing alone it is ruled out, and
                // the anchor it was trimmed from is in the set to be chosen
                // instead.
                $score[$index]       = PHP_INT_MIN;
                $covered[$index]     = 0;
                $predecessor[$index] = -1;

                continue;
            } else {
                $score[$index]       = $fromScratch;
                $covered[$index]     = $length;
                $predecessor[$index] = -1;
            }

            if (
                $score[$index] > $bestScore
                || ($score[$index] === $bestScore && $covered[$index] > $bestCovered)
            ) {
                $bestScore   = $score[$index];
                $bestCovered = $covered[$index];
                $bestEnd     = $index;
            }
        }

        if ($bestEnd === -1) {
            return ['indices' => [], 'chain' => [], 'covered' => 0, 'score' => 0];
        }

        $indices = [];
        $chain   = [];

        // `>= 0` rather than `!== -1`: both stop at the same place, and only
        // this one tells a reader — and a static analyser — that what is about
        // to be used as an offset is one.
        for ($at = $bestEnd; $at >= 0; $at = $predecessor[$at]) {
            $indices[] = $origin[$at];
            $chain[]   = $anchors[$at];
        }

        return [
            'indices' => array_reverse($indices),
            'chain'   => array_reverse($chain),
            'covered' => $covered[$bestEnd],
            'score'   => $bestScore,
        ];
    }

    /**
     * Anchor indices in the order the recurrence *scores* them: by where they
     * start in A, because that is the order in which a predecessor is always
     * finished before the anchor that might use it — a legal predecessor ends
     * before this anchor starts, so it also starts earlier.
     *
     * This and {@see sortedByEnd()} both fall through to every coordinate, so
     * neither order — and therefore neither the chain nor its score — depends
     * on how the anchors happened to arrive.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     * @return list<int>
     */
    private static function sortedByStart(array $anchors): array
    {
        return self::sortedBy(
            $anchors,
            static fn (array $a): array => [$a[0], $a[0] + $a[2], $a[1], $a[1] + $a[2]],
        );
    }

    /**
     * Anchor indices in the order they become *available* as predecessors: by
     * where they end in A, which is the order the eligibility test advances in.
     *
     * Sweeping one order while sorted by the other is what lets an anchor that
     * ends too late become a candidate predecessor.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     * @return list<int>
     */
    private static function sortedByEnd(array $anchors): array
    {
        return self::sortedBy(
            $anchors,
            static fn (array $a): array => [$a[0] + $a[2], $a[1] + $a[2], $a[0], $a[1]],
        );
    }

    /**
     * Anchor indices ordered by $key, which reads one anchor as the tuple that
     * decides its place.
     *
     * Every key below names all four coordinates, so the comparison never falls
     * through to the arrival order and the result is a total order the caller
     * cannot influence — the property both orderings above depend on.
     *
     * @param list<array{0: int, 1: int, 2: int}>                    $anchors
     * @param callable(array{0: int, 1: int, 2: int}): list<int>     $key
     * @return list<int>
     */
    private static function sortedBy(array $anchors, callable $key): array
    {
        $order = array_keys($anchors);

        usort($order, static fn (int $x, int $y): int => $key($anchors[$x]) <=> $key($anchors[$y]));

        return $order;
    }

    /**
     * The anchors, plus a start-trimmed copy of each one that a legal
     * predecessor overlaps.
     *
     * A predecessor is an anchor ending at or before this one's start in A, so
     * the A side can never overlap by construction and only B has to be
     * considered. The trim advances *both* starts by the same amount, because
     * position `t` of an anchor pairs A's `posA + t` with B's `posB + t` and
     * moving one side alone would break that correspondence — the copy sits on
     * the same diagonal as the original, starting later and shorter.
     *
     * The amount is the furthest into B that any legal predecessor reaches,
     * taken as a running maximum over the same ascending-A sweep the recurrence
     * uses. That is the strongest trim any single predecessor could force, and
     * a weaker one is never needed: the copy's whole purpose is to give the
     * recurrence a version of this anchor that no predecessor overlaps, and the
     * original is still there for every reading where none does.
     *
     * A copy that would be trimmed away entirely is not added, and neither is
     * one for an anchor no predecessor reaches — in both cases there is nothing
     * for the recurrence to choose between.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     * @return array{0: list<array{0: int, 1: int, 2: int}>, 1: list<int>}
     *         the working set, and each entry's index in $anchors
     */
    private static function withTrimmedVariants(array $anchors): array
    {
        // The recurrence's own two orders, not near-copies of them. This sweep
        // decides what each anchor's predecessors can already claim and the
        // recurrence then decides which of them to use, so two sweeps that must
        // agree about which anchors have ended cannot sort ties differently.
        $order = self::sortedByStart($anchors);
        $byEnd = self::sortedByEnd($anchors);

        $work   = $anchors;
        $origin = array_keys($anchors);

        $reach    = PHP_INT_MIN;
        $eligible = 0;
        $count    = count($anchors);

        foreach ($order as $index) {
            [$posA, $posB, $length] = $anchors[$index];

            while ($eligible < $count) {
                [$otherA, $otherB, $otherLength] = $anchors[$byEnd[$eligible]];

                if ($otherA + $otherLength > $posA) {
                    break;
                }

                $reach = max($reach, $otherB + $otherLength);
                $eligible++;
            }

            $shift = $reach - $posB;

            if ($shift <= 0 || $shift >= $length) {
                continue;
            }

            $work[]   = [$posA + $shift, $posB + $shift, $length - $shift];
            $origin[] = $index;
        }

        return [$work, $origin];
    }

    /**
     * Tokens covered by a set of anchors that pairs each side off exactly once.
     *
     * This is what the reorder test needs and what {@see coverage()} cannot
     * give it. Reordering is a claim that the *same* material appears on both
     * sides in a different arrangement, and "the same material" is a bijection:
     * a token of A is the copy of one token of B, not of nine. Measuring only
     * the A side (which is what a plain coverage does, and what this engine
     * used to do) asks a much weaker question — "is each token of A matched
     * *somewhere*?" — and in a file built from many near-identical blocks the
     * answer is yes for every token, to every other block, no matter how the
     * two reported spans are drawn. Coverage then reaches THETA by arithmetic
     * rather than by evidence, and any two halves of such a file are "a
     * reorder of each other". phpunit's `MetadataTest.php` reaches 28,394 of
     * 30,945 tokens that way, against a baseline that finds 113 ordinary
     * duplications there and no reorder at all.
     *
     * The bijection is built greedily, longest anchor first, and an anchor is
     * taken only if it is free on *both* sides. Greedy is not the maximum
     * matching, and does not need to be: the figure is compared against a
     * threshold, so what matters is that it cannot be inflated by repetition,
     * and taking the longest evidence first cannot do worse than the colinear
     * chain it is compared against — a chain is itself disjoint on both sides,
     * so it is one of the matchings this search is choosing among.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     */
    public function matchedCoverage(array $anchors): int
    {
        if ($anchors === []) {
            return 0;
        }

        $endA = 0;
        $endB = 0;

        foreach ($anchors as [$posA, $posB, $length]) {
            $endA = max($endA, $posA + $length);
            $endB = max($endB, $posB + $length);
        }

        // One byte per token position, "\0" while free. A mask rather than a
        // list of intervals keeps this linear in the anchors' total length
        // rather than quadratic in their count, which is the difference
        // between microseconds and minutes on the ten-thousand-anchor sets
        // this exists to judge.
        $takenA = str_repeat("\0", $endA);
        $takenB = str_repeat("\0", $endB);

        // Longest first, ties broken by position so the result is a function of
        // the anchor set and not of the order it arrived in.
        // Spelled out rather than as a spaceship over two literal arrays: the
        // comparator runs O(m log m) times over anchor sets in the thousands,
        // and building two throwaway arrays per comparison costs more than
        // every other part of this method put together.
        usort(
            $anchors,
            static fn(array $a, array $b): int => ($b[2] <=> $a[2]) ?: (($a[0] <=> $b[0]) ?: ($a[1] <=> $b[1])),
        );

        $total = 0;

        foreach ($anchors as [$posA, $posB, $length]) {
            // Counted token by token rather than taken or refused whole. An
            // anchor that overlaps an earlier one by a few tokens still
            // attests the rest of itself, and discarding all of it would put
            // this figure *below* the colinear chain it is compared against —
            // which would make a plain gapped clone look like it had negative
            // displaced mass. Position t of the anchor pairs A's posA + t with
            // B's posB + t, so a token counts only when both are still free,
            // and the correspondence stays one-to-one.
            //
            // Written byte by byte rather than with substr_replace, which
            // would copy the whole mask per anchor and turn a linear pass into
            // a quadratic one — 169 seconds against 0.4 on one 31,000-token
            // file. Indexed assignment into a string is in place.
            for ($t = 0; $t < $length; $t++) {
                if ($takenA[$posA + $t] !== "\0" || $takenB[$posB + $t] !== "\0") {
                    continue;
                }

                $takenA[$posA + $t] = "\1";
                $takenB[$posB + $t] = "\1";
                $total++;
            }
        }

        return $total;
    }

    /**
     * Total tokens covered by the anchors, counting each A-token once.
     *
     * The reorder test compares this against the colinear coverage, so it has to
     * be a coverage rather than a sum: overlapping anchors on different diagonals
     * would otherwise add up past the length of the span they sit in and make any
     * dense region look reordered.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     */
    public function coverage(array $anchors): int
    {
        if ($anchors === []) {
            return 0;
        }

        $spans = [];

        foreach ($anchors as [$posA, $_, $length]) {
            $spans[] = [$posA, $posA + $length];
        }

        usort($spans, static fn(array $a, array $b): int => $a <=> $b);

        $total = 0;
        $end   = PHP_INT_MIN;

        foreach ($spans as [$from, $to]) {
            if ($from >= $end) {
                $total += $to - $from;
                $end = $to;
            } elseif ($to > $end) {
                $total += $to - $end;
                $end = $to;
            }
        }

        return $total;
    }

    /**
     * @param array<int, int> $treeValue
     * @param array<int, int> $treeCovered
     * @param array<int, int> $treeIndex
     */
    private static function insert(
        array &$treeValue,
        array &$treeCovered,
        array &$treeIndex,
        int $at,
        int $value,
        int $covered,
        int $index,
    ): void {
        for ($i = $at; $i < count($treeValue); $i += $i & -$i) {
            if (self::outranks($value, $covered, $index, $treeValue[$i], $treeCovered[$i], $treeIndex[$i])) {
                $treeValue[$i]   = $value;
                $treeCovered[$i] = $covered;
                $treeIndex[$i]   = $index;
            }
        }
    }

    /**
     * Maximum over ranks 1..$at, with the anchor that achieved it. Equal scores
     * are settled by coverage (ruling M(b)) and a remaining tie by the lower
     * anchor index, so the chain is reproducible.
     *
     * @param array<int, int> $treeValue
     * @param array<int, int> $treeCovered
     * @param array<int, int> $treeIndex
     * @return array{0: int, 1: int, 2: int} score, coverage, anchor index
     */
    private static function query(array $treeValue, array $treeCovered, array $treeIndex, int $at): array
    {
        $value   = PHP_INT_MIN;
        $covered = PHP_INT_MIN;
        $index   = -1;

        for ($i = $at; $i > 0; $i -= $i & -$i) {
            if (self::outranks($treeValue[$i], $treeCovered[$i], $treeIndex[$i], $value, $covered, $index)) {
                $value   = $treeValue[$i];
                $covered = $treeCovered[$i];
                $index   = $treeIndex[$i];
            }
        }

        return [$value, $covered, $index];
    }

    /**
     * Is (score, coverage, anchor) a better chain end than the one it is against?
     *
     * The total order the recurrence chooses by: score first, because it is the
     * criterion and coverage is only the settlement of its ties; then coverage,
     * per ruling M(b); then the lower anchor index, which decides nothing about
     * quality and exists so that two chains alike in both still yield one answer.
     * An empty slot (index −1) loses to anything real.
     */
    private static function outranks(int $value, int $covered, int $index, int $against, int $againstCovered, int $againstIndex): bool
    {
        if ($index === -1) {
            return false;
        }

        if ($againstIndex === -1) {
            return true;
        }

        if ($value !== $against) {
            return $value > $against;
        }

        if ($covered !== $againstCovered) {
            return $covered > $againstCovered;
        }

        return $index < $againstIndex;
    }
}
