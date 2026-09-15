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

use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;

use function array_slice;
use function count;
use function crc32;
use function explode;
use function max;
use function min;
use function sort;
use function strlen;
use function substr;
use function usort;

/**
 * Ruling R's third seed channel: per-function bags of small-k shingles.
 *
 * ## The one class seeds provably cannot see
 *
 * Stage B seeds on winnowed k-grams and Stage C chains them colinearly, so the
 * engine's evidence is always an *ordered* run. Ruling A's algebra says what that
 * costs: a displacement shorter than K = 16 tokens leaves no run long enough to
 * be seeded on either side of it, so a function whose statements have been
 * permuted in place is invisible to the seeded path however the thresholds are
 * set. That class — and, by constraint 4, only that class — is what this channel
 * exists to reach. It is the capability the token bag has and this engine did
 * not, which is why ruling R makes it a *replacement* rather than a removal.
 *
 * ## Bags of 3-token shingles, and where the 3 comes from
 *
 * A bag of token *unigrams* is nearly free to match: two unrelated functions in
 * the same codebase share a vocabulary. A bag of long n-grams is the seeded path
 * again with extra steps. The useful length is one that sits **below statement
 * length**, so swapping two statements perturbs only the shingles that straddle
 * the boundary between them — a statement of L tokens contributes L − k + 1
 * shingles that lie wholly inside it and survive the move, and k − 1 that do not.
 * A statement shorter than k contributes **none**, and a swap of two such
 * statements is then unlocalizable, which constraint 5 forbids reporting at all.
 *
 * So the floor case decides k, and ruling R names it: fixture r1's swapped
 * statements. **In the engine's own token numbering they are 3 tokens**, not the
 * 7 the M2 packet records — that figure counts source tokens, while `--min-tokens`,
 * K = 16 and every span in this engine count *significant* tokens, and the encoder
 * drops single-character tokens entirely, so `$delta = $alpha + $bravo;` is three.
 * The unit matters here and nowhere else in the ruling, so it is stated rather
 * than assumed.
 *
 * Measured with `bench/measure-statements.php`, over the top-level statements of
 * every function body in the four bench corpora — the same segmentation the
 * permutation injectors move, and counted by the engine's own encoder:
 *
 * ```
 *   corpus            stmts    p1   p5  p10  p25  p50  p90
 *   php-parser         1619     1    2    2    2    4   12
 *   symfony-string     1138     2    2    2    4    7   23
 *   phpunit            1346     2    4    4    4    4   12
 *   firefly-iii        4577     1    3    3    4    6   21
 *   ALL                8680     1    2    3    4    5   18
 *   r1 (the floor case)  22     3    3    3    3    3    3
 *
 *   k    statements with at least one interior shingle
 *    3   90.36 %
 *    4   82.60 %
 *    5   57.48 %
 * ```
 *
 * **k = 3**: the largest shingle that fits inside the floor case the ruling
 * names, and the value at which 90 % of all real statements still contribute a
 * shingle that survives being moved. k = 4 would silently put r1 — the fixture
 * whose whole purpose is to be the hard case — permanently out of contract, and
 * it was measured doing exactly that before this derivation was corrected: bag
 * coverage 0.775, above θ, with **zero** localizable displacement, so nothing
 * reportable. A larger k is more discriminating and that is the trade being
 * refused here on the ruling's own instrument.
 *
 * ## Constraint 2 — the diversity floor, reused rather than re-derived
 *
 * A shingle drawn from fewer than {@see Winnower::NORMALIZED_DIVERSITY_FLOOR}
 * distinct tokens is not indexed. Ruling B derived that floor for the normalized
 * view's fingerprints and the argument is the same one here: `] ) ; }` is a
 * shingle of the *language*, not of the program, and indexing it costs pair
 * enumeration and buys nothing.
 *
 * ## Prefix filtering, and why the order is by document frequency
 *
 * Comparing every function against every other is quadratic, and this channel
 * runs after a stage that already found everything ordered. The published
 * remedy is the prefix filter (Chaudhuri, Ganti & Kaushik, ICDE 2006; refined as
 * PPJoin by Xiao et al., TODS 2011): fix any total order on shingles, and two
 * bags whose overlap reaches θ·max(|A|, |B|) must share an element in the first
 * ⌊(1 − θ)·|S|⌋ + 1 of each. Only those prefixes are indexed, so the enumeration
 * touches a fraction of the pairs and **loses none that could have passed**.
 *
 * The order is by ascending document frequency, which is the papers' own choice
 * and is what makes the filter bite: prefixes then hold each function's *rarest*
 * shingles, and a posting list is short exactly where a common shingle would have
 * made it long. No cap is introduced, because the ordering removes the need for
 * one.
 *
 * ## What this class does not do
 *
 * It emits *candidates*, not findings. Complement gating (constraint 4) and the
 * order-free verdict (θ, constraint 3) belong to the caller and to
 * {@see CloneClassifier}, so that a bag-seeded pair reaches the report through
 * exactly the same classify → verify → classes path as an anchor-seeded one.
 * That is what ruling R's integrated shape asks for and it is why there is no
 * fourth `--algorithm`.
 */
