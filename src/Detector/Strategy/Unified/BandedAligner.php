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

use function abs;
use function array_fill;
use function ceil;
use function count;
use function max;
use function min;
use function str_split;
use function substr;

/**
 * Stage D's verification: how far apart two token spans really are.
 *
 * A chain of anchors says two spans look alike; it does not say they *are* alike,
 * because the chain is built from the parts that match and is silent about
 * everything between them. So no candidate is reported on the chain's word. Each
 * one is measured here, with a real edit distance over the token streams, and
 * accepted only if the similarity clears the threshold.
 *
 * ## The band, and why it is sound
 *
 * The DP is restricted to the diagonal band |i − j| ≤ k, justified by the
 * band-sufficiency lemma of this project's own paper (§2):
 *
 *   D[i,j] ≥ |i − j|, so |i − j| > k implies D[i,j] > k, and no alignment of
 *   cost ≤ k passes through (i,j).
 *
 * Cells outside the band therefore cannot affect an answer of at most k, and
 * computing only the band costs O(L·k) instead of O(L²) *with identical output* —
 * not an approximation, an exclusion of cells that provably cannot matter.
 *
 * ## Why the band grows instead of starting at its maximum
 *
 * The acceptance threshold allows a distance up to RATIO·L, so a band of that
 * width would always be sufficient — and always quadratic, since the width is a
 * fraction of the length. Instead the band starts at the minimum any alignment
 * needs (the length difference) and doubles until the answer it returns is
 * within it, which the lemma certifies as exact. The cost is O(L·d) in the
 * *actual* distance, so two nearly identical spans — the common case, since a
 * candidate got here by sharing anchors — are measured in time linear in their
 * length, and only genuinely distant pairs pay for a wide band before being
 * rejected.
 *
 * This is Ukkonen's doubling applied to the lemma the paper already proved; the
 * lemma is the project's own work and the doubling follows from it directly.
 */
final readonly class BandedAligner
{
    /**
     * The share of a candidate's tokens allowed to differ.
     *
     * This is the single constant the plan replaces three knobs with: the removed
     * suffix tree's `--edit-distance` becomes RATIO (relative to the candidate's
     * length rather than an absolute token count that means something different
     * at every size), and `--min-similarity` becomes 1 − RATIO. A candidate is
     * accepted at similarity ≥ 0.85 and at no other number.
     */
    public const float RATIO = 0.15;

    /** Bytes per token in the signature string. */
    private const int TOKEN_BYTES = FileTokens::TOKEN_BYTES;

    /** The edit budget for a candidate of this length: ⌈RATIO · L⌉. */
    public static function budgetFor(int $length): int
    {
        return (int) ceil(self::RATIO * $length);
    }

    /**
     * The token-level similarity of two spans, and whether it is good enough.
     *
     * Similarity is 1 − distance/max(m, n): the share of the longer span that
     * did not have to be edited away. A pair whose distance exceeds the budget
     * gets `similarity` computed from the budget it blew rather than an exact
     * distance nobody needs — the answer is "rejected", and paying for a wider
     * band to learn exactly how rejected would be work for a number no report
     * shows.
     *
     * @param int $startA token offset of the A span
     * @param int $lengthA tokens in the A span
     * @return array{distance: ?int, similarity: float, accepted: bool}
     */
    public function compare(
        string $signatureA,
        int $startA,
        int $lengthA,
        string $signatureB,
        int $startB,
        int $lengthB,
    ): array {
        $longest = max($lengthA, $lengthB);

        if ($longest === 0) {
            return ['distance' => 0, 'similarity' => 1.0, 'accepted' => false];
        }

        $budget = self::budgetFor($longest);

        // Two spans whose lengths differ by more than the budget cannot be
        // aligned within it: closing the difference costs one edit per token.
        if (abs($lengthA - $lengthB) > $budget) {
            return ['distance' => null, 'similarity' => 0.0, 'accepted' => false];
        }

        $tokensA = self::tokens($signatureA, $startA, $lengthA);
        $tokensB = self::tokens($signatureB, $startB, $lengthB);

        $band = max(1, abs($lengthA - $lengthB));

        while (true) {
            $distance = $this->distanceWithin($tokensA, $tokensB, $band);

            if ($distance !== null) {
                $similarity = 1.0 - ($distance / $longest);

                return [
                    'distance'   => $distance,
                    'similarity' => $similarity,
                    'accepted'   => $distance <= $budget,
                ];
            }

            if ($band >= $budget) {
                return ['distance' => null, 'similarity' => 0.0, 'accepted' => false];
            }

            $band = min($band * 2, $budget);
        }
    }

    /**
     * The five-byte tokens of a span, as an array.
     *
     * Split once here rather than slicing the signature inside the DP: the inner
     * loop runs O(L·k) times and would otherwise pay two `substr` calls per cell
     * for data that never changes.
     *
     * @return list<string>
     */
    private static function tokens(string $signature, int $start, int $length): array
    {
        if ($length <= 0) {
            return [];
        }

        $slice = substr($signature, $start * self::TOKEN_BYTES, $length * self::TOKEN_BYTES);

        return $slice === '' ? [] : str_split($slice, self::TOKEN_BYTES);
    }

    /**
     * Levenshtein distance if it is at most $band, otherwise null.
     *
     * Two rows rather than a matrix: nothing traces back through this. The
     * divergent ranges a gapped clone reports come from the chain's geometry —
     * the gaps *between* exact anchors, which are already the maximal matching
     * runs — so the alignment is needed for its cost, not for its path, and a
     * full matrix would cost O(L·k) memory to rebuild what the anchors already
     * state exactly.
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    private function distanceWithin(array $a, array $b, int $band): ?int
    {
        $m        = count($a);
        $n        = count($b);
        $infinity = $band + 1;

        // Row 0: aligning an empty A prefix with j tokens of B costs j.
        $previous = array_fill(0, $n + 1, $infinity);

        for ($j = 0, $limit = min($n, $band); $j <= $limit; $j++) {
            $previous[$j] = $j;
        }

        for ($i = 1; $i <= $m; $i++) {
            $low     = max(1, $i - $band);
            $high    = min($n, $i + $band);
            $current = array_fill(0, $n + 1, $infinity);

            // Column 0 exists only while deleting i tokens stays inside the band.
            $current[0] = $i <= $band ? $i : $infinity;

            $best  = $current[0];
            $token = $a[$i - 1];

            for ($j = $low; $j <= $high; $j++) {
                $substitution = $previous[$j - 1] + ($token === $b[$j - 1] ? 0 : 1);
                $deletion     = $previous[$j] + 1;
                $insertion    = $current[$j - 1] + 1;

                $value = $substitution < $deletion ? $substitution : $deletion;

                if ($insertion < $value) {
                    $value = $insertion;
                }

                $current[$j] = $value;

                if ($value < $best) {
                    $best = $value;
                }
            }

            // X-drop: once every cell in the band costs more than the band
            // allows, no continuation can come back under it, so stop.
            if ($best > $band) {
                return null;
            }

            $previous = $current;
        }

        return $previous[$n] <= $band ? $previous[$n] : null;
    }
}
