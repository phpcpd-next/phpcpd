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

use function hash;
use function intdiv;
use function strcmp;
use function strlen;
use function substr;

/**
 * Winnowed k-gram fingerprinting (Schleimer, Wilkerson & Aiken, SIGMOD 2003).
 *
 * The engine indexes a sample of positions rather than all of them, and the
 * sample is chosen so that the sampling cannot lose a clone. That is the whole
 * point of winnowing, and it is a theorem rather than a tuning result:
 *
 *   **Guarantee.** Every window of W consecutive fingerprints contributes its
 *   minimum to the index. Any common token substring of length ≥ W + K − 1
 *   therefore spans a complete window in both copies, both copies compute the
 *   same fingerprints over it, and both select the same minimum — so the two
 *   copies share at least one *selected* fingerprint. Nothing that long can be
 *   missed by the sampling.
 *
 *   **Density.** The expected fraction of positions selected is 2/(W + 1).
 *
 * The engine sets W + K − 1 = S = ⌈minTokens/2⌉ (see {@see forMinTokens()}), so
 * the guarantee covers every substring of half the reported threshold — which by
 * the pigeonhole argument in the plan is what a clone of `minTokens` tokens with
 * at most one divergence must contain. Recall at the seeding stage is therefore
 * impossible to lose by construction, not something to be recovered by lowering
 * a knob.
 *
 * Written from the published algorithm description. No implementation of MOSS or
 * of any other clone detector was consulted; none is publicly available to copy.
 *
 * ## Why a fingerprint is eight raw bytes compared with strcmp()
 *
 * The selection rule needs a *total order* on fingerprints — any fixed one will
 * do, since both copies of a clone apply the same rule to the same values. Raw
 * xxh3 output is used as the ordering key and compared with strcmp(), which is a
 * memcmp: one C call per comparison, no conversion, and immune to PHP's
 * numeric-string comparison rules, which would otherwise make `"12345678" < "9"`
 * true and quietly destroy the total order the guarantee rests on.
 */
