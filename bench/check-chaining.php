#!/usr/bin/env php
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

/*
 * ChainBuilder against a brute-force oracle.
 *
 * Stage D2 picks the best chain through a file pair's anchors with a sparse
 * dynamic program over a Fenwick tree — O(m log m), and not obviously right by
 * reading. So it is checked the way M2 checked it: against a search that
 * enumerates *every* colinear subset of a small anchor set and takes the best
 * one outright. The DP and the search share no code, and the search is slow
 * enough to be plainly correct.
 *
 * M3 audit ruling M(b) refined what "best" means — equal scores are settled by
 * coverage — so the oracle is extended to the same tie order and re-run over the
 * same sweep. That is the point of keeping it: a component verified once against
 * a brute force stays verified only if the brute force follows it.
 *
 * ## What is compared
 *
 * For each anchor set, under both weightings:
 *
 *   - the score and the coverage the DP returns must equal the search's
 *     lexicographic optimum over (score, coverage);
 *   - the chain the DP returns must be colinear — strictly non-overlapping and
 *     increasing in both files — and must itself realize those two numbers.
 *
 * The second half matters as much as the first: a score is easy to get right
 * while returning the wrong anchors for it, and the anchors are what a clone is
 * reported from.
 *
 * Ties are only settled where they are *observed*: the run counts how many sets
 * held a score tie between chains of different coverage, because a tie rule that
 * is never exercised is not evidence of anything.
 *
 * Deterministic: the anchor sets come from a seeded generator, so a failure is
 * reproducible by set number rather than by luck.
 */

require_once __DIR__ . '/lib.php';

use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\ChainBuilder;

/**
 * A small deterministic generator — no `rand()`, so the sweep is the same sweep
 * on every machine and a failing set can be named.
 */
final class BcbSeededSequence
{
    private int $state;

    public function __construct(int $seed)
    {
        $this->state = $seed;
    }

    /** Uniform in [0, $bound), by a linear congruential step. */
    public function next(int $bound): int
    {
        // Kept to 31 bits of state with the classic glibc multiplier, so the
        // product stays well inside a signed 64-bit int and never silently
        // becomes a float — which would collapse the sequence to a constant and
        // leave the sweep testing one anchor set three thousand times.
        $this->state = ($this->state * 1103515245 + 12345) & 0x7FFFFFFF;

        return ($this->state >> 8) % $bound;
    }
}

/**
 * Can anchor $b follow anchor $a in a chain?
 *
 * Colinear means increasing in both files with no overlap in either: $a has to
 * end at or before $b starts, on both sides. This is the same relation the DP's
 * sweep enforces — by construction in A, by the Fenwick prefix query in B — and
 * it is restated here rather than shared, because an oracle that reuses the
 * subject's own definition tests nothing.
 *
 * @param array{0: int, 1: int, 2: int} $a
 * @param array{0: int, 1: int, 2: int} $b
 */
function bcb_chain_follows(array $a, array $b): bool
{
    return $a[0] + $a[2] <= $b[0] && $a[1] + $a[2] <= $b[1];
}

/**
 * Score and coverage of one ordered chain.
 *
 * Score is the coverage minus what each junction skips (`gapA + gapB`) when gaps
 * are penalized, and the coverage itself when they are not — the two weightings
 * the classifier asks for.
 *
 * @param list<array{0: int, 1: int, 2: int}> $chain ordered
 * @return array{0: int, 1: int} score, coverage
 */
function bcb_chain_weigh(array $chain, bool $penalizeGaps): array
{
    $covered = 0;
    $score   = 0;

    foreach ($chain as $position => $anchor) {
        $covered += $anchor[2];
        $score   += $anchor[2];

        if ($penalizeGaps && $position > 0) {
            $previous = $chain[$position - 1];
            $score -= ($anchor[0] - $previous[0] - $previous[2]) + ($anchor[1] - $previous[1] - $previous[2]);
        }
    }

    return [$score, $covered];
}

/**
 * The furthest into B that a legal predecessor of this anchor can reach.
 *
 * A predecessor is an anchor ending at or before this one's start in A, so the
 * A side never overlaps and only B has to be asked about. Written as the plain
 * O(n²) scan the definition describes, not as the subject's own two-pointer
 * sweep: an oracle that reuses the subject's implementation tests nothing.
 *
 * @param list<array{0: int, 1: int, 2: int}> $anchors
 * @param array{0: int, 1: int, 2: int} $anchor
 */
