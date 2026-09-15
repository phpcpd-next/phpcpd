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

namespace LucianoPereira\PhpcpdNext\Tests;

use function array_map;
use function array_values;
use function basename;
use function count;
use function file_get_contents;
use function intdiv;
use function max;
use function min;
use function range;
use function sort;
use function sprintf;
use function str_split;
use function strlen;
use function substr;

use LucianoPereira\PhpcpdNext\CloneDivergence;
use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\BandedAligner;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\CloneClassifier;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * M2 probe fixtures and the §6-ruling property tests.
 *
 * The probe fixtures (`tests/fixtures/probes/`) attack the design's weakest
 * claims one at a time, each generated so its divergence survives
 * normalization — statements differ in shape and keyword, not only in
 * variable names, since the normalized view collapses renames and a
 * single-shape fixture would test the fixture rather than the engine:
 *
 *   1. edge          — divergence at the clone's first and last statement,
 *                       symmetric on both sides; documented as out of the
 *                       bounded-edge-divergence rule's scope (M2 ruling C).
 *   2. twingap        — twin gaps with a 12-token middle run, shorter than
 *                       the seed; extension must cross both.
 *   3. dense          — an edit every ~12 tokens, denser than the guarantee;
 *                       documented miss.
 *   4. reorder        — a 24/49-token block swap; the restated displaced-mass
 *                       rule (M2 ruling A) must fire.
 *   5. edgeext/edgeneg — bounded edge divergence (M2 ruling C): one copy
 *                       simply has more material past a shared core than the
 *                       other (edgeext, positive) versus both copies merely
 *                       continuing differently (edgeneg, the required paired
 *                       negative).
 */
#[CoversClass(CloneClassifier::class)]
final class UnifiedProbesTest extends TestCase
{
    private const string PROBES = __DIR__ . '/fixtures/probes';

    private static function config(int $minTokens = 50, int $minLines = 5): StrategyConfiguration
    {
        return new StrategyConfiguration(
            minLines: $minLines,
            minTokens: $minTokens,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );
    }

    /**
     * The probes are written at a threshold expressing a certain amount of
     * code, not a certain number of tokens. A token includes punctuation now
     * and is worth about half what it was, so the same code is a hundred
     * tokens where it was fifty — which is also the shipped default, chosen on
     * the same ratio and by the same reasoning.
     */
    private function detect(array $files, int $minTokens = 100): CodeCloneMap
    {
        return (new Engine(self::config($minTokens), 'unified'))->detect($files);
    }

    /** @return list<CodeClone> */
    private function clonesFor(string $baseName, string $variantName, int $minTokens = 100): array
    {
        $map = $this->detect([self::PROBES . '/' . $baseName, self::PROBES . '/' . $variantName], $minTokens);

        return array_values($map->clones());
    }

    // ── Probe 1: edge divergence, symmetric — out of the edge-divergence rule's scope ──

    /**
     * **Re-derived under ruling 7(b)**, as that ruling requires — not assumed to
     * survive. The derivation lands on the number it started from, and the
     * *reason* it does is the part worth recording.
     *
     * *What this probe is for.* Both the leading and the trailing remainder are
     * one statement's worth in **each** file, so neither edge is *asymmetric*
     * under M2 ruling C's bound: the short side never clears the "the other side
     * is larger" half of the test, and the bounded edge-divergence rule must not
     * fire. The clone is reported as its exact core.
     *
     * *Why ruling 7(b) does not reach it.* The two files share three exact runs,
     * not one: a 10-token preamble (`declare` … the function signature), the
     * 60-token core, and a 2-token tail (`return $seed;`). The chain scorer drops
     * both outer runs — the preamble repays 10 across a 6/5-token divergence and
     * the tail repays 2 across another — and reports the core alone.
     *
     * Ruling 7(b) proposes an outer flank only where the scorer's truncation
     * **decides** rather than ranks: where the truncated span falls under
     * `minTokens` and the floor then refuses a pair the aligner would have
     * accepted. Here the core is 60 tokens against a `minTokens` of 50, so both
     * readings are acceptable and the scorer is doing exactly its job — choosing
     * between two valid descriptions of one pair. No proposal is made and the
     * expectation is unchanged.
     *
     * *What the other reading would have been, measured rather than guessed.*
     * With the proposal made unconditionally this fixture reports **84 tokens**
     * (10 + 6 + 60 + 6 + 2 on the base side), gapped, with four divergence
     * entries and — still — no bounded edge divergence. That reading is
     * defensible and it is not the one taken: on the corpora it swallows shared
     * preambles into spans the scorer had deliberately not taken (phpunit 581 →
     * 629 clones) at 38× the wall-clock, which is the measurement recorded in the
     * M5 packet §5.3.
     */
    #[Test]
    public function probe1_edge_divergence_at_both_ends_is_not_flagged(): void
    {
        $clones = $this->clonesFor('edge_base.php', 'edge_variant.php');

        self::assertCount(1, $clones);
        // 122 tokens, where this probe was written against 60. The clone is the same code;
        // a token counts punctuation now, so the span is worth about twice as many.
        self::assertSame(122, $clones[0]->numberOfTokens());
        self::assertFalse($clones[0]->isGapped());
        self::assertSame([], $clones[0]->divergences());
    }

