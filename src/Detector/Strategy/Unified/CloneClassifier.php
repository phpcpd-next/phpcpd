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

use function array_column;
use function array_fill;
use function count;
use function max;
use function min;
use function str_split;
use function strlen;
use function substr;
use function usort;

/**
 * Stage D3–4: what kind of clone a chain is, and whether it is one at all.
 *
 * Every capability the three engines had lands here, decided from one anchor set
 * rather than from three different algorithms:
 *
 *   - **Type-1 / Type-2** — a chain that is a single gapless anchor. Which of the
 *     two depends only on the view it came from: found in the raw token stream it
 *     is an exact copy; found only after normalization it is the same code with
 *     the names changed.
 *   - **Type-3 gapped** — a chain of two or more anchors on a consistent
 *     diagonal. The gaps between them are the divergence, and they are reported
 *     as byte-exact token ranges on *both* sides. This is the signal the suffix
 *     tree existed for, with the location kept instead of thrown away.
 *   - **Type-3 reordered** — the same anchors, judged twice. High total coverage
 *     with at least {@see DISPLACED_MASS_FLOOR} tokens of *displaced* mass —
 *     coverage the best in-order chain had to drop — means a block of at least
 *     that many tokens sits in a different place, which is what a statement
 *     swap looks like from here. Unlike a token bag, this knows *which*
 *     statements moved, because the anchors that the best chain had to drop are
 *     exactly the displaced ones.
 *
 * ## Nothing is reported on the chain's word
 *
 * A chain is a hypothesis assembled from the parts that match, and it is silent
 * about everything in between; believing it would mean reporting two spans that
 * share four scattered anchors and nothing else. So every candidate is verified,
 * and the verification differs by kind because the kinds fail differently:
 *
 *   - gapless and gapped candidates are verified by {@see BandedAligner} — a real
 *     token-level edit distance, accepted at similarity ≥ 1 − RATIO = 0.85;
 *   - reordered candidates are verified by coverage ≥ {@see THETA}, because an
 *     edit distance is the wrong instrument for them: moving a statement is cheap
 *     to *do* and expensive to *align*, so a genuine reorder scores terribly on
 *     edit distance while being exactly what the report is about. The threshold
 *     is SourcererCC's overlap (Sajnani et al., ICSE 2016) — a threshold value,
 *     not an expression.
 */