final class ShingleBags
{
    /**
     * Tokens per shingle. Derived above from the floor case ruling R names and
     * the measured statement-length distribution; see
     * `bench/measure-statements.php` for the instrument.
     */
    public const int SHINGLE_LENGTH = 3;

    private const int TOKEN_BYTES = FileTokens::TOKEN_BYTES;

    /**
     * The shingles of one token range, as [position, hash] in position order.
     *
     * Raw view only (constraint 1): unrelated data tables differ in their
     * literals, so their raw shingles differ, while the normalized view would
     * make every integer table a permutation of every other.
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function shingles(string $signature, int $start, int $length): array
    {
        $shingles = [];
        $limit    = $length - self::SHINGLE_LENGTH + 1;

        for ($i = 0; $i < $limit; $i++) {
            $offset = ($start + $i) * self::TOKEN_BYTES;
            $gram   = substr($signature, $offset, self::SHINGLE_LENGTH * self::TOKEN_BYTES);

            if (strlen($gram) < self::SHINGLE_LENGTH * self::TOKEN_BYTES) {
                break;
            }

            if (self::distinct($gram) < Winnower::NORMALIZED_DIVERSITY_FLOOR) {
                continue;
            }

            $shingles[] = [$start + $i, crc32($gram)];
        }

        return $shingles;
    }

    /**
     * How many distinct tokens a shingle draws on — ruling B's floor, applied to
     * the bag index.
     */
    private static function distinct(string $gram): int
    {
        $seen = [];

        for ($i = 0; $i < self::SHINGLE_LENGTH; $i++) {
            $seen[substr($gram, $i * self::TOKEN_BYTES, self::TOKEN_BYTES)] = true;
        }

        return count($seen);
    }

    /**
     * Which function pairs have enough bag evidence to be worth verifying.
     *
     * @param list<array{file: int, start: int, length: int, hashes: list<int>, distinct: list<int>}> $units
     * @return list<array{0: int, 1: int}> indices into $units, each pair once, ascending
     */
    public static function candidatePairs(array $units, float $theta): array
    {
        /** @var array<int, int> $frequency */
        $frequency = [];

        foreach ($units as $unit) {
            foreach ($unit['distinct'] as $hash) {
                $frequency[$hash] = ($frequency[$hash] ?? 0) + 1;
            }
        }

        /** @var array<int, list<int>> $index */
        $index = [];
        /** @var array<string, true> $pairs */
        $pairs = [];

        foreach ($units as $id => $unit) {
            $ordered = $unit['distinct'];

            // Rarest first, ties broken by the hash so the order is total and the
            // enumeration is reproducible.
            usort(
                $ordered,
                static fn(int $a, int $b): int => [$frequency[$a] ?? 0, $a] <=> [$frequency[$b] ?? 0, $b],
            );

            $prefix = (int) ((1.0 - $theta) * count($ordered)) + 1;

            foreach (array_slice($ordered, 0, $prefix) as $hash) {
                foreach ($index[$hash] ?? [] as $other) {
                    $pairs[min($other, $id) . ':' . max($other, $id)] = true;
                }

                $index[$hash][] = $id;
            }
        }

        $result = [];

        foreach ($pairs as $key => $_) {
            [$left, $right] = explode(':', $key);
            $result[]       = [(int) $left, (int) $right];
        }

        sort($result);

        return $result;
    }

    /**
     * Bijective coverage of two bags: how much of the larger one the smaller can
     * be matched onto, one occurrence to one occurrence.
     *
     * **Bijective, not one-sided**, and that is the M2 lesson rather than a
     * preference: one-sided coverage scored a repetitive file as a reorder of
     * itself, because every one of its shingles is present in the other side —
     * many times over. Matching multiset occurrences one-to-one removes that
     * entirely: a bag of 40 copies of one shingle covers a bag of one copy by
     * exactly one.
     *
     * @param list<int> $left
     * @param list<int> $right
     */
    public static function coverage(array $left, array $right): float
    {
        $larger = max(count($left), count($right));

        if ($larger === 0) {
            return 0.0;
        }

        /** @var array<int, int> $counts */
        $counts = [];

        foreach ($left as $hash) {
            $counts[$hash] = ($counts[$hash] ?? 0) + 1;
        }

        $matched = 0;

        foreach ($right as $hash) {
            if (($counts[$hash] ?? 0) > 0) {
                $counts[$hash]--;
                $matched++;
            }
        }

        return $matched / $larger;
    }