    /**
     * The band ruling 7(b) *does* reach, pinned as its own case so the mechanism
     * is exercised by the suite and not only by the recall gate.
     *
     * The same fixture at a floor set **above** the core, which is what makes
     * the scorer's truncation decide the verdict rather than ranking between
     * two acceptable readings. The attachment is proposed, the aligner accepts
     * it, and a pair that would otherwise have been refused outright is
     * reported.
     *
     * The floor has to move with the unit or the construction dissolves: the
     * core is 122 tokens where it was 60, so a floor of 70 now sits *below* it
     * and nothing needs rescuing. 140 keeps the same relation — a floor a
     * little above the core — which is the only thing this probe depends on.
     *
     * Without ruling 7(b) this configuration reports nothing at all.
     */
    #[Test]
    public function probe1_the_same_pair_under_a_higher_floor_is_rescued_by_flank_attachment(): void
    {
        $clones = $this->clonesFor('edge_base.php', 'edge_variant.php', minTokens: 140);

        self::assertCount(1, $clones);
        // 171 tokens, where this probe was written against 84 — and the ratio
        // is the evidence that the mechanism is untouched rather than merely
        // still firing. Flank attachment grew a 60-token core to 84 before, and
        // grows a 122-token core to 171 now: 1.40 either way.
        self::assertSame(171, $clones[0]->numberOfTokens());
        self::assertTrue($clones[0]->isGapped());

        $divergences = $clones[0]->divergences();
        self::assertCount(4, $divergences);

        foreach ($divergences as $divergence) {
            // Ruling C still does not fire on a symmetric shape: the attachment
            // turned two edge remainders into two internal gaps, which is what
            // they always were.
            self::assertFalse($divergence->edge, 'ruling C must not fire on a symmetric shape');
        }
    }

    // ── Probe 2: twin gaps, middle run shorter than S ──

    #[Test]
    public function probe2_twin_gaps_are_both_crossed(): void
    {
        $clones = $this->clonesFor('twingap_base.php', 'twingap_variant.php');

        self::assertCount(1, $clones);
        self::assertTrue($clones[0]->isGapped());
        self::assertFalse($clones[0]->isReordered());
        // 167 tokens, where this probe was written against 84. The clone is the same code;
        // a token counts punctuation now, so the span is worth about twice as many.
        self::assertSame(167, $clones[0]->numberOfTokens());
        // Two divergences (one inserted statement before the stranded middle
        // run, one after), named on both sides: four entries.
        self::assertCount(4, $clones[0]->divergences());
    }

    // ── Probe 3: an edit every ~12 tokens — denser than the guarantee ──

    #[Test]
    public function probe3_denser_than_the_guarantee_is_a_documented_miss(): void
    {
        $clones = $this->clonesFor('dense_base.php', 'dense_variant.php');

        self::assertSame([], $clones, 'no seed can survive an edit every ~12 tokens against a 16-token seed');
    }

    // ── Probe 4: reorder — the restated displaced-mass rule ──