final readonly class CloneClassifier
{
    /**
     * Overlap a reordered candidate must reach, from SourcererCC (ICSE 2016).
     *
     * Only reached by the reorder branch: a gapped or gapless candidate is judged
     * on its alignment instead.
     */
    public const float THETA = 0.7;

    /**
     * The least displaced mass (total anchor coverage minus the best colinear
     * chain's coverage) a candidate must show to count as reordered.
     *
     * Restated by M2 audit ruling A. The plan's original figure — colinear
     * coverage under half of total — is provably unsatisfiable for any two-block
     * swap: with blocks X, Y exchanged inside shared context C, total coverage is
     * |C|+|X|+|Y| and the best colinear chain always keeps the context plus
     * whichever block is larger, so colinear = |C|+max(|X|,|Y|). "colinear <
     * half of total" then demands |C|+max < min, which is impossible since
     * max ≥ min. No block-swap fixture, of any size, could ever satisfy it.
     *
     * The floor is {@see Winnower::SEED_LENGTH}, and it is derived rather than
     * tuned: K exact tokens matched at crossed positions is the smallest
     * displacement a seeded method can attest at all, because any anchor that
     * crosses another on the diagonal is, by construction, at least K tokens
     * long. Displacements shorter than K (statement-level micro-swaps below the
     * seed length) are out of contract for any seeded method — a capability the
     * removed TokenBag engine retained; M3's permutation recall curve measures
     * the loss.
     */
    public const int DISPLACED_MASS_FLOOR = Winnower::SEED_LENGTH;

    /**
     * The shortest exact run worth recovering during gapped extension.
     *
     * Stage B only seeds runs of at least K = 16 tokens, so material on the far
     * side of a divergence is invisible to it when the run there is shorter —
     * which is the ordinary case at a clone's edges, where the tail after the
     * last change is often a closing brace and a return. Extension recovers those
     * runs by direct comparison rather than by seeding, and this is the floor
     * below which a run stops being evidence: `);`, `}`, `return $this;` recur
     * everywhere, and matching three of them says nothing about duplication.
     */
    private const int MINIMUM_RECOVERED_RUN = 4;

    /**
     * How wide a gap is still worth searching exhaustively for a stranded run.
     *
     * Derived from what the search is *for*. It recovers runs that Stage B could
     * not seed, which means runs shorter than K = 16 tokens; a longer run inside
     * a gap would have carried its own seed and already be an anchor. Four seed
     * lengths on each side leaves room for a divergence either side of such a run
     * and still bounds the one quadratic step in the engine at 64×64 cells.
     *
     * A gap wider than this keeps the behaviour the search exists to improve on:
     * the divergence stays whole. That is a bounded loss of resolution inside one
     * gap, not a lost clone.
     */
    private const int GAP_SEARCH_SPAN = 4 * Winnower::SEED_LENGTH;

    /** Bytes per token in the signature string. */
    private const int TOKEN_BYTES = FileTokens::TOKEN_BYTES;

    public function __construct(
        private BandedAligner $aligner = new BandedAligner(),
        private ChainBuilder $chains = new ChainBuilder(),
    ) {}

    /**
     * Classify and verify one file pair's anchors.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors posA, posB, length
     * @return array{
     *     accepted: bool,
     *     kind: string,
     *     startA: int, startB: int, lengthA: int, lengthB: int,
     *     tokens: int, similarity: float,
     *     gaps: list<array{0: int, 1: int, 2: int, 3: int}>,
     *     displaced: list<array{0: int, 1: int, 2: int}>,
     *     reason: string
     * } gaps are (startA, lengthA, startB, lengthB) token ranges
     */
    public function classify(
        array $anchors,
        string $signatureA,
        string $signatureB,
        int $minTokens,
        bool $normalizedOnly,
        bool $samePair = false,
    ): array {
        $empty = [
            'accepted'   => false,
            'kind'       => 'none',
            'startA'     => 0, 'startB' => 0, 'lengthA' => 0, 'lengthB' => 0,
            'tokens'     => 0, 'similarity' => 0.0,
            'gaps'       => [], 'displaced' => [],
            'reason'     => 'no anchors',
        ];

        if ($anchors === []) {
            return $empty;
        }

        // Every anchor this call was handed, kept aside from the pruning below.
        // Building the gapped candidate's chain (a few lines down) deliberately
        // keeps only anchors compatible with *one* in-order reading, and
        // extension then searches only that reading's own flanks and gaps — so
        // an anchor that crosses the winning reading is dropped and never
        // reconsidered downstream. That is correct for finding the best ordered
        // chain, and it is exactly the evidence the reorder test (below) exists
        // to look at: a crossing anchor is not noise to discard, it is the
        // signal. So the reorder test is asked of every anchor this call was
        // given, not of whatever survived the ordered reading's own pruning.
        $allAnchors = $anchors;

        // Stage D1 runs against the *maximal colinear* set, not against the
        // scored chain, and the order matters. The scored chain has already paid
        // the gap penalty for every junction, so it drops anchors whose gap looks
        // more expensive than they are worth — and the gap looks expensive
        // precisely because the material inside it has not been recovered yet.
        // Extending first would then never see that gap at all, and the clone
        // would be reported as whichever side of it was longer.
        $colinear = $this->chains->chain($anchors, penalizeGaps: false);

        if ($colinear['indices'] === []) {
            return $empty;
        }

        // Reordering requires a crossing anchor — one the maximal colinear
        // chain above had to drop — by construction: with nothing dropped,
        // the colinear chain already *is* the full anchor set in one order,
        // so total and colinear coverage cannot differ and displaced mass is
        // zero. Recording that now skips the reorder test's own two full
        // chain passes over $allAnchors entirely in the (overwhelmingly
        // common) case they cannot change the answer — the only cost this
        // saves is redundant work, never a candidate.
        $noAnchorCrossed = count($colinear['indices']) === count($allAnchors);

        // The chain's own geometry, not the anchors it was built from: where a
        // junction was made across an overlap the chainer trimmed the start of
        // the later anchor, and extension has to search the flanks and gaps of
        // the reading that was actually chosen.
        $reachable = $colinear['chain'];

        usort($reachable, static fn(array $a, array $b): int => $a <=> $b);

        // Cross the divergences: a chain built only from seeded anchors stops
        // wherever the matching material falls under the seed length, which at a
        // clone's edges is almost always — the run after the last change is a
        // closing brace and a return, and 12 tokens cannot carry a 16-token seed.
        // Recover those runs by direct comparison and put them back in.
        $extended = $this->extend($reachable, $signatureA, $signatureB, $samePair);

        if (count($extended) > count($reachable)) {
            $anchors = $extended;
        }

        $best = $this->chains->chain($anchors, penalizeGaps: true);

        if ($best['indices'] === []) {
            return $empty;
        }

        $chained = $best['chain'];

        usort($chained, static fn(array $a, array $b): int => $a <=> $b);

        $first  = $chained[0];
        $last   = $chained[count($chained) - 1];
        $startA = $first[0];
        $startB = $first[1];
        $endA   = $last[0] + $last[2];
        $endB   = $last[1] + $last[2];

        $lengthA = $endA - $startA;
        $lengthB = $endB - $startB;
        $span    = max($lengthA, $lengthB);

        // The gaps between consecutive anchors, on both sides. These are the
        // divergences: everything the chain did not cover, bounded by exact
        // matches on either side, which is precisely what "the copies differ
        // here" means and is exactly what a reader needs to look at.
        $gaps = self::internalGaps($chained);

        // The reorder question, asked of *every* anchor this call was given —
        // see $allAnchors above — not narrowed to the gapped candidate's own
        // [startA, endA), and not limited to whatever the colinear-then-extend
        // pruning above kept. That boundary and that pruning both commit to one
        // in-order reading; a genuine reorder has no in-order reading that
        // includes the displaced block, so either restriction would hide the
        // very crossing this check exists to find.
        // $colinear is already `chain($allAnchors, penalizeGaps: false)` —
        // computed above, before $anchors was ever reassigned — so its
        // `covered` figure *is* the reorder test's colinear coverage. A
        // second identical chain pass over up to several thousand anchors
        // would cost as much as everything else classify() does combined,
        // for a number already sitting in a local variable.
        $totalCoverage = $noAnchorCrossed ? $colinear['covered'] : $this->chains->matchedCoverage($allAnchors);
        $displacedMass = $totalCoverage - $colinear['covered'];

        $reordered = $totalCoverage >= self::THETA * $span
            && $displacedMass >= self::DISPLACED_MASS_FLOOR;


        if ($reordered) {
            $displaced = $this->displacedAnchors($allAnchors);

            // The reported span widens to the full extent of the evidence, not
            // just the penalized chain's — a reorder's displaced block sits
            // *outside* that chain by construction (that is what "penalized"
            // means), so reporting the chain's own boundaries would silently
            // cut the very block the finding is about out of the clone's lines.
            $spanStartA = $startA;
            $spanEndA   = $endA;
            $spanStartB = $startB;
            $spanEndB   = $endB;

            foreach ($allAnchors as [$anchorPosA, $anchorPosB, $anchorLength]) {
                $spanStartA = min($spanStartA, $anchorPosA);
                $spanEndA   = max($spanEndA, $anchorPosA + $anchorLength);
                $spanStartB = min($spanStartB, $anchorPosB);
                $spanEndB   = max($spanEndB, $anchorPosB + $anchorLength);
            }

            return [
                'accepted'   => $totalCoverage >= self::THETA * $span && $span >= $minTokens,
                'kind'       => 'reordered',
                'startA'     => $spanStartA, 'startB' => $spanStartB,
                'lengthA'    => $spanEndA - $spanStartA, 'lengthB' => $spanEndB - $spanStartB,
                'tokens'     => $totalCoverage,
                'similarity' => $totalCoverage / $span,
                'gaps'       => $gaps,
                'displaced'  => $displaced,
                'reason'     => 'coverage ' . $totalCoverage . '/' . $span . ', displaced ' . $displacedMass,
            ];
        }

        $candidate = self::geometry($chained, $signatureA, $signatureB, $normalizedOnly);

        // **Ruling 7(b)** — attach, verify, back out; the aligner holds the last
        // word.
        //
        // `ChainBuilder`'s recorded contract is that its score *"only ranks
        // candidates in any case: whether a chain is reported is decided
        // later"*. In one narrow band it does not rank, it decides: a divergence
        // `d` followed by a trailing run `r ≤ d` loses the junction by
        // arithmetic — `r − gapA − gapB < 0` — so the chain truncates, and the
        // `minTokens` floor then refuses a pair the aligner would have accepted.
        // The traced case loses by exactly one point: a 5-token tail across a
        // 6-token deletion, on a pair whose similarity is 0.882.
        //
        // Ruling M already settled the argument for the *flanks*: the gap
        // penalty exists to arbitrate between competing readings, and a flank
        // run has nothing beyond it to bridge to, so it cannot be a competitor.
        // What §13.4 established is that completing that argument
        // **unconditionally** is a different rule and a wrong one: a flank can
        // pull a candidate past the acceptance ratio, which the suite's own
        // similarity invariant caught it doing at 0.838.
        //
        // So the attachment is a *proposal*, and {@see acceptance()} — the same
        // test `verify()` runs, the chain-witness bound and then the banded
        // aligner — decides it. An attachment the aligner refuses is backed out
        // and the chain's own reading stands. The 0.85 invariant therefore
        // cannot bend: no geometry reaches the report without passing the test
        // that enforces it, and `verify()` applies it a second time downstream.
        //
        // `ChainBuilder` and its brute-force oracle are untouched. The change is
        // entirely in this consumer, which is what the ruling requires: the
        // scorer keeps its theorem and stops being the last word about it.
        // **When** an attachment is proposed, and why not always.
        //
        // The scorer's contract is that it *ranks*. Where both readings are
        // acceptable it is doing exactly that — choosing between two valid
        // descriptions of one pair — and the ruling names no defect there. It
        // stops ranking and starts **deciding** at exactly one place: when its
        // truncation drops the candidate under `minTokens`, so that the floor
        // then refuses a pair the aligner would have accepted. Both traced
        // misses sit there, and the request's own framing is that "in this band
        // it does not rank, it decides".
        //
        // So the proposal is made only where the decision is the scorer's to
        // lose. Measured, because the alternative was implemented first and the
        // numbers are the argument (M5 packet §5.3): proposing unconditionally
        // moves phpunit 581 → 629 clones and firefly-iii 617 → 714, swallowing
        // shared preambles into spans the scorer had deliberately not taken —
        // and costs 4.2 s → 159.0 s on phpunit, a 38× regression that ruling U's
        // speed bar would not survive. Restricted to the band, the extra
        // alignments are paid only on candidates the engine was about to throw
        // away, which are short by definition.
        if (max($lengthA, $lengthB) >= $minTokens) {
            return $candidate;
        }

        foreach ($this->flankAttachments($chained, $anchors) as $attachment) {
            $attempt = self::geometry($attachment, $signatureA, $signatureB, $normalizedOnly);

            // The other half of the band: the attachment has to actually clear
            // the floor. `span − r < minTokens ≤ span` is the ruling's own
            // statement of it, and an attachment that lands still under the floor
            // is one `verify()` would refuse a moment later — so it is not worth
            // an alignment to find that out.
            if (max($attempt['lengthA'], $attempt['lengthB']) < $minTokens) {
                continue;
            }

            if ($this->acceptance($attempt, $signatureA, $signatureB)[0]) {
                return $attempt;
            }
        }

        return $candidate;
    }

    /**
     * The gaps between consecutive anchors of a chain, on both sides.
     *
     * These are the divergences: everything the chain did not cover, bounded by
     * exact matches on either side, which is precisely what "the copies differ
     * here" means and is exactly what a reader needs to look at.
     *
     * @param  list<array{0: int, 1: int, 2: int}> $chained
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    private static function internalGaps(array $chained): array
    {
        $gaps = [];

        for ($i = 1; $i < count($chained); $i++) {
            $previous = $chained[$i - 1];
            $next     = $chained[$i];

            $gapStartA  = $previous[0] + $previous[2];
            $gapStartB  = $previous[1] + $previous[2];
            $gapLengthA = $next[0] - $gapStartA;
            $gapLengthB = $next[1] - $gapStartB;

            if ($gapLengthA > 0 || $gapLengthB > 0) {
                $gaps[] = [$gapStartA, $gapLengthA, $gapStartB, $gapLengthB];
            }
        }

        return $gaps;
    }

    /**
     * The unverified candidate one chain describes: its span, its gaps, its
     * bounded edge divergences, and what kind of clone it would be.
     *
     * Extracted so the chain's own reading and a flank-attached proposal are
     * built by one piece of code. Two readings assembled by two pieces of code
     * would eventually disagree about something other than their flanks.
     *
     * Bounded edge divergence (M2 audit ruling C): the chain's own boundaries
     * are exact by construction, but a maximal span whose *continuation* differs
     * on the two sides — one copy simply has more material past the shared part
     * than the other — is real divergence too, and plan §1 Stage D3 originally
     * excluded it by requiring gaps to be internal. Reported only under the
     * asymmetry bound in {@see edgeDivergences()}; the core span and its
     * verification are untouched by it either way.
     *
     * @param  list<array{0: int, 1: int, 2: int}> $chained ascending in both files
     * @return array{accepted: bool, kind: string, startA: int, startB: int, lengthA: int, lengthB: int, tokens: int, similarity: float, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>, reason: string}
     */
    private static function geometry(array $chained, string $signatureA, string $signatureB, bool $normalizedOnly): array
    {
        $first  = $chained[0];
        $last   = $chained[count($chained) - 1];
        $startA = $first[0];
        $startB = $first[1];

        $lengthA = ($last[0] + $last[2]) - $startA;
        $lengthB = ($last[1] + $last[2]) - $startB;

        $gaps = [...self::internalGaps($chained), ...self::edgeDivergences(
            (int) (strlen($signatureA) / self::TOKEN_BYTES),
            (int) (strlen($signatureB) / self::TOKEN_BYTES),
            $startA,
            $lengthA,
            $startB,
            $lengthB,
        )];

        return [
            'accepted'   => false,
            'kind'       => $gaps === [] ? ($normalizedOnly ? 'type-2' : 'type-1') : 'gapped',
            'startA'     => $startA, 'startB' => $startB,
            'lengthA'    => $lengthA, 'lengthB' => $lengthB,
            'tokens'     => min($lengthA, $lengthB),
            'similarity' => 0.0,
            'gaps'       => $gaps,
            'displaced'  => [],
            'reason'     => 'pending verification',
        ];
    }

    /**
     * The flank-attached readings to propose, widest first.
     *
     * An **outer flank** is a run lying entirely outside the chain on both sides
     * — before its first anchor, or after its last — and **within the reach
     * extension itself searched**: one span back from the chain's start, one span
     * forward from its end. That reach is not a new bound, it is
     * {@see extend()}'s own definition of a flank region, and requiring it is
     * what keeps this mechanism to the object ruling M's argument is about.
     *
     * The requirement is load-bearing, and it was found by measurement rather
     * than reasoned out in advance. Without it, any *colinear anchor the
     * penalized chain deliberately dropped* qualifies as a flank, however far
     * away it sits — and on php-parser that produced 591 alignments at a mean
     * span of 790 tokens, 14.5 s of dynamic programming on a corpus that scans
     * in under a second. A distant dropped anchor is not a flank that extension
     * recovered; it is a competing chain element, and the gap penalty exists
     * precisely to arbitrate those. Attaching one is the rule ruling M's argument
     * does *not* license.
     *
     * Only the nearest run in each direction is proposed, which bounds this to at
     * most three extra alignments per candidate instead of a search over every
     * dropped run.
     *
     * Three proposals, in decreasing span: both flanks, the trailing one alone,
     * the leading one alone. Backing out from two attachments to one is still
     * backing out, and trying the wider reading first means the narrower one is
     * only ever reached when the aligner has actually refused the wider.
     * Candidates with no dropped outer flank — the overwhelming majority —
     * produce no proposals and cost nothing.
     *
     * @param  list<array{0: int, 1: int, 2: int}> $chained ascending in both files
     * @param  list<array{0: int, 1: int, 2: int}> $anchors every run available, chained or not
     * @return list<list<array{0: int, 1: int, 2: int}>>
     */
    private function flankAttachments(array $chained, array $anchors): array
    {
        $first = $chained[0];
        $last  = $chained[count($chained) - 1];

        $tailA = $last[0] + $last[2];
        $tailB = $last[1] + $last[2];

        // Extension's own reach, restated from {@see extend()}: one span back,
        // one span forward.
        $span   = max($tailA - $first[0], $tailB - $first[1]);
        $reachA = $first[0] - $span;
        $reachB = $first[1] - $span;

        $leading  = null;
        $trailing = null;

        foreach ($anchors as $anchor) {
            [$posA, $posB, $length] = $anchor;
            $endA = $posA + $length;
            $endB = $posB + $length;

            // Before the chain on both sides, inside the flank region extension
            // searched — and nearest means the largest end. Ties break on the
            // full triple, so the choice is total and two runs produce one
            // answer.
            if ($endA <= $first[0] && $endB <= $first[1] && $posA >= $reachA && $posB >= $reachB
                && ($leading === null || [$endA, $endB, $anchor] > [$leading[0] + $leading[2], $leading[1] + $leading[2], $leading])) {
                $leading = $anchor;
            }

            // After the chain on both sides, inside the trailing flank region —
            // nearest means the smallest start.
            if ($posA >= $tailA && $posB >= $tailB && $endA <= $tailA + $span && $endB <= $tailB + $span
                && ($trailing === null || [$posA, $posB, $anchor] < [$trailing[0], $trailing[1], $trailing])) {
                $trailing = $anchor;
            }
        }

        if ($leading === null && $trailing === null) {
            return [];
        }

        $proposals = [];

        if ($leading !== null && $trailing !== null) {
            $proposals[] = [$leading, ...$chained, $trailing];
        }

        if ($trailing !== null) {
            $proposals[] = [...$chained, $trailing];
        }

        if ($leading !== null) {
            $proposals[] = [$leading, ...$chained];
        }

        return $proposals;
    }

    /**
     * Bounded edge divergence at both edges of a span (M2 audit ruling C).
     *
     * A span whose continuation differs on the two sides is flagged only
     * under an asymmetry bound: one side's remainder beyond the span (to its
     * file end) is at most {@see BandedAligner::budgetFor()} of the span,
     * while the other side's exceeds it. Without the bound every clone would
     * "diverge" into the rest of both files; with it, a pair that merely
     * continues differently on both sides — both remainders exceeding the
     * bound — is correctly left alone, which is the paired negative the
     * ruling requires. Checked at both edges independently: leading (back to
     * file start) and trailing (forward to file end).
     *
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    private static function edgeDivergences(int $tokensA, int $tokensB, int $startA, int $lengthA, int $startB, int $lengthB): array
    {
        $bound = BandedAligner::budgetFor(max($lengthA, $lengthB));
        $edges = [];

        $leading = self::edgeDivergence($bound, $startA, $startA, $startB, $startB, forward: false);

        if ($leading !== null) {
            $edges[] = $leading;
        }

        $tailFromA = $startA + $lengthA;
        $tailFromB = $startB + $lengthB;
        $trailing  = self::edgeDivergence($bound, $tailFromA, $tokensA - $tailFromA, $tailFromB, $tokensB - $tailFromB, forward: true);

        if ($trailing !== null) {
            $edges[] = $trailing;
        }

        return $edges;
    }

    /**
     * One direction's asymmetry check. `$remainderA`/`$remainderB` are the
     * tokens available past the span in this direction; `$edgeA`/`$edgeB` are
     * the span's own boundary in this direction, which the reported ranges
     * are measured from. Returns the (startA, lengthA, startB, lengthB) to
     * report, or null when the two sides are not asymmetric under the bound.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}|null
     */
    private static function edgeDivergence(int $bound, int $edgeA, int $remainderA, int $edgeB, int $remainderB, bool $forward): ?array
    {
        $short = min($remainderA, $remainderB);
        $long  = max($remainderA, $remainderB);

        // The short side must be small enough to be a mere trailing-off, and
        // the long side must be large enough that the difference is not
        // itself a small, roughly-symmetric wobble both sides equally have.
        if ($short > $bound || $long <= $bound) {
            return null;
        }

        $lengthA = min($remainderA, $bound);
        $lengthB = min($remainderB, $bound);

        return [
            $forward ? $edgeA : $edgeA - $lengthA,
            $lengthA,
            $forward ? $edgeB : $edgeB - $lengthB,
            $lengthB,
        ];
    }

    /**
     * Verify a non-reordered candidate against the token streams.
     *
     * A gapless candidate needs no alignment: its two spans are one exact match
     * by construction, so the distance is zero and computing it would be work to
     * confirm what Stage C already proved with a memcmp.
     *
     * `$normalizedOnly` applies the M2 audit ruling B backstop: a candidate
     * built only from the normalized view is rejected if its own span falls
     * below {@see Winnower::NORMALIZED_DIVERSITY_FLOOR} distinct tokens, even
     * though it was accepted at the index — Stage D1's direct-comparison
     * extension (see {@see extend()}) compares bytes, not diversity, and can
     * pull a low-diversity run in from beside a seed the guard let through.
     * The raw view never receives this argument as true, so it is never
     * checked there.
     *
     * @param array{accepted: bool, kind: string, startA: int, startB: int, lengthA: int, lengthB: int, tokens: int, similarity: float, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>, reason: string} $candidate
     * @return array{accepted: bool, kind: string, startA: int, startB: int, lengthA: int, lengthB: int, tokens: int, similarity: float, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>, reason: string}
     */
    public function verify(array $candidate, string $signatureA, string $signatureB, int $minTokens, int $minLines, int $lines, bool $normalizedOnly = false): array
    {
        if ($candidate['kind'] === 'reordered' || $candidate['kind'] === 'none') {
            $candidate['accepted'] = $candidate['accepted'] && $lines >= $minLines;

            return $candidate;
        }

        $span = max($candidate['lengthA'], $candidate['lengthB']);

        if ($span < $minTokens || $lines < $minLines) {
            $candidate['accepted'] = false;
            $candidate['reason']   = 'below thresholds';

            return $candidate;
        }

        if (
            $normalizedOnly
            && self::distinctTokenCount($signatureA, $candidate['startA'], $candidate['lengthA']) < Winnower::NORMALIZED_DIVERSITY_FLOOR
        ) {
            $candidate['accepted'] = false;
            $candidate['reason']   = 'below the normalized diversity floor';

            return $candidate;
        }

        [$accepted, $similarity, $reason] = $this->acceptance($candidate, $signatureA, $signatureB);

        $candidate['accepted']   = $accepted;
        $candidate['similarity'] = $similarity;
        $candidate['tokens']     = $candidate['gaps'] === [] ? $candidate['lengthA'] : $span;
        $candidate['reason']     = $reason;

        if ($accepted && !$normalizedOnly) {
            return $this->refineAsReorder($candidate, $signatureA, $signatureB);
        }

        return $candidate;
    }

    /**
     * Does this candidate's own evidence accept it — and how similar are the two
     * spans?
     *
     * The whole of the similarity verdict, in one place, because two callers ask
     * it: {@see verify()}, which is the shipped verdict, and {@see classify()},
     * which asks it of a flank attachment before proposing one (ruling 7(b)).
     * The attachment gate and the shipped verdict have to be the same test or
     * the invariant the suite pins is only enforced on one path.
     *
     * A **gapless** candidate needs no alignment: its two spans are one exact
     * match by construction, so the distance is zero and computing it would be
     * work to confirm what Stage C already proved with a memcmp.
     *
     * The **chain is already an alignment**, and sometimes it is proof enough.
     * Between the two spans it interleaves exact runs with gaps, and each gap can
     * be closed by substituting across the shorter side and inserting or deleting
     * the difference — max(gapA, gapB) edits, never more. So the chain
     * *witnesses* an edit distance of at most the sum of those maxima, and when
     * that sum is within the budget the pair is accepted with no dynamic program
     * run at all. This is not an estimate standing in for the aligner: it is a
     * bound in the direction that decides the question, so what the DP could
     * still do is lower the distance further, and the verdict cannot change.
     *
     * What it saves is the whole quadratic term. The aligner doubles its band up
     * to the budget, costing O(RATIO · span²), which on phpunit's
     * `MetadataTest.php` is nine alignments of 12,000 to 25,000 tokens — 60
     * seconds of dynamic programming, every one of them returning `accepted`,
     * every one already proved by its own chain.
     *
     * A candidate whose gaps exceed the budget still goes to the **aligner**,
     * because there the chain proves nothing: the DP can match material inside a
     * gap that Stage B never seeded and {@see extend()} did not reach, and it is
     * exactly then that its answer is not already known.
     *
     * @param  array{accepted: bool, kind: string, startA: int, startB: int, lengthA: int, lengthB: int, tokens: int, similarity: float, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>, reason: string} $candidate
     * @return array{0: bool, 1: float, 2: string} accepted, similarity, reason
     */
    private function acceptance(array $candidate, string $signatureA, string $signatureB): array
    {
        if ($candidate['gaps'] === []) {
            return [true, 1.0, 'exact'];
        }

        $span      = max($candidate['lengthA'], $candidate['lengthB']);
        $uncovered = 0;

        foreach ($candidate['gaps'] as [$gapStartA, $gapLengthA, , $gapLengthB]) {
            // Edge divergences (ruling C) sit outside the span being aligned and
            // are not the aligner's business.
            if ($gapStartA < $candidate['startA'] || $gapStartA >= $candidate['startA'] + $candidate['lengthA']) {
                continue;
            }

            $uncovered += max($gapLengthA, $gapLengthB);
        }

        if ($uncovered <= BandedAligner::budgetFor($span)) {
            // A lower bound, from the witness rather than from an exact
            // distance. Nothing downstream reports this number — it decides
            // acceptance and stops there — and it can only understate how
            // similar the two spans are.
            return [
                true,
                1.0 - ($uncovered / $span),
                'chain witnesses distance at most ' . $uncovered . ' of ' . BandedAligner::budgetFor($span),
            ];
        }

        $alignment = $this->aligner->compare(
            $signatureA,
            $candidate['startA'],
            $candidate['lengthA'],
            $signatureB,
            $candidate['startB'],
            $candidate['lengthB'],
        );

        return [
            $alignment['accepted'],
            $alignment['similarity'],
            $alignment['accepted']
                ? 'similarity ' . $alignment['similarity']
                : self::REFUSED_ON_SIMILARITY . ' ' . (1.0 - BandedAligner::RATIO),
        ];
    }

    /**
     * A gapped clone whose material is all present, merely moved, is a reorder —
     * ruling R (2) applied to the reorder test's refusal rather than the
     * aligner's.
     *
     * {@see classify()}'s reorder test is anchor-based, so it cannot see a
     * displacement shorter than K = 16 tokens: below that there is no run long
     * enough to be seeded on either side of the move, and the pair is correctly
     * — under that evidence — read as a substitution. That is exactly ruling A's
     * algebra and exactly the class ruling R exists to reach, and fixture r1 is
     * the case: two swapped 3-token statements, reported as a substitution
     * because nothing seeded could say otherwise.
     *
     * Bag evidence can say otherwise, so it is asked. Nothing about the pair's
     * *acceptance* changes — the span, the gaps and the verdict all stand — only
     * its **kind**, and only when the material is bijectively present at θ and
     * the moved blocks can be named. A finding that changes name but not extent
     * consumes no evidence, so ruling O is not in play here.
     *
     * @param array{accepted: bool, kind: string, startA: int, startB: int, lengthA: int, lengthB: int, tokens: int, similarity: float, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>, reason: string} $candidate
     * @return array{accepted: bool, kind: string, startA: int, startB: int, lengthA: int, lengthB: int, tokens: int, similarity: float, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>, reason: string}
     */
    private function refineAsReorder(array $candidate, string $signatureA, string $signatureB): array
    {
        if ($candidate['kind'] !== 'gapped') {
            return $candidate;
        }

        $orderFree = $this->orderFreeVerdict($candidate, $signatureA, $signatureB);

        if ($orderFree === null) {
            return $candidate;
        }

        $candidate['kind']      = 'reordered';
        $candidate['displaced'] = $orderFree['displaced'];
        $candidate['reason']   .= '; order-free coverage ' . $orderFree['similarity'];

        return $candidate;
    }

    /**
     * The second verdict — ruling R, integrated shape (2).
     *
     * An aligner refusal is a statement about *order*: taken as one colinear
     * alignment these two spans cost more than the budget allows. Ruling O
     * already established that this is the one refusal whose parts inherit
     * nothing from it, and drew the consequence for sub-spans. This is the same
     * observation carried one step further: if the refusal says nothing about the
     * parts, it also says nothing about whether the parts are *all there in a
     * different arrangement*, and that is a different question about the same
     * evidence — so it is asked here rather than in a sibling pass.
     *
     * The question is bijective bag coverage at {@see THETA}, constraint 3's
     * verifier, with the same θ and the same provenance the reordered path
     * already uses. Occurrences match one-to-one, so a repetitive span cannot
     * cover itself into an acceptance — the M2 lesson that killed one-sided
     * coverage.
     *
     * Constraint 5 is enforced here rather than assumed: a verdict with no
     * displaced run is not emitted at all. "The material is all present" without
     * a place to point at is not a finding a reader can act on, and a pair that
     * is order-free equivalent *and* has nothing displaced is a pair the
     * colinear path should have accepted — so the refusal, not the bag, is the
     * thing to trust there.
     *
     * ## Why it answers *beside* the refusal rather than replacing it
     *
     * Ruling O's whole content is that a similarity refusal must not consume the
     * evidence inside it. An order-free acceptance that *replaced* the refusal
     * would consume it just as thoroughly — the decomposition would never run,
     * and the exact clones inside the refused reading would be lost again. This
     * was not a hypothetical: converting the refusal cost phpunit's Rabin-Karp
     * location gate the one location ruling O had recovered. So the verdict is
     * returned as an addition and the refusal stays a refusal.
     *
     * @param array{accepted: bool, kind: string, startA: int, startB: int, lengthA: int, lengthB: int, tokens: int, similarity: float, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>, reason: string} $candidate
     * @return ?array{accepted: bool, kind: string, startA: int, startB: int, lengthA: int, lengthB: int, tokens: int, similarity: float, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>, reason: string}
     */
    public function orderFreeVerdict(array $candidate, string $signatureA, string $signatureB): ?array
    {
        $left  = ShingleBags::shingles($signatureA, $candidate['startA'], $candidate['lengthA']);
        $right = ShingleBags::shingles($signatureB, $candidate['startB'], $candidate['lengthB']);

        if ($left === [] || $right === []) {
            return null;
        }

        $coverage = ShingleBags::coverage(
            array_column($left, 1),
            array_column($right, 1),
        );

        if ($coverage < self::THETA) {
            return null;
        }

        $displaced = ShingleBags::displacedRuns($left, $right);

        if ($displaced === []) {
            return null;
        }

        $candidate['accepted']   = true;
        $candidate['kind']       = 'reordered';
        $candidate['similarity'] = $coverage;
        $candidate['displaced']  = $displaced;
        $candidate['reason']     = 'order-free coverage ' . $coverage . ' at theta ' . self::THETA;

        return $candidate;
    }

    /**
     * Prefix of the one refusal reason the aligner issues.
     *
     * A caller has to be able to tell *this* refusal from the others, because
     * it is the only one that says nothing about the candidate's parts: "these
     * two spans are too dissimilar taken as one alignment" leaves entirely open
     * whether some sub-span is a clone. Falling below `minTokens`, or below the
     * normalized diversity floor, are judgements the parts inherit. See
     * {@see UnifiedStrategy::splitAtWidestGap()}.
     */
    public const string REFUSED_ON_SIMILARITY = 'similarity below';

    /**
     * Was this candidate refused by the aligner, rather than by a rule its
     * sub-spans would fail too?
     *
     * @param array{accepted: bool, reason: string, ...} $candidate
     */
    public static function refusedOnSimilarity(array $candidate): bool
    {
        return !$candidate['accepted'] && str_starts_with($candidate['reason'], self::REFUSED_ON_SIMILARITY);
    }

    /**
     * Stage D1 — gapped extension: recover the matching runs a seed cannot reach.
     *
     * Three regions are examined per chain: the flank before it, each gap inside
     * it, and the flank after it. In every one of them the two sides are compared
     * directly, from both ends, and the runs found are added to the anchor set:
     *
     *   - a **common prefix** of a region is material that continues to match
     *     after the anchor before it — the near side of a divergence;
     *   - a **common suffix** is material that matches again before the anchor
     *     after it — the far side.
     *
     * A gap with both is a divergence with agreement on either side, which is the
     * shape this engine exists to report; what is left between the two runs is the
     * divergence itself, exact on both sides and no longer inferred from where a
     * seed happened to land.
     *
     * Flanks are bounded by the chain's own length, so extension can at most
     * double a candidate and cannot walk out of one clone into the next; whatever
     * it produces is then verified by {@see BandedAligner} like anything else.
     *
     * Comparison rather than dynamic programming here is deliberate. The plan
     * reaches this material with a banded DP and reads the divergence off the
     * alignment; the runs recovered by direct comparison are the *same* material
     * — maximal exact matches bounding the same gap — and they arrive already
     * exact, where a traceback would have to be read back into token ranges. The
     * DP still decides whether the result is a clone.
     *
     * The minimum a recovered run has to reach is a *gap* rule, and M3 audit
     * ruling M exempts the two outer flanks from it. {@see MINIMUM_RECOVERED_RUN}
     * exists because a short spurious run inside a gap bridges two regions that
     * are not otherwise related, buying coverage across a divergence with `);`
     * and `}`. A flank run bridges nothing: there is no anchor beyond it to reach,
     * so it either extends the candidate's own edge or it does not exist. Plan §1
     * Stage D1 says so directly — "longest common prefix and suffix at each flank"
     * carries no minimum, while the gap-interior search does. This is the gap
     * between the specification and the implementation closed, not a constant
     * loosened: the constant still governs every gap.
     *
     * On a file compared against itself, a recovered run at offset zero is
     * discarded. {@see AnchorSet} already refuses those when it seeds — a pair is
     * generated only for `posA < posB`, "which is what keeps a run from being
     * found against itself at offset zero" — but extension searches flanks and
     * gaps by direct comparison, and the two sides of a self-pair are the same
     * string, so every such search trivially matches the file against itself.
     * Those identity runs are not evidence of duplication and they are not
     * harmless: chained together they form a whole-file candidate at shift 0 that
     * consumes a shift band's anchors and reports nothing, which is what stopped
     * the periodic walk in {@see UnifiedStrategy::fromCluster()} after one step.
     * The rule is Stage C's own, applied where it was missing.
     *
     * @param list<array{0: int, 1: int, 2: int}> $chained ascending in both files
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private function extend(array $chained, string $signatureA, string $signatureB, bool $samePair = false): array
    {
        $tokensA = (int) (strlen($signatureA) / self::TOKEN_BYTES);
        $tokensB = (int) (strlen($signatureB) / self::TOKEN_BYTES);

        $first = $chained[0];
        $last  = $chained[count($chained) - 1];
        $span  = max(
            ($last[0] + $last[2]) - $first[0],
            ($last[1] + $last[2]) - $first[1],
        );

        // Each region carries whether it is one of the two outer flanks: the
        // minimum a recovered run must reach is a gap rule, and ruling M exempts
        // the flanks from it. See the docblock.
        $regions = [];

        // The flank before the chain, reaching back at most one span.
        $headA = max(0, $first[0] - $span);
        $headB = max(0, $first[1] - $span);

        if ($first[0] > $headA && $first[1] > $headB) {
            $regions[] = [$headA, $first[0] - $headA, $headB, $first[1] - $headB, true];
        }

        // Every gap inside the chain.
        for ($i = 1; $i < count($chained); $i++) {
            $previous = $chained[$i - 1];
            $next     = $chained[$i];

            $fromA   = $previous[0] + $previous[2];
            $fromB   = $previous[1] + $previous[2];
            $lengthA = $next[0] - $fromA;
            $lengthB = $next[1] - $fromB;

            if ($lengthA > 0 && $lengthB > 0) {
                $regions[] = [$fromA, $lengthA, $fromB, $lengthB, false];
            }
        }

        // The flank after the chain, reaching forward at most one span.
        $tailA = $last[0] + $last[2];
        $tailB = $last[1] + $last[2];
        $availA = min($tokensA - $tailA, $span);
        $availB = min($tokensB - $tailB, $span);

        if ($availA > 0 && $availB > 0) {
            $regions[] = [$tailA, $availA, $tailB, $availB, true];
        }

        $recovered = $chained;

        // A run of a self-pair at offset zero is the file against itself; see the
        // docblock. Stated once here so every recovery path below goes through it.
        $keep = static fn(int $startA, int $startB): bool => !$samePair || $startA !== $startB;

        foreach ($regions as [$fromA, $lengthA, $fromB, $lengthB, $isFlank]) {
            // Ruling M: the flanks answer to §1's "longest common prefix and
            // suffix", which sets no floor; the gaps keep theirs.
            $minimum = $isFlank ? 1 : self::MINIMUM_RECOVERED_RUN;

            $prefix = self::commonPrefix($signatureA, $fromA, $signatureB, $fromB, min($lengthA, $lengthB));

            if ($prefix >= $minimum && $keep($fromA, $fromB)) {
                $recovered[] = [$fromA, $fromB, $prefix];
            }

            // The suffix is measured from the far end of each side, and is only
            // taken as far as the prefix left room for — otherwise a region that
            // matches throughout would be claimed twice, once from each end.
            $room   = min($lengthA, $lengthB) - $prefix;
            $suffix = $room <= 0
                ? 0
                : self::commonSuffix($signatureA, $fromA + $lengthA, $signatureB, $fromB + $lengthB, $room);

            if ($suffix >= $minimum && $keep($fromA + $lengthA - $suffix, $fromB + $lengthB - $suffix)) {
                $recovered[] = [$fromA + $lengthA - $suffix, $fromB + $lengthB - $suffix, $suffix];
            }

            // Neither end matched, but the middle still can: an edit on each side
            // of a short shared run leaves that run stranded, touching nothing at
            // either boundary and too short to have carried a seed of its own.
            // That is the shape a statement inserted before *and* after a couple
            // of surviving lines makes, and without this the clone is reported as
            // whichever half is longer.
            if ($prefix === 0 && $suffix === 0) {
                $middle = self::longestCommonRun($signatureA, $fromA, $lengthA, $signatureB, $fromB, $lengthB);

                if ($middle !== null && $keep($middle[0], $middle[1])) {
                    $recovered[] = $middle;
                }
            }
        }

        return $recovered;
    }

    /**
     * The longest run of tokens shared by two regions, wherever it sits in them.
     *
     * The classical longest-common-substring dynamic program, over two rows. It is
     * quadratic, which is why it runs only inside a gap — a region bounded by
     * anchors on both sides and therefore small — and only when the cheap checks
     * at the region's two ends have already failed. {@see GAP_SEARCH_CELLS} caps
     * the product so that one pathologically wide gap cannot turn a candidate-
     * sized step into a corpus-sized one; a gap over the cap keeps the behaviour
     * this method exists to improve on, which is to say the divergence stays
     * whole.
     *
     * @return array{0: int, 1: int, 2: int}|null an anchor: startA, startB, length
     */
    private static function longestCommonRun(string $a, int $fromA, int $lengthA, string $b, int $fromB, int $lengthB): ?array
    {
        if ($lengthA < self::MINIMUM_RECOVERED_RUN || $lengthB < self::MINIMUM_RECOVERED_RUN) {
            return null;
        }

        if ($lengthA > self::GAP_SEARCH_SPAN || $lengthB > self::GAP_SEARCH_SPAN) {
            return null;
        }

        $tokensA = str_split(substr($a, $fromA * self::TOKEN_BYTES, $lengthA * self::TOKEN_BYTES), self::TOKEN_BYTES);
        $tokensB = str_split(substr($b, $fromB * self::TOKEN_BYTES, $lengthB * self::TOKEN_BYTES), self::TOKEN_BYTES);

        $previous  = array_fill(0, $lengthB + 1, 0);
        $best      = 0;
        $bestEndA  = 0;
        $bestEndB  = 0;

        for ($i = 1; $i <= $lengthA; $i++) {
            $current = array_fill(0, $lengthB + 1, 0);

            for ($j = 1; $j <= $lengthB; $j++) {
                if ($tokensA[$i - 1] !== $tokensB[$j - 1]) {
                    continue;
                }

                $run         = $previous[$j - 1] + 1;
                $current[$j] = $run;

                // Ties keep the earliest run, so the recovered anchor is a
                // function of the two regions and not of the scan order.
                if ($run > $best) {
                    $best     = $run;
                    $bestEndA = $i;
                    $bestEndB = $j;
                }
            }

            $previous = $current;
        }

        if ($best < self::MINIMUM_RECOVERED_RUN) {
            return null;
        }

        return [$fromA + $bestEndA - $best, $fromB + $bestEndB - $best, $best];
    }

    /** How many tokens match forward from these two positions, at most $limit. */
    private static function commonPrefix(string $a, int $fromA, string $b, int $fromB, int $limit): int
    {
        $matched = 0;

        while (
            $matched < $limit
            && substr($a, ($fromA + $matched) * self::TOKEN_BYTES, self::TOKEN_BYTES)
                === substr($b, ($fromB + $matched) * self::TOKEN_BYTES, self::TOKEN_BYTES)
        ) {
            $matched++;
        }

        return $matched;
    }

    /** How many tokens match backward from these two ends, at most $limit. */
    private static function commonSuffix(string $a, int $endA, string $b, int $endB, int $limit): int
    {
        $matched = 0;

        while (
            $matched < $limit
            && substr($a, ($endA - $matched - 1) * self::TOKEN_BYTES, self::TOKEN_BYTES)
                === substr($b, ($endB - $matched - 1) * self::TOKEN_BYTES, self::TOKEN_BYTES)
        ) {
            $matched++;
        }

        return $matched;
    }

    /**
     * How many distinct tokens a span contains — the M2 ruling B verification
     * backstop's own measure, over the *whole* accepted span rather than a
     * sliding K-window: a candidate is rejected only if it carries too little
     * information taken as a whole, which is the failure the guard at the index
     * cannot always reach (see {@see verify()}).
     */
    private static function distinctTokenCount(string $signature, int $start, int $length): int
    {
        if ($length <= 0) {
            return 0;
        }

        $seen = [];

        for ($t = 0; $t < $length; $t++) {
            $seen[substr($signature, ($start + $t) * self::TOKEN_BYTES, self::TOKEN_BYTES)] = true;
        }

        return count($seen);
    }

    /**
     * The anchors a reordered candidate's best in-order chain had to drop.
     *
     * These are the statements that moved — the thing a token bag cannot say. It
     * sees the same multiset either way and reports the region without being able
     * to name a single line inside it that is responsible.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private function displacedAnchors(array $anchors): array
    {
        $kept = [];

        foreach ($this->chains->chain($anchors, penalizeGaps: false)['indices'] as $index) {
            $kept[$index] = true;
        }

        $displaced = [];

        foreach ($anchors as $index => $anchor) {
            if (!isset($kept[$index])) {
                $displaced[] = $anchor;
            }
        }

        usort($displaced, static fn(array $a, array $b): int => $a <=> $b);

        return $displaced;
    }
}