function bcb_chain_reach(array $anchors, array $anchor): int
{
    $reach = PHP_INT_MIN;

    foreach ($anchors as [$otherA, $otherB, $otherLength]) {
        if ($otherA + $otherLength <= $anchor[0]) {
            $reach = max($reach, $otherB + $otherLength);
        }
    }

    return $reach;
}

/**
 * This anchor with its start advanced past everything a predecessor can claim,
 * or null when there is nothing to advance past or nothing left afterwards.
 *
 * Anchors are extended independently of one another, so the two flanking a
 * divergence routinely overlap by a token or two on one side. The chainer is
 * allowed to trim such an anchor's start rather than lose the junction, and the
 * trim moves *both* starts by the same amount because position t of an anchor
 * pairs A's posA + t with B's posB + t.
 *
 * @param list<array{0: int, 1: int, 2: int}> $anchors
 * @param array{0: int, 1: int, 2: int} $anchor
 * @return array{0: int, 1: int, 2: int}|null
 */
function bcb_chain_trimmed(array $anchors, array $anchor): ?array
{
    $shift = bcb_chain_reach($anchors, $anchor) - $anchor[1];

    if ($shift <= 0 || $shift >= $anchor[2]) {
        return null;
    }

    return [$anchor[0] + $shift, $anchor[1] + $shift, $anchor[2] - $shift];
}

/**
 * The best chain by exhaustive search: every subset, colinear ones weighed.
 *
 * The optimum is lexicographic in (score, coverage) — ruling M(b)'s order, which
 * the DP now follows. The count of sets where that second component actually
 * decided something is returned so the sweep can report it.
 *
 * @param list<array{0: int, 1: int, 2: int}> $anchors
 * @return array{score: int, covered: int, tied: bool}
 */