    #[Test]
    public function probe4_reorder_fires_under_the_restated_displaced_mass_rule(): void
    {
        $clones = $this->clonesFor('reorder_base.php', 'reorder_swapped.php');

        self::assertCount(1, $clones);
        self::assertTrue($clones[0]->isReordered());
        self::assertTrue($clones[0]->isGapped());
        // 279 tokens, where this probe was written against 132. The clone is the same code;
        // a token counts punctuation now, so the span is worth about twice as many.
        self::assertSame(279, $clones[0]->numberOfTokens());

        // The displaced 49-token block is named on both sides, plus the
        // 24-token block's B-side placement: at least the crossed material
        // is present in the reported divergences.
        self::assertNotSame([], $clones[0]->divergences());
    }

    #[Test]
    public function the_tokenbag_reorder_fixture_r1_is_back_in_contract(): void
    {
        // r1's swapped statements are three tokens each in the engine's own
        // numbering — far below K = 16 — so no *seeded* method can attest a
        // displacement that short, and until ruling R this pair was correctly
        // read as a substitution rather than a reorder. That is the one class
        // ruling A's algebra says seeds provably cannot see, and it is what the
        // order-free verdict exists to reach.
        //
        // The pair's acceptance never depended on this: it was reported before
        // and is reported now. What changed is its *kind* — the shingle bag can
        // say that the material is all present and name which block moved, so
        // the finding is a reorder and says where. Its own floor is still 38.
        $map = $this->detect([
            __DIR__ . '/fixtures/r1/reorder_base.php',
            __DIR__ . '/fixtures/r1/reorder_swapped.php',
        ], minTokens: 38);

        $clones = array_values($map->clones());

        self::assertCount(1, $clones);
        self::assertTrue($clones[0]->isReordered());
        self::assertNotSame([], $clones[0]->divergences(), 'a reorder that cannot say what moved is not reportable');
    }

    // ── Probe 5: bounded edge divergence (M2 ruling C) ──

    #[Test]
    public function probe5_one_copy_extended_is_flagged_with_a_bounded_range(): void
    {
        // The base copy's file ends right where the shared core ends; the
        // variant copy keeps going with a block of guards the base never
        // had. The asymmetry bound fires: short side (0 remainder) clears
        // it, long side (well past it) does not.
        $clones = $this->clonesFor('edgeext_base.php', 'edgeext_variant.php');

        self::assertCount(1, $clones);
        self::assertTrue($clones[0]->isGapped());
        self::assertFalse($clones[0]->isReordered());

        $divergences = $clones[0]->divergences();
        self::assertCount(2, $divergences);

        $bound = BandedAligner::budgetFor($clones[0]->numberOfTokens());

        foreach ($divergences as $divergence) {
            self::assertLessThanOrEqual(
                $bound,
                $divergence->tokens,
                'a truncated tail must never exceed the asymmetry bound',
            );
        }
    }

    #[Test]
    public function probe5_negative_two_copies_that_merely_continue_differently_are_not_flagged(): void
    {
        // Both copies keep going past the shared core — different tails, but
        // neither one is small. The required paired negative: this must NOT
        // be flagged, or every clone would "diverge" into the rest of both
        // files.
        $clones = $this->clonesFor('edgeneg_base.php', 'edgeneg_variant.php');

        self::assertCount(1, $clones);
        self::assertFalse($clones[0]->isGapped());
        self::assertSame([], $clones[0]->divergences());
    }

    // ── §6 property tests ────────────────────────────────────────────────

    /**
     * Every gapped (not reordered) clone the engine reports — across the
     * probe fixtures, the suffix-tree Type-3 fixtures, and one real corpus —
     * has a verified similarity of at least 1 − RATIO = 0.85, recomputed
     * independently of {@see CloneClassifier}'s own verification: a full,
     * unbanded Levenshtein distance ({@see levenshtein()}, a plain textbook
     * two-row DP over the actual tokens — no band, no X-drop, no code shared
     * with {@see BandedAligner}) over the two copies' raw token streams. Each
     * side's own true length is reconstructed from public data only —
     * `numberOfTokens()` for the lead copy, adjusted by that copy's and the
     * other copy's own divergence-token totals for the other — not assumed
     * equal, since a gap can be a pure insertion on one side.
     *
     * Reordered clones are excluded deliberately: they are verified by
     * coverage against {@see CloneClassifier::THETA}, not by edit distance,
     * and a genuine reorder is expected to score badly on a linear alignment
     * — that is the whole reason it needs its own classification.
     */
    #[Test]
    public function every_gapped_clone_has_verified_similarity_at_least_the_threshold(): void
    {
        $checked = 0;

        foreach ($this->gappedClonesOnEngineOutput() as [$clone, $fileA, $fileB]) {
            if ($clone->isReordered()) {
                continue;
            }

            $similarity = self::independentSimilarity($clone);

            self::assertGreaterThanOrEqual(
                1.0 - BandedAligner::RATIO,
                $similarity,
                sprintf(
                    'gapped clone %s + %s (%d tokens) has independently-verified similarity %.4f, below the threshold',
                    basename($fileA),
                    basename($fileB),
                    $clone->numberOfTokens(),
                    $similarity,
                ),
            );

            $checked++;
        }

        self::assertGreaterThan(0, $checked, 'the property must have been exercised on something');
    }

