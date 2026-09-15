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

use function count;
use function intdiv;
use function min;
use function sort;
use function strlen;
use function strpos;
use function substr;
use function substr_compare;
use function usort;

/**
 * Stage C: turn shared fingerprints into maximal exact matches.
 *
 * A shared fingerprint says only "these two places begin with the same K tokens".
 * An anchor is what that grows into: the longest exact common run through those
 * two points, found by walking outwards from the seed in both directions. This is
 * the seed-and-extend paradigm of BLAST (Altschul et al. 1990/1997), taken from
 * the published description; the extension is where PHP does almost none of the
 * work, because comparing two token runs is `substr_compare` — a memcmp at
 * C speed over the packed signature — rather than a loop over an array.
 *
 * Extension length is found by binary search on a monotone predicate: if the next
 * t tokens match then the next t − 1 match too, so ⌈log₂ L⌉ memcmps locate the
 * exact mismatch. The alternative, stepping token by token, would put the one
 * corpus-scale loop of the engine back into the interpreter.
 *
 * ## Why seeds are grouped by diagonal before anything is extended
 *
 * A long exact match is selected by many fingerprints, not one, and every one of
 * them would extend to the same anchor — the same memcmps, repeated. All seeds of
 * one match share a *diagonal*, the offset `posB − posA` between the two copies,
 * so grouping by (file pair, diagonal) puts them together. Within a diagonal the
 * seeds are swept in ascending position and any seed already inside the previous
 * anchor's reach is skipped: each maximal match is extended exactly once,
 * regardless of how many fingerprints found it.
 *
 * That grouping is also the deduplication the design asks for. Two anchors on one
 * diagonal are the same anchor if they end at the same place, and the sweep can
 * only produce one anchor per ending, so `(file pair, diagonal, end)` is unique by
 * construction rather than by a filtering pass afterwards.
 *
 * ## The seed-pair bound, and why the posting cap is not one
 *
 * The loop below is Θ(n²) in a fingerprint's posting count, and
 * {@see FingerprintIndex::POSTINGS_CAP} bounds the *list* rather than the pairs
 * that list yields: at the cap, one fingerprint alone emits C(1000, 2) = 499,500
 * seed pairs. That is the same product-versus-sum mistake M2 audit ruling D
 * identified one level down — a bound on a sum cannot bound a product — and
 * measured on a 600-file slice of a real application it is 14.7 million pairs and
 * an exhausted gigabyte, which is to say the engine did not finish at all.
 * {@see SEED_PAIR_CAP} restates the bound on the quantity that costs.
 */
final class AnchorSet
{
    /**
     * Maximum seed pairs emitted from one fingerprint.
     *
     * **M3 audit ruling N (2026-09-01).** The same *class* of constant as
     * {@see FingerprintIndex::POSTINGS_CAP} and {@see FingerprintIndex::PER_FILE_CAP}
     * — a counted recall trade, already admitted — and chosen by ruling D's
     * method: the dense-coverage knee, measured on the corpora that exhibit the
     * failure, never on wall-clock and never on a failing gate.
     *
     * The measurement is the union of source lines the engine reports, restricted
     * to clones dense enough to be code rather than a chain across a gap (≥ 4
     * tokens per line), swept over the cap on phpunit:
     *
     *     cap      1,000    2,000    4,000    8,000  *16,000*   32,000  uncapped
     *     dense    8,614    8,661    8,964    8,951   *9,036*    9,036     9,036
     *
     * Sixteen thousand is the dense-coverage knee: the smallest cap that reports
     * every dense line an uncapped run reports, and the point below which the
     * engine starts losing them. It is chosen there and nowhere else — not on
     * wall-clock, which it makes worse (3.4 s at 1,000 against 4.2 s uncapped),
     * and not on the memory failure that prompted the ruling, which is an
     * acceptance check run afterwards and recorded in the M3 packet.
     *
     * The value costs nothing on the corpus that motivated it: on a 600-file
     * slice of a real application, dense coverage is 244 lines at *every* cap
     * from 1,000 to uncapped, because that corpus's duplication is literal data
     * tables rather than dense code. What the cap buys there is that the engine
     * finishes at all.
     *
     * Past the cap a fingerprint's later postings pair only with its earliest
     * ones, which is the loss, and it is a bounded one: every posting still
     * appears in a pair, so every place a runaway fingerprint selects is still
     * seeded against a representative copy of that region, and `classes()` closes
     * the relation transitively over what those pairs report. What is lost is the
     * all-against-all detail *within* one boilerplate region — the same thing the
     * two posting caps already trade away, at the axis that actually costs.
     *
     * Counted and surfaced exactly as the other two are, through
     * {@see pairCappedFingerprints()} and {@see discardedSeedPairs()}. Never
     * silent.
     */
    public const int SEED_PAIR_CAP = 16000;