    /**
     * The maximal runs of shingles that appear in both spans at a *constant*
     * offset, and which of them are displaced relative to the dominant offset.
     *
     * Constraint 5: the pass never emits an unlocalized "the material is all
     * present" finding. A bag on its own cannot say where anything moved, so the
     * positions the shingles carry are re-assembled into runs, and the runs that
     * do not sit at the pair's dominant offset are the blocks that moved. They
     * are reported through the same `displaced` channel a seeded reorder uses,
     * so the loggers, the classes and the gates need to know nothing new.
     *
     * @param  list<array{0: int, 1: int}> $left  [position, hash], position order
     * @param  list<array{0: int, 1: int}> $right [position, hash], position order
     * @return list<array{0: int, 1: int, 2: int}> [positionA, positionB, length]
     */
    public static function displacedRuns(array $left, array $right): array
    {
        /** @var array<int, list<int>> $positions hash => positions in $right, unconsumed */
        $positions = [];

        foreach ($right as [$position, $hash]) {
            $positions[$hash][] = $position;
        }

        /** @var list<array{0: int, 1: int, 2: int}> $runs */
        $runs        = [];
        $runStartA   = null;
        $runStartB   = 0;
        $runLength   = 0;
        $previousB   = 0;

        foreach ($left as [$positionA, $hash]) {
            $candidate = null;

            // Prefer the continuation of the current run, so a repeated shingle
            // extends a run rather than starting a new one somewhere else.
            foreach ($positions[$hash] ?? [] as $slot => $positionB) {
                if ($candidate === null || ($runStartA !== null && $positionB === $previousB + 1)) {
                    $candidate = [$slot, $positionB];
                }

                if ($runStartA !== null && $positionB === $previousB + 1) {
                    break;
                }
            }

            if ($candidate === null) {
                if ($runStartA !== null) {
                    $runs[]    = [$runStartA, $runStartB, $runLength];
                    $runStartA = null;
                }

                continue;
            }

            [$slot, $positionB] = $candidate;
            unset($positions[$hash][$slot]);

            if ($runStartA !== null && $positionB === $previousB + 1 && $positionA === $runStartA + $runLength) {
                $runLength++;
            } else {
                if ($runStartA !== null) {
                    $runs[] = [$runStartA, $runStartB, $runLength];
                }

                $runStartA = $positionA;
                $runStartB = $positionB;
                $runLength = 1;
            }

            $previousB = $positionB;
        }

        if ($runStartA !== null) {
            $runs[] = [$runStartA, $runStartB, $runLength];
        }

        // Which runs are *displaced* is not "which sit at an unusual offset" —
        // an inserted statement shifts everything after it and would then look
        // like a move. It is "which break the order": a permutation is exactly a
        // pair of runs whose relative order differs on the two sides, while an
        // insertion or a deletion preserves it. So the displaced set is the
        // complement of the heaviest run-order-preserving subsequence, which is
        // the same construction {@see CloneClassifier::displacedAnchors()} uses
        // on anchors — one notion of "displaced" in the engine, not two.
        //
        // Measured consequence, recorded because it was a real defect and not a
        // hypothesis: with the offset rule, probe 2's twin *insertions* were
        // reported as a reorder.
        $count = count($runs);
        /** @var list<int> $weight heaviest increasing chain ending at each run */
        $weight = [];
        /** @var list<int> $previous */
        $previous = [];

        for ($i = 0; $i < $count; $i++) {
            $weight[$i]   = $runs[$i][2];
            $previous[$i] = -1;

            for ($j = 0; $j < $i; $j++) {
                if ($runs[$j][1] + $runs[$j][2] <= $runs[$i][1] && $weight[$j] + $runs[$i][2] > $weight[$i]) {
                    $weight[$i]   = $weight[$j] + $runs[$i][2];
                    $previous[$i] = $j;
                }
            }
        }

        $best  = -1;
        $tail  = -1;

        for ($i = 0; $i < $count; $i++) {
            if ($weight[$i] > $best) {
                $best = $weight[$i];
                $tail = $i;
            }
        }

        $colinear = [];

        while ($tail >= 0 && isset($previous[$tail])) {
            $colinear[$tail] = true;
            $tail            = $previous[$tail];
        }

        $displaced = [];

        foreach ($runs as $index => $run) {
            if (!isset($colinear[$index])) {
                $displaced[] = $run;
            }
        }

        sort($displaced);

        return $displaced;
    }
}