    /**
     * Every `gapped: true` report's divergence ranges actually differ between
     * the two copies, and the material flanking each range — the token
     * immediately before it and the token immediately after, wherever the
     * file has one — actually matches. That is what "gapped" and "ranges"
     * mean: a divergence is bounded by exact agreement on both sides (or by
     * the file's own edge, for a bounded edge divergence), and it is not
     * empty air.
     *
     * The required edge-divergence negative (probe 5's paired negative) is
     * asserted directly above; included here again as part of the same
     * corpus this property runs over, so a regression that made it fire
     * would also break this property's premise (an unexpected divergence
     * that fails to differ or to flank correctly).
     */
    #[Test]
    public function every_gapped_report_has_ranges_that_differ_and_flanks_that_match(): void
    {
        $checked = 0;

        foreach ($this->gappedClonesOnEngineOutput() as [$clone, $fileA, $fileB]) {
            if ($clone->isReordered()) {
                continue; // displaced blocks are matches, not divergences; see below
            }

            $divergences = $clone->divergences();
            self::assertNotSame([], $divergences, 'a gapped, non-reordered clone must name at least one divergence');
            self::assertSame(0, count($divergences) % 2, 'divergences are named in pairs, one per side of each gap');

            $bySide = self::splitBySide($divergences, $fileA, $fileB);

            if (count($bySide[0]) !== count($bySide[1])) {
                // A class can carry more than one edge between the same two
                // sites (the raw and normalized views both matching the same
                // pair, say), and their divergence sets are unioned and
                // deduplicated by (file, start token) — a dedup keyed
                // per-file, not per-pair, so two edges' divergence counts can
                // come out uneven on the two sides once merged. Pairing them
                // back up needs the edge structure this test deliberately
                // does not reach into from engine output; skip rather than
                // guess at a pairing. The single-edge case — everything the
                // probe fixtures exercise — is asserted in full below.
                continue;
            }

            // Checked in both views: a divergence the normalized-only view
            // found can be raw-identical-looking at the token level only in
            // the sense that a rename is; what must hold is that *some* view
            // shows a real difference and *some* view's flanks agree — the
            // same view need not be the one that shows both, since a rename
            // right at the flank boundary is itself information, not noise.
            $rawA  = self::rawSignature($fileA);
            $rawB  = self::rawSignature($fileB);
            $normA = self::normalizedSignature($fileA);
            $normB = self::normalizedSignature($fileB);
            $count = self::tokenCount($rawA); // raw and normalized share a token count

            foreach ($bySide[0] as $index => $divergenceA) {
                $divergenceB = $bySide[1][$index];

                $differsInRaw  = !self::rangesAreIdentical($rawA, $divergenceA->startToken, $divergenceA->tokens, $rawB, $divergenceB->startToken, $divergenceB->tokens);
                $differsInNorm = !self::rangesAreIdentical($normA, $divergenceA->startToken, $divergenceA->tokens, $normB, $divergenceB->startToken, $divergenceB->tokens);

                self::assertTrue(
                    $differsInRaw || $differsInNorm,
                    'a reported divergence must actually differ between the two copies, in at least one view',
                );

                $flanksInRaw  = self::flanksMatch($rawA, $divergenceA->startToken, $divergenceA->tokens, $count, $rawB, $divergenceB->startToken, $divergenceB->tokens, $count);
                $flanksInNorm = self::flanksMatch($normA, $divergenceA->startToken, $divergenceA->tokens, $count, $normB, $divergenceB->startToken, $divergenceB->tokens, $count);

                self::assertTrue(
                    $flanksInRaw || $flanksInNorm,
                    'the material flanking a divergence must match between the two copies, in at least one view',
                );

                $checked++;
            }
        }

        self::assertGreaterThan(0, $checked, 'the property must have been exercised on something');
    }