    /** Bytes per token in the signature string. */
    private const int TOKEN_BYTES = FileTokens::TOKEN_BYTES;

    /** How many fingerprints emitted fewer pairs than their postings imply. */
    private int $pairCappedFingerprints = 0;

    /** How many seed pairs the cap did not emit, in total. */
    private int $discardedSeedPairs = 0;

    /**
     * @param array<int, string> $signatures file id => signature string
     */
    public function __construct(
        private readonly array $signatures,
        private readonly int $seedLength,
    ) {}

    /** How many fingerprints hit the seed-pair cap — reported, never silent. */
    public function pairCappedFingerprints(): int
    {
        return $this->pairCappedFingerprints;
    }

    /** How many seed pairs the cap discarded, in total. */
    public function discardedSeedPairs(): int
    {
        return $this->discardedSeedPairs;
    }

    /**
     * Every maximal exact match reachable from the shared fingerprints.
     *
     * @param array<string, list<int>> $postings fingerprint => packed postings
     * @return list<array{0: int, 1: int, 2: int, 3: int, 4: int}>
     *         fileA, posA, fileB, posB, length — fileA ≤ fileB, and posA < posB
     *         when they are equal, so a pair is named one way only
     */
    public function build(array $postings): array
    {
        /** @var array<string, list<int>> $byDiagonal "fileA:fileB:diagonal" => seed positions in A */
        $byDiagonal = [];

        foreach ($postings as $list) {
            $count   = count($list);
            $emitted = 0;

            // The cap is a prefix of this enumeration, the same discipline the
            // two posting caps use: they keep a prefix of an already-sorted list,
            // so the index is a function of the corpus and not of the walk order,
            // and so is this. Stopping i-major means the postings past the cutoff
            // still pair with the earliest ones — every place stays seeded — while
            // the all-against-all interior of a runaway fingerprint is dropped.
            for ($i = 0; $i < $count && $emitted < self::SEED_PAIR_CAP; $i++) {
                $fileA = FingerprintIndex::fileOf($list[$i]);
                $posA  = FingerprintIndex::positionOf($list[$i]);

                for ($j = $i + 1; $j < $count && $emitted < self::SEED_PAIR_CAP; $j++) {
                    $fileB = FingerprintIndex::fileOf($list[$j]);
                    $posB  = FingerprintIndex::positionOf($list[$j]);

                    // Postings are ascending, so j is always the later place and
                    // a pair is generated once. Within one file that makes posA
                    // strictly less than posB, which is what keeps a run from
                    // being "found" against itself at offset zero.
                    $byDiagonal[$fileA . ':' . $fileB . ':' . ($posB - $posA)][] = $posA;
                    $emitted++;
                }
            }

            $possible = intdiv($count * ($count - 1), 2);

            if ($emitted < $possible) {
                $this->pairCappedFingerprints++;
                $this->discardedSeedPairs += $possible - $emitted;
            }
        }

        $anchors = [];

        foreach ($byDiagonal as $key => $seeds) {
            [$fileA, $fileB, $diagonal] = self::parseKey($key);

            sort($seeds);
            $reach = -1;

            foreach ($seeds as $posA) {
                if ($posA <= $reach) {
                    continue; // already inside the anchor a previous seed grew
                }

                $anchor = $this->extend($fileA, $posA, $fileB, $posA + $diagonal);

                if ($anchor === null) {
                    continue;
                }

                [$startA, $startB, $length] = $anchor;
                $reach                      = $startA + $length - 1;
                $anchors[]                  = [$fileA, $startA, $fileB, $startB, $length];
            }
        }

        // A total order on the output, so the anchor list — and everything the
        // classifier derives from it — is the same however the corpus was walked.
        usort($anchors, static fn(array $a, array $b): int => [$a[0], $a[1], $a[2], $a[3], $a[4]] <=> [$b[0], $b[1], $b[2], $b[3], $b[4]]);

        return $anchors;
    }