function bcb_chain_brute_force(array $anchors, bool $penalizeGaps): array
{
    $count = count($anchors);

    // Sorted by start in A, then in B, so a subset taken in index order is in
    // the only order a chain could use it in.
    usort($anchors, static fn(array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

    // Every colinear reading's (score, coverage), kept rather than folded on the
    // fly: "was there a tie at the winning score" is a question about the final
    // maximum, and a running fold answers it about whatever was leading at the
    // time. At most 2^8 subsets, so keeping them costs nothing.
    /** @var list<array{0: int, 1: int}> $readings */
    $readings = [];

    // Three states per anchor rather than two — left out, taken whole, or taken
    // trimmed — because the chainer may substitute the trimmed form for any
    // anchor, and trimming moves an anchor's start. A reading is therefore not
    // always in the order the untrimmed anchors are in, so the states are
    // enumerated and the chosen anchors sorted, rather than walked in a fixed
    // order and patched. An anchor and its own trim overlap, so no reading can
    // hold both, and this enumerates exactly the readings available to the
    // subject: 3^n of them against the 2^n of a set with nothing to trim.
    $trimmed = [];

    foreach ($anchors as $anchor) {
        $trimmed[] = bcb_chain_trimmed($anchors, $anchor);
    }

    $states = 3 ** $count;

    for ($state = 1; $state < $states; $state++) {
        $chain = [];
        $rest  = $state;
        $valid = true;

        for ($i = 0; $i < $count; $i++) {
            $pick = $rest % 3;
            $rest = intdiv($rest, 3);

            if ($pick === 1) {
                $chain[] = $anchors[$i];
            } elseif ($pick === 2) {
                if ($trimmed[$i] === null) {
                    $valid = false;

                    break;
                }

                $chain[] = $trimmed[$i];
            }
        }

        if (!$valid || $chain === []) {
            continue;
        }

        usort($chain, static fn(array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        $colinear = true;

        for ($i = 1; $i < count($chain); $i++) {
            if (!bcb_chain_follows($chain[$i - 1], $chain[$i])) {
                $colinear = false;

                break;
            }
        }

        if (!$colinear) {
            continue;
        }

        $readings[] = bcb_chain_weigh($chain, $penalizeGaps);
    }

    $bestScore = PHP_INT_MIN;

    foreach ($readings as [$score, $_]) {
        $bestScore = max($bestScore, $score);
    }

    // Among the readings that tie at the winning score, the widest coverage —
    // and whether they disagreed about it at all, which is the case ruling M(b)
    // settles and the only case in which this sweep tests the new rule.
    $coverages = [];

    foreach ($readings as [$score, $covered]) {
        if ($score === $bestScore) {
            $coverages[$covered] = true;
        }
    }

    return [
        'score'   => $bestScore,
        'covered' => $coverages === [] ? PHP_INT_MIN : max(array_keys($coverages)),
        'tied'    => count($coverages) > 1,
    ];
}

$sets       = 3000;
$seed       = 20260901;
$sequence   = new BcbSeededSequence($seed);
$builder    = new ChainBuilder();
$mismatches = [];
$tieSets    = 0;
$checked    = 0;

for ($set = 1; $set <= $sets; $set++) {
    // Up to eight anchors, so the 2^m subset enumeration stays honest work
    // rather than an afternoon. Positions are drawn from a range narrow enough
    // that anchors genuinely compete — overlapping, crossing, and tying — which
    // is what the DP has to get right.
    //
    // Every other set is drawn from a tighter range. A junction ties with
    // dropping it exactly when the run it reaches is worth what the gap costs,
    // which is a coincidence between two small numbers; sets spread over a wide
    // range produce it too rarely for a sweep to say the tie order was tested at
    // all. The two families are both unbiased — neither is built *to* tie.
    $dense   = $set % 2 === 0;
    $extent  = $dense ? 24 : 60;
    $longest = $dense ? 8 : 20;

    $count   = 1 + $sequence->next(8);
    $anchors = [];

    for ($i = 0; $i < $count; $i++) {
        $anchors[] = [$sequence->next($extent), $sequence->next($extent), 1 + $sequence->next($longest)];
    }

    foreach ([true, false] as $penalizeGaps) {
        $expected = bcb_chain_brute_force($anchors, $penalizeGaps);
        $actual   = $builder->chain($anchors, $penalizeGaps);
        $checked++;

        if ($penalizeGaps && $expected['tied']) {
            $tieSets++;
        }

        // The geometry the chainer used, not the anchors it was given: where it
        // made a junction across an overlap it trimmed the later anchor's
        // start, and a colinearity check against the untrimmed anchor would be
        // asking about a reading nobody returned.
        $chain = $actual['chain'];

        // The returned chain has to be a chain, and has to be the one the
        // returned numbers describe.
        $colinear = true;

        for ($i = 1; $i < count($chain); $i++) {
            if (!bcb_chain_follows($chain[$i - 1], $chain[$i])) {
                $colinear = false;

                break;
            }
        }

        [$chainScore, $chainCovered] = bcb_chain_weigh($chain, $penalizeGaps);

        $problems = [];

        if ($actual['score'] !== $expected['score']) {
            $problems[] = sprintf('score %d, oracle %d', $actual['score'], $expected['score']);
        }

        if ($actual['covered'] !== $expected['covered']) {
            $problems[] = sprintf('covered %d, oracle %d', $actual['covered'], $expected['covered']);
        }

        if (!$colinear) {
            $problems[] = 'returned chain is not colinear';
        }

        if ($chain !== [] && ($chainScore !== $actual['score'] || $chainCovered !== $actual['covered'])) {
            $problems[] = sprintf(
                'returned anchors weigh (%d, %d), reported (%d, %d)',
                $chainScore,
                $chainCovered,
                $actual['score'],
                $actual['covered'],
            );
        }

        if ($problems !== []) {
            $mismatches[] = sprintf(
                "  set %d (%s): %s\n    anchors: %s",
                $set,
                $penalizeGaps ? 'gap-penalized' : 'colinear coverage',
                implode('; ', $problems),
                json_encode($anchors),
            );
        }
    }
}

printf("Chaining oracle — %d anchor sets x 2 weightings, seed %d\n\n", $sets, $seed);
printf("  %d comparisons against the brute-force search\n", $checked);
printf("  %d sets held a score tie between chains of different coverage\n\n", $tieSets);

if ($mismatches !== []) {
    printf("  FAIL  the sparse DP agrees with the brute force — %d mismatches\n\n", count($mismatches));
    echo implode("\n", array_slice($mismatches, 0, 10)), "\n";

    exit(1);
}

echo "  PASS  the sparse DP agrees with the brute force, including the tie order — 0 mismatches\n";
echo "  PASS  every returned chain is colinear and weighs what it reports — 0 mismatches\n\n";
echo "chaining oracle: 2/2 checks passed.\n";

exit(0);