    /**
     * Reordered clones separately: every displaced block *is* a real match
     * (not a divergence) — the whole point of naming it is that it moved,
     * not that it changed — so the property here is the opposite of the
     * gapped case: the two named locations are token-for-token identical.
     */
    #[Test]
    public function every_reordered_clones_displaced_blocks_are_real_matches(): void
    {
        $clones = $this->clonesFor('reorder_base.php', 'reorder_swapped.php');
        self::assertCount(1, $clones);
        self::assertTrue($clones[0]->isReordered());

        $fileA = self::PROBES . '/reorder_base.php';
        $fileB = self::PROBES . '/reorder_swapped.php';
        $sigA  = self::rawSignature($fileA);
        $sigB  = self::rawSignature($fileB);

        $bySide   = self::splitBySide($clones[0]->divergences(), $fileA, $fileB);
        $checked  = 0;

        // Paired by what they are, not by where they sit in the list. The engine
        // sorts divergences by (file, start token), and for a *reorder* the two
        // sides are in crossed order by definition — that is what "reordered"
        // means — so the nth entry on side A is not in general the partner of
        // the nth on side B. Indexing them together held only while a fixture
        // happened to sort the same way on both sides, which is a property of
        // the fixture and not of the engine.
        foreach ($bySide[0] as $divergenceA) {
            if ($divergenceA->tokens === 0) {
                continue; // an unmatched-length pairing (an insertion side); nothing to compare
            }

            $matched = false;

            foreach ($bySide[1] as $divergenceB) {
                if ($divergenceB->tokens !== $divergenceA->tokens) {
                    continue;
                }

                if (self::rangesAreIdentical($sigA, $divergenceA->startToken, $divergenceA->tokens, $sigB, $divergenceB->startToken, $divergenceB->tokens)) {
                    $matched = true;

                    break;
                }
            }

            self::assertTrue(
                $matched,
                'a displaced block must be a real match, token for token',
            );

            $checked++;
        }

        self::assertGreaterThan(0, $checked);
    }

    /**
     * @return list<array{0: CodeClone, 1: string, 2: string}> clone, fileA path, fileB path
     */
    private function gappedClonesOnEngineOutput(): array
    {
        $out = [];

        $pairs = [
            ['edge_base.php', 'edge_variant.php'],
            ['twingap_base.php', 'twingap_variant.php'],
            ['reorder_base.php', 'reorder_swapped.php'],
            ['edgeext_base.php', 'edgeext_variant.php'],
            ['edgeneg_base.php', 'edgeneg_variant.php'],
        ];

        foreach ($pairs as [$base, $variant]) {
            $fileA = self::PROBES . '/' . $base;
            $fileB = self::PROBES . '/' . $variant;

            foreach ($this->detect([$fileA, $fileB])->clones() as $clone) {
                if ($clone->isGapped()) {
                    $out[] = [$clone, $fileA, $fileB];
                }
            }
        }

        // The suffix-tree Type-3 fixtures — a multi-copy class, so only
        // 2-file edges are usable for this property; taken pairwise.
        $type3Dir = __DIR__ . '/fixtures/type3';
        $type3    = (new FileFinder())->find([$type3Dir], ['.php'], [], false);
        sort($type3);

        foreach ($this->detect($type3)->clones() as $clone) {
            if (!$clone->isGapped() || count($clone->files()) !== 2) {
                continue;
            }

            $names = array_map(static fn($f) => $f->name, array_values($clone->files()));

            if ($names[0] === $names[1]) {
                continue; // two sites in one file: see the note below
            }

            $out[] = [$clone, $names[0], $names[1]];
        }

        // One real corpus: symfony/string, fast enough for the suite and
        // known (from the M2 diversity-guard measurement) to carry genuine
        // Type-3 gapped duplication once the false Unicode-table match is
        // suppressed.
        //
        // Two sites in *one* file (e.g. AbstractUnicodeString.php duplicating
        // a block against itself) are excluded: this helper's divergence
        // bookkeeping below splits by file *name*, which cannot tell two
        // same-file sites apart. That is a limit of this test's own
        // reconstruction from public data, not a claim about such clones.
        $corpus = __DIR__ . '/../bench/corpus/symfony-string';
        $files  = (new FileFinder())->find([$corpus], ['.php'], [], false);
        sort($files);

        foreach ($this->detect($files, minTokens: 70)->clones() as $clone) {
            if (!$clone->isGapped() || count($clone->files()) !== 2) {
                continue;
            }

            $names = array_map(static fn($f) => $f->name, array_values($clone->files()));

            if ($names[0] === $names[1]) {
                continue;
            }

            $out[] = [$clone, $names[0], $names[1]];
        }

        return $out;
    }