    /**
     * Grow one seed into its maximal exact match.
     *
     * @return array{0: int, 1: int, 2: int}|null startA, startB, length
     */
    private function extend(int $fileA, int $posA, int $fileB, int $posB): ?array
    {
        $a = $this->signatures[$fileA] ?? null;
        $b = $this->signatures[$fileB] ?? null;

        if ($a === null || $b === null) {
            return null;
        }

        $tokensA = intdiv(strlen($a), self::TOKEN_BYTES);
        $tokensB = intdiv(strlen($b), self::TOKEN_BYTES);

        if ($posA + $this->seedLength > $tokensA || $posB + $this->seedLength > $tokensB) {
            return null;
        }

        $sameFile      = $fileA === $fileB;
        $diagonal      = $posB - $posA;
        $backwardLimit = min($posA, $posB);
        $forwardLimit  = min($tokensA - $posA, $tokensB - $posB);

        // Two copies inside one file must not grow into each other: their total
        // length can be at most the distance between them, or the "clone" would
        // be one stretch of code reported as a duplicate of itself.
        if ($sameFile) {
            $backwardLimit = min($backwardLimit, $diagonal);
        }

        $backward = $this->matchRun($a, $posA, $b, $posB, $backwardLimit, backwards: true);

        if ($sameFile) {
            $forwardLimit = min($forwardLimit, $diagonal - $backward);
        }

        $forward = $this->matchRun($a, $posA, $b, $posB, $forwardLimit);

        if ($forward < $this->seedLength) {
            // The seed itself did not survive an exact comparison: two different
            // token runs shared an xxh3 fingerprint. Rare, and rejected here for
            // the cost of one memcmp — which is why a hash collision in Stage B
            // can waste work but can never produce a wrong report.
            return null;
        }

        return [$posA - $backward, $posB - $backward, $backward + $forward];
    }

    /**
     * How many tokens match from these two positions, at most $limit — counted
     * forwards from them, or, with $backwards, immediately before them.
     *
     * Binary search over a monotone predicate: "the next t tokens are equal" can
     * only become false as t grows, so ⌈log₂ limit⌉ memcmps find the boundary.
     * The direction is the only thing that changes: forwards the compared window
     * starts at the given positions and grows, backwards it ends at them and so
     * starts $mid tokens earlier. The predicate, and therefore the search, is
     * the same at both ends of the seed.
     */
    private function matchRun(string $a, int $tokenA, string $b, int $tokenB, int $limit, bool $backwards = false): int
    {
        if ($limit <= 0) {
            return 0;
        }

        $low  = 0;        // known to match
        $high = $limit;   // not yet known

        while ($low < $high) {
            $mid   = intdiv($low + $high + 1, 2);
            $bytes = $mid * self::TOKEN_BYTES;
            $start = $backwards ? $mid : 0;

            $offsetA = ($tokenA - $start) * self::TOKEN_BYTES;
            $offsetB = ($tokenB - $start) * self::TOKEN_BYTES;

            if (substr_compare($a, substr($b, $offsetB, $bytes), $offsetA, $bytes) === 0) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }

        return $low;
    }

    /** @return array{0: int, 1: int, 2: int} fileA, fileB, diagonal */
    private static function parseKey(string $key): array
    {
        $first  = strpos($key, ':');
        $second = strpos($key, ':', (int) $first + 1);

        return [
            (int) substr($key, 0, (int) $first),
            (int) substr($key, (int) $first + 1, (int) $second - (int) $first - 1),
            (int) substr($key, (int) $second + 1),
        ];
    }
}