final readonly class Winnower
{
    /**
     * Seed length K, in tokens.
     *
     * Sixteen tokens is 80 bytes of signature per seed, which makes an accidental
     * xxh3 collision between two different seeds negligible, and it is a stronger
     * head-equality guarantee than the 10 the removed suffix-tree engine
     * defaulted to. It is a derived constant, not a knob: raising it would weaken the guarantee's reach
     * (W + K − 1 is pinned to S), and lowering it would multiply false seeds that
     * Stage C then has to reject one memcmp at a time.
     */
    public const int SEED_LENGTH = 16;

    /**
     * The smallest `--min-tokens` this engine will accept.
     *
     * Below 2K + 6 the winnow window W falls under 4 and the sampling stops being
     * a meaningful average over a window — the guarantee formally still holds but
     * the density approaches 1, so the index degenerates into the exhaustive
     * table winnowing exists to avoid. Refused loudly rather than served badly.
     */
    public const int MINIMUM_MIN_TOKENS = 2 * self::SEED_LENGTH + 6;

    /**
     * Distinct-token floor for a normalized k-gram to be fingerprinted at all
     * (M2 audit ruling B).
     *
     * E2's result — type-anchored normalization Pareto-dominates name-blind
     * fuzzing — was measured on function-shaped code, and it does not cover
     * literal-dense data: there, normalization does not refine the match, it
     * *is* the match, because every literal in a numeric table folds to the
     * same token. Measured on this project's benchmark corpora (function
     * bodies vs. generated/data files — composer class maps, Laravel locale
     * arrays, symfony/string's Unicode range tables — normalized under the
     * same type-anchored view the unified engine's second view uses, K = 16
     * tokens per window):
     *
     *   function-body windows (n≈65,000): 1st percentile already at 3 distinct
     *     tokens; median 7. Fewer than 0.65% of windows fall below 3.
     *   data/generated windows (n≈13,000): 97.6% at or below 2; the cleanest
     *     case — two unrelated Unicode range tables in symfony/string, every
     *     integer literal folding to the same normalized token — never exceeds
     *     2 anywhere in either file.
     *
     * 3 is the smallest floor that clears the data case entirely (its
     * observed maximum is 2) while sitting at the function-body
     * distribution's own 1st percentile — the point below which real code
     * essentially never falls. It is derived from that separation, not tuned
     * against any one fixture or corpus: the full measurement, methodology,
     * and per-file breakdown are recorded in the M2 completion packet.
     *
     * Applied twice, both only to the *normalized* view — the raw view is
     * unconditional and untouched:
     *
     *   - at the index ({@see select()}): a normalized k-gram below the floor
     *     is never fingerprinted, so a run built entirely of such k-grams
     *     (a data table's whole extent, in the measured case) seeds nothing;
     *   - at verification: a normalized-only match is rejected if its own
     *     span falls below the floor, which catches the case a single
     *     higher-diversity seed at the edge of a low-diversity run could
     *     otherwise pull in by direct-comparison extension — extension
     *     compares bytes, not diversity, so it does not see the guard at the
     *     index at all.
     */
    public const int NORMALIZED_DIVERSITY_FLOOR = 3;

    /** Bytes per token in the signature string: one type byte plus a 4-byte hash. */
    private const int TOKEN_BYTES = FileTokens::TOKEN_BYTES;

    /**
     * @param positive-int $seedLength K
     * @param positive-int $window     W
     */
    private function __construct(
        public int $seedLength,
        public int $window,
    ) {}

    /**
     * Derive K and W from the reported clone threshold.
     *
     * S = ⌈minTokens/2⌉ is the guarantee threshold: by pigeonhole, a clone of
     * `minTokens` tokens containing at most one divergence has an exact run of at
     * least S tokens on one side of it. Setting W = S − K + 1 makes the winnowing
     * guarantee reach exactly that far, so every such run is seeded.
     */
    public static function forMinTokens(int $minTokens): self
    {
        $guaranteeThreshold = self::guaranteeThresholdFor($minTokens);
        $window             = $guaranteeThreshold - self::SEED_LENGTH + 1;

        // MINIMUM_MIN_TOKENS keeps this above 3; UnifiedStrategy refuses anything
        // lower before construction, and this is the belt to that's braces.
        if ($window < 1) {
            $window = 1;
        }

        return new self(self::SEED_LENGTH, $window);
    }

    /** S = ⌈minTokens/2⌉, the length of run the guarantee covers. */
    public static function guaranteeThresholdFor(int $minTokens): int
    {
        return intdiv($minTokens + 1, 2);
    }

    /** The shortest common substring this configuration is guaranteed to seed. */
    public function guaranteedRunLength(): int
    {
        return $this->window + $this->seedLength - 1;
    }

    /**
     * The selected fingerprints of a signature string, in ascending token order.
     *
     * The sliding-window minimum is computed with a monotonic deque — the
     * classical O(n) technique — rather than by rescanning each window: indices
     * are pushed at the back, and an arriving fingerprint pops every index whose
     * fingerprint is **greater than or equal** to it. That `equal` is the
     * tie-break rule: of two equal fingerprints the later one survives, so the
     * front of the deque is always the *rightmost* minimum of the window. Ties
     * broken by rule, never by iteration order, is what makes two runs — and two
     * copies of a clone — select the same positions.
     *
     * A `$diversityFloor` above zero ({@see NORMALIZED_DIVERSITY_FLOOR} for the
     * normalized view; the raw view always passes zero) additionally requires a
     * k-gram to contain at least that many distinct tokens to be eligible for
     * selection at all — a k-gram below the floor is simply never pushed onto
     * the sliding-window candidate deque, the same as if it did not exist. A
     * window with no eligible k-gram selects nothing, which is the correct
     * outcome for a run built entirely of such k-grams: it carries too little
     * information to attest anything, and the winnowing guarantee was never a
     * promise about a run's *content*, only about which windows of it get
     * sampled.
     *
     * @return list<array{0: string, 1: int}> fingerprint (8 raw bytes), token position
     */
    public function select(string $signature, int $diversityFloor = 0): array
    {
        $tokenCount = intdiv(strlen($signature), self::TOKEN_BYTES);
        $grams      = $tokenCount - $this->seedLength + 1;

        // Fewer k-grams than a whole window means no complete window exists, so
        // nothing here carries the guarantee. A file this short cannot host a
        // clone of minTokens tokens anyway: minTokens ≥ 2S − 1 ≥ grams + K − 1.
        if ($grams < $this->window) {
            return [];
        }

        $seedBytes    = $this->seedLength * self::TOKEN_BYTES;
        $fingerprints = [];

        for ($i = 0; $i < $grams; $i++) {
            $fingerprints[$i] = hash('xxh3', substr($signature, $i * self::TOKEN_BYTES, $seedBytes), true);
        }

        $eligible = $diversityFloor > 0
            ? self::diversityEligibility($signature, $grams, $this->seedLength, $diversityFloor)
            : null;

        /** @var list<int> $deque indices, fingerprints strictly increasing front to back */
        $deque    = [];
        $head     = 0;
        $tail     = 0;
        $selected = [];
        $previous = -1;

        for ($right = 0; $right < $grams; $right++) {
            if ($eligible === null || $eligible[$right]) {
                while ($tail > $head && strcmp($fingerprints[$deque[$tail - 1]], $fingerprints[$right]) >= 0) {
                    $tail--;
                }

                $deque[$tail++] = $right;
            }

            $left = $right - $this->window + 1;

            if ($left < 0) {
                continue; // the first complete window has not closed yet
            }

            while ($tail > $head && $deque[$head] < $left) {
                $head++;
            }

            if ($head >= $tail) {
                continue; // no eligible k-gram fell inside this window
            }

            $minimum = $deque[$head];

            // Consecutive windows usually share a minimum; record each selected
            // position once. The front only ever moves forward, so comparing
            // against the previous one is enough to deduplicate.
            if ($minimum !== $previous) {
                $selected[] = [$fingerprints[$minimum], $minimum];
                $previous   = $minimum;
            }
        }

        return $selected;
    }

    /**
     * Which of a signature's k-grams contain at least `$floor` distinct tokens.
     *
     * Computed with a sliding frequency count rather than by re-scanning each
     * k-gram: moving the window forward by one token removes exactly one token
     * from it and adds exactly one, so the distinct count is maintained in
     * O(1) amortized per step (two `substr` calls and a hash-map update)
     * instead of O(K) — the same asymptotic shape as the fingerprint loop it
     * runs alongside.
     *
     * @return list<bool>
     */
    private static function diversityEligibility(string $signature, int $grams, int $seedLength, int $floor): array
    {
        /** @var array<string, int> $frequency token (5 raw bytes) => occurrences in the current window */
        $frequency = [];
        $distinct  = 0;
        $eligible  = [];

        for ($t = 0; $t < $seedLength; $t++) {
            $token = substr($signature, $t * self::TOKEN_BYTES, self::TOKEN_BYTES);

            if (!isset($frequency[$token])) {
                $frequency[$token] = 0;
                $distinct++;
            }

            $frequency[$token]++;
        }

        $eligible[] = $distinct >= $floor;

        for ($i = 1; $i < $grams; $i++) {
            $leaving = substr($signature, ($i - 1) * self::TOKEN_BYTES, self::TOKEN_BYTES);
            $frequency[$leaving]--;

            if ($frequency[$leaving] === 0) {
                unset($frequency[$leaving]);
                $distinct--;
            }

            $entering = substr($signature, ($i + $seedLength - 1) * self::TOKEN_BYTES, self::TOKEN_BYTES);

            if (!isset($frequency[$entering])) {
                $frequency[$entering] = 0;
                $distinct++;
            }

            $frequency[$entering]++;

            $eligible[] = $distinct >= $floor;
        }

        return $eligible;
    }
}