    /**
     * @param list<CloneDivergence> $divergences
     * @return array{0: list<CloneDivergence>, 1: list<CloneDivergence>}
     */
    private static function splitBySide(array $divergences, string $fileA, string $fileB): array
    {
        $sideA = [];
        $sideB = [];

        foreach ($divergences as $divergence) {
            if ($divergence->file === $fileA) {
                $sideA[] = $divergence;
            } elseif ($divergence->file === $fileB) {
                $sideB[] = $divergence;
            }
        }

        return [$sideA, $sideB];
    }

    /** @var array<string, FileTokens> */
    private static array $tokenizedCache = [];

    /** @var array<string, string> */
    private static array $normalizedCache = [];

    private static function tokenized(string $file): FileTokens
    {
        return self::$tokenizedCache[$file] ??= (new DefaultStrategy(self::config()))
            ->tokenize((string) file_get_contents($file));
    }

    /**
     * The normalized view's own signature — same token count and line map as
     * the raw view (normalization rewrites token text, never token count),
     * different byte values. Independent of the engine's own encoder only in
     * the sense of being a fresh call; the normalization rules themselves are
     * {@see DefaultStrategy}'s, same as production, since re-normalizing by
     * hand here would test this file's normalizer against itself.
     */
    private static function normalizedSignature(string $file): string
    {
        return self::$normalizedCache[$file] ??= (new DefaultStrategy(new StrategyConfiguration(
            minLines: 5,
            minTokens: 50,
            normalization: Normalization::TypeAnchored,
            minSimilarity: 0.7,
        )))->tokenize((string) file_get_contents($file))->signature;
    }

    private static function rawSignature(string $file): string
    {
        return self::tokenized($file)->signature;
    }

    private static function tokenCount(string $signature): int
    {
        return intdiv(strlen($signature), FileTokens::TOKEN_BYTES);
    }

    private static function token(string $signature, int $index): ?string
    {
        $slice = substr($signature, $index * FileTokens::TOKEN_BYTES, FileTokens::TOKEN_BYTES);

        return strlen($slice) === 5 ? $slice : null;
    }

    private static function rangesAreIdentical(string $sigA, int $startA, int $lengthA, string $sigB, int $startB, int $lengthB): bool
    {
        if ($lengthA !== $lengthB) {
            return false;
        }

        return substr($sigA, $startA * FileTokens::TOKEN_BYTES, $lengthA * FileTokens::TOKEN_BYTES)
            === substr($sigB, $startB * FileTokens::TOKEN_BYTES, $lengthB * FileTokens::TOKEN_BYTES);
    }

    /** Does the token immediately before, and the token immediately after, a range agree — wherever the file has one? */
    private static function flanksMatch(
        string $sigA,
        int $startA,
        int $lengthA,
        int $countA,
        string $sigB,
        int $startB,
        int $lengthB,
        int $countB,
    ): bool {
        $beforeA = $startA > 0 ? self::token($sigA, $startA - 1) : null;
        $beforeB = $startB > 0 ? self::token($sigB, $startB - 1) : null;

        if ($beforeA !== null && $beforeB !== null && $beforeA !== $beforeB) {
            return false;
        }

        $afterA = $startA + $lengthA < $countA ? self::token($sigA, $startA + $lengthA) : null;
        $afterB = $startB + $lengthB < $countB ? self::token($sigB, $startB + $lengthB) : null;

        if ($afterA !== null && $afterB !== null && $afterA !== $afterB) {
            return false;
        }

        return true;
    }

    /**
     * An independent recomputation of similarity: a plain, full (unbanded)
     * Levenshtein distance over the two copies' token streams, written here
     * rather than reused from {@see BandedAligner} — a classical textbook DP
     * over the actual 5-byte tokens, with no band, no X-drop, and no shared
     * code with the engine's own verifier.
     *
     * The lead copy's own length is `numberOfTokens()`; the other copy's is
     * not assumed equal (a gap can be a pure insertion on one side) but
     * reconstructed from public data only: the lead's length, minus its own
     * divergence-token total, plus the other copy's — since every stretch
     * *between* divergences is, by definition of "gapped", an exact match and
     * therefore the same length on both sides.
     *
     * `CodeCloneFile::startLine` names a *line*, not a token — a clone need
     * not begin at the first significant token on it (mirroring the same
     * choice in {@see UnifiedEngineTest}'s exactness property) — and a clone
     * a normalized-only view found can look nothing alike in the raw token
     * stream. So this tries every token on the reported start line, in both
     * the raw and the normalized view, and reports the best similarity found
     * across all of them: a real failure fails in *every* interpretation,
     * where a false one only needs one to succeed.
     */
    private static function independentSimilarity(CodeClone $clone): float
    {
        $files = array_values($clone->files());
        $lead  = $files[0];
        $other = $files[1];

        $leadLength = $clone->numberOfTokens();
        $sumLead    = 0;
        $sumOther   = 0;

        foreach ($clone->divergences() as $divergence) {
            // A bounded edge divergence (ruling C) is material one copy has
            // *past* the shared span, so it is no part of either side's length
            // and counting it would reconstruct the wrong window — measured: on
            // the symfony/string inflector pair it makes a 218-token side read
            // as 197, and a genuine 0.864 alignment read as 0.838. The engine
            // reports which kind each divergence is, so this no longer has to be
            // inferred from line numbers it cannot be inferred from.
            if ($divergence->edge) {
                continue;
            }

            if ($divergence->file === $lead->name) {
                $sumLead += $divergence->tokens;
            } elseif ($divergence->file === $other->name) {
                $sumOther += $divergence->tokens;
            }
        }

        $otherLength = $leadLength - $sumLead + $sumOther;

        $best = 0.0;

        foreach ([self::rawSignature(...), self::normalizedSignature(...)] as $signatureOf) {
            foreach (self::candidateStarts($lead->name, $lead->startLine) as $leadStart) {
                foreach (self::candidateStarts($other->name, $other->startLine) as $otherStart) {
                    $tokensLead  = str_split(substr($signatureOf($lead->name), $leadStart * FileTokens::TOKEN_BYTES, $leadLength * FileTokens::TOKEN_BYTES), FileTokens::TOKEN_BYTES);
                    $tokensOther = str_split(substr($signatureOf($other->name), $otherStart * FileTokens::TOKEN_BYTES, $otherLength * FileTokens::TOKEN_BYTES), FileTokens::TOKEN_BYTES);

                    $distance = self::levenshtein($tokensLead, $tokensOther);
                    $longest  = max(count($tokensLead), count($tokensOther));
                    $best     = max($best, $longest === 0 ? 1.0 : 1.0 - ($distance / $longest));
                }
            }
        }

        return $best;
    }

    /** Every token position on $startLine — a clone need not begin at the first one. */
    private static function candidateStarts(string $file, int $startLine): array
    {
        $starts = [];

        foreach (self::tokenized($file)->tokenRealLines as $index => $line) {
            if ($line === $startLine) {
                $starts[] = $index;
            }
        }

        return $starts;
    }

    /**
     * Full Levenshtein distance over two token lists — the classical
     * textbook two-row dynamic program, no band, no early termination.
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function levenshtein(array $a, array $b): int
    {
        $m = count($a);
        $n = count($b);

        $previous = range(0, $n);

        for ($i = 1; $i <= $m; $i++) {
            $current    = [$i];
            $tokenA     = $a[$i - 1];

            for ($j = 1; $j <= $n; $j++) {
                $cost         = $tokenA === $b[$j - 1] ? 0 : 1;
                $current[$j]  = min(
                    $previous[$j] + 1,
                    $current[$j - 1] + 1,
                    $previous[$j - 1] + $cost,
                );
            }

            $previous = $current;
        }

        return $previous[$n];
    }
}
