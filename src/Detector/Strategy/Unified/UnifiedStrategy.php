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

use function array_column;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;
use function array_slice;
use function count;
use function file_get_contents;
use function ksort;
use function max;
use function min;
use function sort;
use function usort;

use LucianoPereira\PhpcpdNext\CloneDivergence;
use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Facts\RegionStructure;
use LucianoPereira\PhpcpdNext\InvalidStrategyException;

/**
 * The unified engine: one detector in place of three.
 *
 * Four stages:
 *
 *   A. **Encode** — reuse {@see DefaultStrategy::tokenize()}'s packed signature,
 *      eight bytes per significant token, in two views: the tokens as written, and
 *      the same tokens with identifiers normalized and types kept distinct.
 *   B. **Index** — winnowed k-gram fingerprints ({@see Winnower}), sampling
 *      ~2/(W+1) of positions under a guarantee that no clone of the reported
 *      length can be missed by the sampling.
 *   C. **Anchor** — grow every shared fingerprint into its maximal exact match
 *      ({@see AnchorSet}), at memcmp speed.
 *   D. **Chain, classify, verify** — {@see ChainBuilder} finds which anchors
 *      belong to one clone, {@see CloneClassifier} decides what kind it is, and
 *      {@see BandedAligner} decides whether it is one at all.
 *
 * The design principle underneath: **corpus-sized work happens inside C
 * primitives, and interpreted PHP only ever touches candidates.** The only loop
 * here that runs per token is the fingerprint scan, whose body is a `substr` and
 * a `hash`; everything from Stage C on runs over shared fingerprints, whose count
 * is a property of how much duplication a corpus contains rather than of its size.
 *
 * ## Two views, not two modes
 *
 * `--fuzzy` and `--type-anchored` are switches on the other engines: turn one on
 * and the whole run changes meaning. Here both views are computed always, and
 * which one found a clone *is* its type — raw means an exact copy, normalized-only
 * means the same code with the names changed. Type-anchoring is on for the
 * normalized view and not offered as a choice, because the paper settled that
 * question: it Pareto-dominates name-blind fuzzing, equal recall at +10.9 to +45.3
 * points of specificity, which makes name-blind fuzzing a dominated configuration
 * rather than an alternative.
 *
 * ## Two public knobs
 *
 * `--min-tokens` and `--min-lines`. The removed suffix tree's `--edit-distance`
 * became {@see BandedAligner::RATIO}, `--min-similarity` became 1 − RATIO,
 * `--head-equality` became {@see Winnower::SEED_LENGTH}, and K, W, the postings cap
 * and the overlap threshold are derived or cited. A knob that can be derived is a
 * knob that can be set wrong.
 *
 * ## Determinism
 *
 * File ids are assigned from the *sorted* path list in {@see postProcess()}, not
 * from the order files arrived in, so the report is identical whether the caller
 * passed a sorted list, a reversed one, or a shuffled one.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class UnifiedStrategy extends AbstractStrategy
{
    /**
     * How much two token ranges must overlap to be talking about the same code.
     *
     * Eight tenths, and it is read two ways, which ruling G separated: of the
     * **longer** range when asking whether two ranges are one *site*
     * ({@see overlaps()}, used by {@see classes()}), and of the **shorter** when
     * asking whether one range is already *covered* by another
     * ({@see subsumes()}). Either way the fraction is strict enough that two
     * adjacent clones stay separate and loose enough that the same block found
     * twice, once a few tokens longer, does not become two.
     */
    private const float SITE_OVERLAP = 0.8;

    private DefaultStrategy $rawEncoder;

    private DefaultStrategy $normalizedEncoder;

    /**
     * @var array<string, array{
     *     tokens: FileTokens,
     *     fingerprints: list<array{0: string, 1: int}>,
     *     normalized: string,
     *     normalizedFingerprints: list<array{0: string, 1: int}>
     * }>
     */
    private array $files = [];

    private ?CodeCloneMap $result = null;

    private readonly Winnower $winnower;

    private readonly CloneClassifier $classifier;

    private int $cappedFingerprints = 0;

    private int $discardedPostings = 0;

    private int $pairCappedFingerprints = 0;

    private int $discardedSeedPairs = 0;

    private int $filesEncoded = 0;

    private int $filesFingerprinted = 0;

    private int $tableRepetitions = 0;

    private int $bagSeededCandidates = 0;

    private int $orderFreeVerdicts = 0;

    /**
     * The facts layer, one description per file, computed on first use.
     *
     * Ruling R's integrated shape says the two tiers "consume the Stage 0/A facts
     * layer (wiring, role, region-structure annotations computed once)", and this
     * cache is the "once": the literal-table rule and the bag channel both read
     * it, so a file is tokenized for structure at most one extra time per run.
     *
     * @var array<string, ?RegionStructure>
     */
    private array $facts = [];

    public function __construct(StrategyConfiguration $config)
    {
        parent::__construct($config);

        if ($config->minTokens < Winnower::MINIMUM_MIN_TOKENS) {
            throw new InvalidStrategyException((new Catalogue())->get('refuse.belowFloor.minTokens', [
                'flag'  => '--min-tokens',
                'floor' => Winnower::MINIMUM_MIN_TOKENS,
                'given' => $config->minTokens,
            ]));
        }

        $this->winnower          = Winnower::forMinTokens($config->minTokens);
        $this->classifier        = new CloneClassifier();
        $this->rawEncoder        = new DefaultStrategy(self::view($config, normalized: false));
        $this->normalizedEncoder = new DefaultStrategy(self::view($config, normalized: true));
    }

    /**
     * The configuration one of Stage A's views is encoded under: the thresholds
     * as given, with normalization forced on or off regardless of what the caller
     * asked for. The unified engine computes views, not modes.
     */
    private static function view(StrategyConfiguration $config, bool $normalized): StrategyConfiguration
    {
        return new StrategyConfiguration(
            minLines: $config->minLines,
            minTokens: $config->minTokens,
            normalization: $normalized ? Normalization::TypeAnchored : Normalization::Raw,
            minSimilarity: $config->minSimilarity,
        );
    }

    #[\Override]
    public function processFile(string $file, CodeCloneMap $result): void
    {
        $buffer = file_get_contents($file);

        if ($buffer === false) {
            return;
        }

        $tokens     = $this->rawEncoder->tokenize($buffer);
        $normalized = $this->normalizedEncoder->tokenize($buffer)->signature;
        $this->filesEncoded++;

        $this->add(
            $file,
            $tokens,
            $this->fingerprint($tokens->signature),
            $normalized,
            $this->fingerprint($normalized, normalized: true),
            $result,
        );

        $this->filesFingerprinted++;
    }

    /**
     * Hand the engine a file whose encoding and fingerprints were computed
     * elsewhere — the incremental index replaying an unchanged file.
     *
     * All four are pure functions of the file's bytes and the configuration, so a
     * replayed file and a freshly computed one are indistinguishable to
     * everything downstream. Neither counter moves: nothing was computed.
     *
     * @param list<array{0: string, 1: int}> $fingerprints
     * @param list<array{0: string, 1: int}> $normalizedFingerprints
     */
    public function add(
        string $file,
        FileTokens $tokens,
        array $fingerprints,
        string $normalized,
        array $normalizedFingerprints,
        CodeCloneMap $result,
    ): void {
        $this->files[$file] = [
            'tokens'                 => $tokens,
            'fingerprints'           => $fingerprints,
            'normalized'             => $normalized,
            'normalizedFingerprints' => $normalizedFingerprints,
        ];

        $this->result = $result;
    }

    /** Tokenize a buffer under the raw view — the encode step, exposed for the cache. */
    public function encode(string $buffer): FileTokens
    {
        return $this->rawEncoder->tokenize($buffer);
    }

    /** Tokenize a buffer under the normalized view, returning just its signature. */
    public function encodeNormalized(string $buffer): string
    {
        return $this->normalizedEncoder->tokenize($buffer)->signature;
    }

    /**
     * The winnowed sample of one signature — the step whose result the
     * incremental index caches so a warm run does not repeat it.
     *
     * `$normalized` applies the diversity guard (M2 audit ruling B): the raw
     * view is fingerprinted unconditionally, and the normalized view refuses a
     * k-gram below {@see Winnower::NORMALIZED_DIVERSITY_FLOOR} distinct tokens.
     *
     * @return list<array{0: string, 1: int}>
     */
    public function fingerprint(string $signature, bool $normalized = false): array
    {
        return $this->winnower->select(
            $signature,
            $normalized ? Winnower::NORMALIZED_DIVERSITY_FLOOR : 0,
        );
    }

    #[\Override]
    public function postProcess(): void
    {
        if ($this->result === null || $this->files === []) {
            return;
        }

        // Sorting here rather than trusting the caller is what makes the engine
        // order-stable: from this line on, nothing knows how the files arrived.
        $paths = $this->files;
        ksort($paths);

        /** @var array<int, string> $names */
        $names = [];
        /** @var array<int, FileTokens> $tokens */
        $tokens = [];
        /** @var array<int, string> $raw */
        $raw = [];
        /** @var array<int, string> $normalized */
        $normalized = [];

        $rawIndex        = new FingerprintIndex();
        $normalizedIndex = new FingerprintIndex();
        $fileId          = 0;

        foreach ($paths as $path => $entry) {
            $this->result->addToNumberOfLines($entry['tokens']->numberOfLines);

            $names[$fileId]      = $path;
            $tokens[$fileId]     = $entry['tokens'];
            $raw[$fileId]        = $entry['tokens']->signature;
            $normalized[$fileId] = $entry['normalized'];

            $rawIndex->add($fileId, $entry['fingerprints']);
            $normalizedIndex->add($fileId, $entry['normalizedFingerprints']);

            $fileId++;
        }

        $this->cappedFingerprints = $rawIndex->cappedFingerprintCount() + $normalizedIndex->cappedFingerprintCount();
        $this->discardedPostings  = $rawIndex->discardedPostingCount() + $normalizedIndex->discardedPostingCount();

        $candidates      = $this->candidates($raw, $rawIndex, $tokens, normalizedOnly: false);
        $normalizedFound = $this->candidates($normalized, $normalizedIndex, $tokens, normalizedOnly: true);

        // The two views cross-check each other, and this is what the second one
        // is really for. A pair that the raw view calls gapped but the normalized
        // view sees as one unbroken match differs only in things normalization
        // erases — identifier names. That is a rename, not a divergence, and
        // reporting it as `gapped` would put the most bug-predictive signal the
        // tool has ("one copy was patched and its sibling was not") on two classes
        // that differ by their own names. The other engines do not raise it, and
        // neither should this one.
        foreach ($candidates as $index => $candidate) {
            if ($candidate['kind'] !== 'gapped') {
                continue;
            }

            foreach ($normalizedFound as $other) {
                if ($other['kind'] === 'type-2' && self::samePlace($candidate, $other)) {
                    $candidates[$index]['kind'] = 'type-2';
                    $candidates[$index]['gaps'] = [];

                    break;
                }
            }
        }

        // A clone the normalized view finds and the raw view does not is the same
        // code with the names changed — a Type-2 clone. One the raw view already
        // found is the same report twice, so it is dropped rather than emitted
        // as a second finding about one piece of duplication.
        foreach ($normalizedFound as $candidate) {
            if (!self::alreadyReported($candidate, $candidates)) {
                $candidates[] = $candidate;
            }
        }

        $candidates = $this->withoutSelfRepeatingTables($candidates, $names);
        $candidates = [...$candidates, ...$this->bagCandidates($names, $raw, $tokens, $candidates)];

        foreach ($this->classes($candidates, $names, $tokens) as $clone) {
            $this->result->add($clone);
        }
    }

    /**
     * Drop the candidates that are a literal table matching **itself** — ruling
     * H's one open avenue, and the only span-level discriminator that survives
     * the three the M2 audit refuted.
     *
     * The refuted three (anchor multiplicity, tokens-per-line sparseness, logic
     * share) were all *statistics* of a span, each needing a threshold, and each
     * failed on the same counter-example: a generated parser's action table is a
     * pure literal array and is a genuine clone of its sibling parser's, so no
     * cut on "how literal is this span" can remove data-table noise without
     * removing it too. This rule asks a different question — not how literal the
     * span is, but **what it is**: are these two runs of elements of one and the
     * same pure literal array?
     *
     * That distinction is what the counter-example turns on. `Php7.php` and
     * `Php8.php` hold two *different* tables, in two files, that a grammar change
     * regenerates together — a change to one plausibly has to be made to the
     * other, which is the rubric's definition of duplication, and this rule never
     * touches it. A table matching itself is the opposite: its second run is not
     * a copy anyone could remove, it is the regularity that makes it a table.
     *
     * No constant is introduced. The two conditions are membership tests — same
     * frame, and a frame containing no closure and no statement — and
     * {@see RegionStructure} records why "contains none of those" is a definition
     * of *literal* rather than a threshold at zero, and why ruling 5 moved the
     * named token class without changing the kind of test.
     *
     * Only files carrying a same-file candidate are read, which on every corpus
     * measured is a small minority of them.
     *
     * @param list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}> $candidates
     * @param array<int, string> $names
     * @return list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}>
     */
    private function withoutSelfRepeatingTables(array $candidates, array $names): array
    {
        $kept = [];

        foreach ($candidates as $candidate) {
            if ($candidate['fileA'] === $candidate['fileB'] && isset($names[$candidate['fileA']])) {
                $structure = $this->factsFor($names[$candidate['fileA']]);

                if ($structure !== null && $structure->sameLiteralTable(
                    $candidate['startA'],
                    $candidate['lengthA'],
                    $candidate['startB'],
                    $candidate['lengthB'],
                )) {
                    $this->tableRepetitions++;

                    continue;
                }
            }

            $kept[] = $candidate;
        }

        return $kept;
    }

    /** The facts layer for one file, read once per run. */
    private function factsFor(string $file): ?RegionStructure
    {
        if (!array_key_exists($file, $this->facts)) {
            $source             = file_get_contents($file);
            $this->facts[$file] = $source === false ? null : RegionStructure::fromSource($source);
        }

        return $this->facts[$file];
    }

    /**
     * Ruling R's third seed channel: function pairs the anchor set cannot reach,
     * promoted into the report as ordinary candidates.
     *
     * Two things make this a channel rather than a pass. It runs on the same
     * facts layer and the same signatures Stage A already built, so nothing is
     * encoded twice; and its output is a candidate of the same shape as an
     * anchor-seeded one, classified `reordered` and reported through the same
     * {@see classes()} path, so determinism, counting, grouping and every gate
     * keep their shape.
     *
     * **Complement gating (constraint 4) is the whole cost control.** A pair
     * already covered by what the seeded path reported is dropped before it is
     * verified, through the same {@see alreadyReported()} the normalized view
     * uses — so the channel's entire finding surface is the marginal class, which
     * is also, by construction, the bound on the false positives it can add.
     *
     * @param array<int, string>     $names
     * @param array<int, string>     $raw
     * @param array<int, FileTokens> $tokens
     * @param list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}> $existing
     * @return list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}>
     */
    private function bagCandidates(array $names, array $raw, array $tokens, array $existing): array
    {
        /** @var list<array{file: int, start: int, length: int, shingles: list<array{0: int, 1: int}>, hashes: list<int>, distinct: list<int>}> $units */
        $units = [];

        foreach ($names as $fileId => $file) {
            // Deliberately *not* through {@see factsFor()}: that cache is sized
            // by the handful of files carrying a same-file candidate, and this
            // loop visits every file in the corpus. A description holds one
            // integer per token, so retaining them all would put the corpus's
            // whole token count in memory a second time — the shape of the
            // failure the seed-pair cap was introduced for. What survives this
            // loop is the bags, which is what the next one needs.
            $structure = $this->facts[$file] ?? null;

            if ($structure === null) {
                $source = file_get_contents($file);

                if ($source === false) {
                    continue;
                }

                $structure = RegionStructure::fromSource($source);
            }

            if (!isset($raw[$fileId])) {
                continue;
            }

            foreach ($structure->functions() as [$start, $length]) {
                // A unit shorter than the reporting floor cannot produce a
                // finding, so it is not indexed and costs nothing.
                if ($length < $this->config->minTokens) {
                    continue;
                }

                $shingles = ShingleBags::shingles($raw[$fileId], $start, $length);

                if ($shingles === []) {
                    continue;
                }

                $hashes = array_column($shingles, 1);

                $units[] = [
                    'file'     => $fileId,
                    'start'    => $start,
                    'length'   => $length,
                    'shingles' => $shingles,
                    'hashes'   => $hashes,
                    'distinct' => array_values(array_unique($hashes)),
                ];
            }
        }

        $found = [];

        foreach (ShingleBags::candidatePairs($units, CloneClassifier::THETA) as [$leftId, $rightId]) {
            $left  = $units[$leftId];
            $right = $units[$rightId];

            // A nested closure against the body enclosing it is one region
            // compared with itself, which is not a pair at all.
            if ($left['file'] === $right['file'] && self::rangesTouch($left['start'], $left['length'], $right['start'], $right['length'])) {
                continue;
            }

            if ($left['file'] > $right['file'] || ($left['file'] === $right['file'] && $left['start'] > $right['start'])) {
                [$left, $right] = [$right, $left];
            }

            $candidate = [
                'fileA'     => $left['file'],
                'fileB'     => $right['file'],
                'startA'    => $left['start'],
                'lengthA'   => $left['length'],
                'startB'    => $right['start'],
                'lengthB'   => $right['length'],
                'kind'      => 'reordered',
                'tokens'    => min($left['length'], $right['length']),
                'gaps'      => [],
                'displaced' => [],
            ];

            if (self::alreadyReported($candidate, $existing) || self::alreadyReported($candidate, $found)) {
                continue;
            }

            $coverage = ShingleBags::coverage($left['hashes'], $right['hashes']);

            if ($coverage < CloneClassifier::THETA) {
                continue;
            }

            $displaced = ShingleBags::displacedRuns($left['shingles'], $right['shingles']);

            // Constraint 5: never an unlocalized finding.
            if ($displaced === []) {
                continue;
            }

            $linesA = $tokens[$left['file']]->tokenRealLines[$left['start'] + $left['length'] - 1]
                - $tokens[$left['file']]->tokenRealLines[$left['start']] + 1;

            if ($linesA < $this->config->minLines) {
                continue;
            }

            $candidate['displaced'] = $displaced;
            $found[]                = $candidate;
            $this->bagSeededCandidates++;
        }

        return $found;
    }

    /** Do two ranges in one file overlap at all? */
    private static function rangesTouch(int $startA, int $lengthA, int $startB, int $lengthB): bool
    {
        return min($startA + $lengthA, $startB + $lengthB) > max($startA, $startB);
    }

    /**
     * Stages C and D over one view: anchors, grouped by file pair, chained,
     * classified and verified.
     *
     * @param array<int, string>     $signatures
     * @param array<int, FileTokens> $tokens
     * @return list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}>
     */
    private function candidates(array $signatures, FingerprintIndex $index, array $tokens, bool $normalizedOnly): array
    {
        $seeds   = new AnchorSet($signatures, $this->winnower->seedLength);
        $anchors = $seeds->build($index->sharedPostings());

        // Both views' tallies add up, the same way the two posting caps' do:
        // this method runs once per view and the caps are never silent.
        $this->pairCappedFingerprints += $seeds->pairCappedFingerprints();
        $this->discardedSeedPairs     += $seeds->discardedSeedPairs();

        // Group by file pair — from here on the data is candidate-sized and
        // interpreted PHP is allowed to touch it.
        /** @var array<string, array{0: int, 1: int, 2: list<array{0: int, 1: int, 2: int}>, 3: int}> $pairs */
        $pairs = [];

        foreach ($anchors as [$fileA, $posA, $fileB, $posB, $length]) {
            $key = $fileA . ':' . $fileB;

            if (!isset($pairs[$key])) {
                $pairs[$key] = [$fileA, $fileB, [], 0];
            }

            $pairs[$key][2][] = [$posA, $posB, $length];
            $pairs[$key][3] += $length;
        }

        // How much agreement a file pair must show before it is worth chaining.
        //
        // Most pairs that share a fingerprint share very little. On phpunit the
        // normalized view reaches 23,717 file pairs — sixteen times the raw
        // view's 1,487, because normalization makes short, shallow agreement
        // common — and 91% of them yield no clone at all. Chaining, extending
        // and classifying each one is where three quarters of the engine's
        // running time goes.
        //
        // The floor is the winnowing guarantee's own threshold, S = ⌈minTokens/2⌉:
        // the length at and above which a common run is *guaranteed* to be
        // seeded, and so the unit in which this engine's evidence is denominated.
        // A pair whose anchors do not add up to one such run, across the whole
        // of both files, has not shown a single guaranteed-detectable run's
        // worth of agreement in total. It is not a new constant — it is the one
        // {@see Winnower::forMinTokens()} already derives, read back.
        //
        // This is a filter on *work*, and its cost is measured rather than
        // assumed: on phpunit it removes 62% of the normalized view's pairs and
        // 52% of the raw view's, and of the 2,152 normalized pairs that do
        // produce a candidate it removes 5, none of which survives to be
        // reported as a clone. It is not a proof — extension can recover runs
        // shorter than S that no anchor carries — so `bench/check-superset.php`
        // is what holds it honest.
        $floor = $this->winnower->guaranteedRunLength();

        $candidates = [];

        foreach ($pairs as [$fileA, $fileB, $pairAnchors, $mass]) {
            if ($mass < $floor) {
                continue;
            }

            foreach (self::cluster($pairAnchors) as $cluster) {
                // A file compared against itself is clustered once more, by
                // shift — see {@see shiftBands()}. Two files are not.
                $groups = $fileA === $fileB ? self::shiftBands($cluster) : [$cluster];

                foreach ($groups as $group) {
                    $candidates = [...$candidates, ...$this->fromCluster($group, $fileA, $fileB, $signatures, $tokens, $normalizedOnly)];
                }
            }
        }

        return $candidates;
    }

    /**
     * Split one file pair's anchors into groups that could belong to one clone.
     *
     * Without this the chainer is handed every anchor between two files at once —
     * 9,286 of them for one pair of generated parsers — and will happily join
     * anchors thousands of tokens and dozens of diagonals apart into a single
     * "clone" whose span covers most of both files. Two things go wrong at once:
     * the report grows spans that are mostly gap, and the chainer is re-run over
     * that whole set once per clone extracted from it.
     *
     * The rule has to be *permissive*, and that is the subtle part. Clustering is
     * a performance partition, not a decision: whether a chain is a clone is
     * settled later by {@see BandedAligner}, so a split here can only ever lose
     * something. In particular the gaps it measures are the gaps *before* Stage
     * D1 has recovered the matching material inside them, so a gap always looks
     * at least as wide as it will turn out to be — cutting on the acceptance
     * budget at this point would split exactly the twin-gap case that extension
     * exists to rejoin.
     *
     * So the cut is made only where no extension could help: when a junction
     * skips more tokens than the anchors have matched so far. Nothing recoverable
     * lives on the far side of a gap wider than everything already established,
     * and everything narrower stays together for chaining and verification to
     * judge on the evidence.
     *
     * The junction's own width is the *smaller* of the two files' gaps, not the
     * larger. A reordered candidate's junction from context into a displaced
     * block is exactly the shape a `max` would wrongly cut on: the block sits
     * immediately next to the context in one file (that gap is ~0 — nothing
     * skipped there at all) while the same block sits far away in the other,
     * because reordering is what put it there. Cutting on the far side's
     * distance would sever the context from the very block the reorder
     * classifier exists to name as displaced, before that classifier ever sees
     * both. Two genuinely unrelated anchors, by contrast, are distant on
     * *both* sides — that is what "unrelated" means here — so `min` still cuts
     * them apart.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     * @return list<list<array{0: int, 1: int, 2: int}>>
     */
    private static function cluster(array $anchors): array
    {
        usort($anchors, static fn(array $a, array $b): int => $a <=> $b);

        $clusters = [];
        $current  = [];
        $startA   = 0;
        $endA     = 0;
        $endB     = 0;

        foreach ($anchors as $anchor) {
            [$posA, $posB, $length] = $anchor;

            if ($current === []) {
                $current = [$anchor];
                $startA  = $posA;
                $endA    = $posA + $length;
                $endB    = $posB + $length;

                continue;
            }

            $gapA    = max(0, $posA - $endA);
            $gapB    = max(0, $posB - $endB);
            $step    = min($gapA, $gapB);
            $matched = $endA - $startA;

            if ($step > $matched) {
                $clusters[] = $current;
                $current    = [$anchor];
                $startA     = $posA;
                $endA       = $posA + $length;
                $endB       = $posB + $length;

                continue;
            }

            $current[] = $anchor;
            $endA      = max($endA, $posA + $length);
            $endB      = max($endB, $posB + $length);
        }

        if ($current !== []) {
            $clusters[] = $current;
        }

        return $clusters;
    }

    /**
     * Split one file's self-comparison into groups that share a shift.
     *
     * Two copies in one file sit some distance apart, and that distance — the
     * shift `posB - posA` — is a property of the duplication, not of the
     * anchor. Edits between the copies move it a little; nothing else moves it
     * at all. So anchors at wildly different shifts are evidence about
     * *different* pairs of copies, and chaining them together is the one thing
     * that must not happen here.
     *
     * It is what happens without this. A file built from many near-identical
     * blocks — a generated parser's productions, a test class with one method
     * per event type — matches itself at every multiple of its own period, so
     * one cluster arrives holding shifts p, 2p, 3p, … all at once. The chainer
     * is handed all of them together and answers with the only thing that reads
     * them as one duplication: a "clone" spanning almost the whole file against
     * almost the whole file, whose two copies overlap each other by thousands
     * of tokens. That candidate is nonsense, and it is not merely reported —
     * {@see fromCluster()} lets it claim every anchor in the cluster, so the
     * genuine block-against-block pairs inside are consumed and never found.
     * On phpunit's `MetadataTest.php` that is 9,286 anchors spent on one
     * rejected candidate, and nine real duplications lost with it.
     *
     * The tolerance is the aligner's own edit budget for the shift, so it is
     * derived rather than tuned: anchors whose shifts differ by more than
     * {@see BandedAligner::budgetFor()} could not survive one alignment
     * together even if they were chained, because closing that difference would
     * cost more edits than acceptance allows. Everything within it stays
     * together and is judged on the evidence, as before.
     *
     * Cross-file anchors are not banded. Two different files have no shared
     * origin for their offsets, so a shift there means nothing, and a genuine
     * reorder between two files is precisely a chain across shifts — the signal
     * {@see CloneClassifier} exists to name.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     * @return list<list<array{0: int, 1: int, 2: int}>>
     */
    private static function shiftBands(array $anchors): array
    {
        usort(
            $anchors,
            static fn(array $a, array $b): int => [$a[1] - $a[0], $a[0]] <=> [$b[1] - $b[0], $b[0]],
        );

        $bands = [];
        $current = [];
        $shift   = 0;

        foreach ($anchors as $anchor) {
            $anchorShift = $anchor[1] - $anchor[0];

            if ($current === []) {
                $current = [$anchor];
                $shift   = $anchorShift;

                continue;
            }

            // Sorted ascending, so this is never negative. It is measured from
            // the band's *first* shift rather than the previous anchor's, or a
            // long run of one-token steps would drift a band arbitrarily far
            // from where it started.
            if ($anchorShift - $shift > BandedAligner::budgetFor($shift)) {
                $bands[] = $current;
                $current = [$anchor];
                $shift   = $anchorShift;

                continue;
            }

            $current[] = $anchor;
        }

        if ($current !== []) {
            $bands[] = $current;
        }

        return $bands;
    }

    /**
     * Every clone one cluster of anchors yields.
     *
     * A cluster can still hold more than one clone — two copies of a block that
     * also share their surroundings — and the chainer answers "the best chain",
     * singular. So chains are extracted repeatedly: take the best, remove the
     * anchors it consumed, ask again. Each round strictly shrinks the set, which
     * is what terminates the loop.
     *
     * @param list<array{0: int, 1: int, 2: int}> $cluster
     * @param array<int, string>     $signatures
     * @param array<int, FileTokens> $tokens
     * @return list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}>
     */
    private function fromCluster(array $cluster, int $fileA, int $fileB, array $signatures, array $tokens, bool $normalizedOnly): array
    {
        $candidates = [];

        // Clusters still to mine. A cluster refused by the aligner puts its two
        // halves back here rather than disappearing — see the refusal branch
        // below, and {@see splitAtWidestGap()} for why halves and not the whole.
        // A worklist rather than recursion because a decomposition chain is as
        // long as the cluster has gaps, and depth is not a resource worth
        // spending on a shape the corpus chooses.
        $queue = [$cluster];

        while ($queue !== []) {
        $remaining = array_pop($queue);

        while ($remaining !== []) {
                $candidate = $this->classifier->classify(
                    $remaining,
                    $signatures[$fileA],
                    $signatures[$fileB],
                    $this->config->minTokens,
                    $normalizedOnly,
                    $fileA === $fileB,
                );

                if ($candidate['kind'] === 'none') {
                    break;
                }


                // Two copies in one file are only a clone if they are in
                // different places; a stretch of code is not a duplicate of
                // itself. Sharing a start is the obvious way to fail that, and
                // it is not the only one: two ranges that merely *overlap*
                // describe one region matched against a shifted view of
                // itself, which is a statement about the file's periodicity
                // and not about duplication.
                $selfOverlapping = $fileA === $fileB
                    && max($candidate['startA'], $candidate['startB'])
                        < min($candidate['startA'] + $candidate['lengthA'], $candidate['startB'] + $candidate['lengthB']);

                // How much of the anchor set this candidate is entitled to take
                // with it, which is not always the span it claims.
                //
                // A self-overlapping candidate cannot be reported as it stands,
                // and consuming its whole span before refusing it is how the
                // duplication *inside* it used to be lost. In a file built from
                // many near-identical blocks, block i matches block i+1 for
                // every i, so a single shift's anchors run the whole length of
                // the file on one perfectly colinear diagonal — the chainer has
                // no grounds to stop, and the candidate it returns spans nearly
                // the whole file against nearly the whole file. On phpunit's
                // `MetadataTest.php` that swallowed 19 of the raw view's 24
                // shift bands: each was consumed by one candidate that was then
                // thrown away, and every block-against-block duplication inside
                // went with it.
                //
                // So it consumes only as far as its own shift — the most two
                // copies that far apart could ever span without running into
                // each other — and the rest is left for the next round to find.
                $claimA = $candidate['lengthA'];
                $claimB = $candidate['lengthB'];
                $shift  = 0;

                if ($selfOverlapping) {
                    $shift  = max($candidate['startA'], $candidate['startB']) - min($candidate['startA'], $candidate['startB']);
                    $claimA = min($claimA, $shift);
                    $claimB = min($claimB, $shift);
                }

                $left     = self::withoutSpan($remaining, $candidate['startA'], $claimA, $candidate['startB'], $claimB);
                $narrowed = count($left) !== count($remaining);

                if (!$narrowed) {
                    // The narrowed claim took nothing — two copies at zero
                    // distance, which is one region and not two. Fall back to
                    // the full span so the loop cannot spin on it forever.
                    $left = self::withoutSpan($remaining, $candidate['startA'], $candidate['lengthA'], $candidate['startB'], $candidate['lengthB']);
                }

                if (count($left) === count($remaining)) {
                    break; // no progress is possible; stop rather than spin
                }

                // The claim actually applied above: the narrowed one where it
                // took anchors, the full span where it did not.
                $tookA = $narrowed ? $claimA : $candidate['lengthA'];
                $tookB = $narrowed ? $claimB : $candidate['lengthB'];

                // The anchors this round is taking out of the cluster. Held
                // because a refusal has to be able to give them back: they are
                // the evidence *inside* the reading, and the reading may be
                // wrong about them (ruling O).
                $consumed = self::withinSpan($remaining, $candidate['startA'], $tookA, $candidate['startB'], $tookB);

                if ($selfOverlapping) {
                    // A self-overlapping chain is not a clone, but it is not
                    // noise either — it is a *periodicity* statement, and M2
                    // audit ruling J is about not throwing it away.
                    //
                    // A chain aligning [s, s+L) with [s+D, s+D+L) asserts that
                    // token p matches token p+D everywhere it covers: the region
                    // has period D, in the elementary sense (Lothaire,
                    // Combinatorics on Words — the definition, no theorem
                    // needed). A region of length L+D with period D *is* a run
                    // of ⌊(L+D)/D⌋ blocks of D tokens that agree pairwise, which
                    // is precisely the "N mutually-similar blocks" the ruling
                    // says to report as one class naming every block.
                    //
                    // So the chain is restricted to **one period step** — the
                    // anchors this round just claimed, which are exactly those
                    // matching one block against the next — and re-classified.
                    // That candidate's two ranges are D apart and at most D
                    // long, so they are disjoint by construction: an ordinary
                    // pair, judged by the ordinary `verify()` on its own
                    // evidence. The loop's existing claim-narrowing then steps
                    // to the following block, and `classes()`'s union-find
                    // closes the consecutive relation transitively into the one
                    // class the ruling asks for. Nothing here is a new rule:
                    // the period is read off the candidate, the block count
                    // falls out of the loop, and `minTokens`/`minLines` decide
                    // whether the blocks are big enough to report. No constant
                    // is introduced and `SITE_OVERLAP` is untouched.
                    //
                    // Only when the narrowed claim actually took anchors: a
                    // shift of zero is a region against itself at no distance,
                    // which has no period to decompose and is refused as before.
                    $claimed   = self::withinSpan($remaining, $candidate['startA'], $claimA, $candidate['startB'], $claimB);
                    $remaining = $left;

                    if (!$narrowed || $shift <= 0) {
                        continue;
                    }

                    $period = $this->classifier->classify(
                        $claimed,
                        $signatures[$fileA],
                        $signatures[$fileB],
                        $this->config->minTokens,
                        $normalizedOnly,
                        true,
                    );

                    if ($period['kind'] === 'none') {
                        continue;
                    }

                    // The one period step can still land overlapping — the
                    // re-classified chain may extend past the window it was
                    // built from. It is a clone only if it does not.
                    if (
                        max($period['startA'], $period['startB'])
                        < min($period['startA'] + $period['lengthA'], $period['startB'] + $period['lengthB'])
                    ) {
                        continue;
                    }

                    $candidate = $period;
                } else {
                    $remaining = $left;
                }

                $lines     = self::lineSpan($tokens[$fileA], $candidate['startA'], $candidate['lengthA']);
                $candidate = $this->classifier->verify(
                    $candidate,
                    $signatures[$fileA],
                    $signatures[$fileB],
                    $this->config->minTokens,
                    $this->config->minLines,
                    $lines,
                    $normalizedOnly,
                );

                if (!$candidate['accepted']) {
                    // Too short to be a clone ends the cluster rather than
                    // skipping one round of it.
                    //
                    // Each round takes the best-scoring chain and removes what
                    // it consumed, so a later round's anchors are a strict
                    // subset of this one's: every chain it could build was
                    // already available here, and this round returned the
                    // best-scoring of them. Once that one is under the size
                    // thresholds, the rounds that follow are asking the same
                    // question of strictly less evidence.
                    //
                    // Stated as what it is: score is coverage minus gaps, not
                    // span, so a lower-scoring chain could in principle reach
                    // further by spanning wider gaps — this is a bound on the
                    // ordinary case, not a theorem. It is measured instead.
                    // Without it, one 228-anchor cluster on phpunit runs 147
                    // rounds and produces nothing; with it, the corpus loses no
                    // reported clone (`bench/check-superset.php` is unchanged
                    // on phpunit and still passes on php-parser) and the run
                    // is 22% faster.
                    if ($candidate['reason'] === 'below thresholds') {
                        break;
                    }

                    // **Ruling O (M3 close audit).** A refused candidate must
                    // not consume the evidence inside it.
                    //
                    // The aligner's refusal is the one verdict that says
                    // nothing about the candidate's parts. "Below thresholds"
                    // and "below the diversity floor" are inherited by every
                    // sub-span, so a refusal on either is final for everything
                    // inside it. "These two spans are too dissimilar taken as
                    // one alignment" is not: the chain that built them is made
                    // of exact runs, and the refusal means only that the gaps
                    // *between* those runs cost more than acceptance allows.
                    //
                    // Left as it was, the reading takes those exact runs with
                    // it. On the phpunit pair ruling O names, one chain spans
                    // A=[34,423) against B=[31,382) with two gaps — 78 tokens
                    // and 20 — and is refused at similarity 0.00. Inside it sit
                    // two of Rabin-Karp's three clones for that file pair, 193
                    // tokens and 98 tokens, both *exact*; both were consumed by
                    // the refusal and never reported.
                    //
                    // The evidence is therefore returned, decomposed rather
                    // than restored whole. Restoring it whole would rebuild the
                    // same chain next round and spin; and the reading is not
                    // merely unlucky, it is wrong in a locatable way — it
                    // bridges its widest gap, which is precisely the material
                    // the alignment could not pay for. So the cluster splits
                    // there, and each side is mined as a cluster in its own
                    // right ({@see splitAtWidestGap()}).
                    //
                    // This is ruling J's mechanism applied to a second refusal:
                    // a candidate that cannot be reported as it stands gives
                    // back what it holds, narrowed by a rule read off the
                    // candidate itself. No constant is introduced — the split
                    // point is the widest gap the chain already reported — and
                    // termination is unchanged, because both halves exclude the
                    // anchors bridging that gap and so are strictly smaller
                    // than the set they came from.
                    if (CloneClassifier::refusedOnSimilarity($candidate)) {
                        // Ruling R (2). The aligner has just said these two spans
                        // cost too much *in order*; the next question about the
                        // same evidence is whether they hold the same material in
                        // a different arrangement. Asked here, beside the
                        // decomposition rather than instead of it, because ruling
                        // O's rule applies to this verdict too: an acceptance that
                        // replaced the refusal would consume the exact clones
                        // inside the refused reading exactly as the old defect did.
                        $orderFree = $normalizedOnly
                            ? null
                            : $this->classifier->orderFreeVerdict($candidate, $signatures[$fileA], $signatures[$fileB]);

                        if ($orderFree !== null && !$selfOverlapping) {
                            $lines = $tokens[$fileA]->tokenRealLines[$orderFree['startA'] + $orderFree['lengthA'] - 1]
                                - $tokens[$fileA]->tokenRealLines[$orderFree['startA']] + 1;

                            if ($lines >= $this->config->minLines) {
                                $candidates[] = [
                                    'fileA'     => $fileA,
                                    'fileB'     => $fileB,
                                    'startA'    => $orderFree['startA'],
                                    'lengthA'   => $orderFree['lengthA'],
                                    'startB'    => $orderFree['startB'],
                                    'lengthB'   => $orderFree['lengthB'],
                                    'kind'      => $orderFree['kind'],
                                    'tokens'    => $orderFree['tokens'],
                                    'gaps'      => $orderFree['gaps'],
                                    'displaced' => $orderFree['displaced'],
                                ];
                                $this->orderFreeVerdicts++;
                            }
                        }

                        foreach (self::splitAtWidestGap($consumed, $candidate) as $half) {
                            $queue[] = $half;
                        }
                    }

                    continue;
                }

                $candidates[] = [
                    'fileA'   => $fileA,
                    'fileB'   => $fileB,
                    'startA'  => $candidate['startA'],
                    'lengthA' => $candidate['lengthA'],
                    'startB'  => $candidate['startB'],
                    'lengthB' => $candidate['lengthB'],
                    'kind'      => $candidate['kind'],
                    'tokens'    => $candidate['tokens'],
                    'gaps'      => $candidate['gaps'],
                    'displaced' => $candidate['displaced'],
                ];
        }
        }

        return $candidates;
    }

    /**
     * A refused reading's evidence, split at the gap that refused it.
     *
     * The candidate's own gaps are the divergences its chain had to bridge, and
     * `verify()` refuses when their total cost — `max(gapA, gapB)` summed, the
     * edits the chain witnesses — exceeds the aligner's budget for the span.
     * The single widest gap is therefore the largest contributor to the verdict,
     * and it is the one place where the reading is most plausibly two readings
     * rather than one.
     *
     * So the anchors are cut there: those wholly before the gap on **both**
     * sides, and those wholly after it on both sides. Both conditions are
     * required because an anchor is evidence about a pair of positions; one
     * that straddles the gap on either side is evidence *for* the bridge, and
     * it is exactly what must not survive into either half — that is what stops
     * a half from rebuilding the refused chain, and what makes each half
     * strictly smaller than the set it came from.
     *
     * Anchors that fall inside the gap on one side and outside on the other
     * belong to neither half and are dropped. They are the material the
     * alignment could not pay for.
     *
     * A half of one anchor is kept, not discarded: a single exact anchor long
     * enough to clear `minTokens` is a Type-1 clone, and on the pair this
     * ruling was recorded for that is exactly what both recovered clones are.
     * A half is dropped only when it is empty, or when it is the whole set —
     * the latter cannot happen while a gap has non-zero width on both sides,
     * but it is the condition that makes termination unconditional rather than
     * argued.
     *
     * Only gaps interior to the span are considered, matching the filter
     * `verify()` applies when it totals the cost: an edge divergence (ruling C)
     * sits outside the aligned region and did not contribute to the refusal, so
     * splitting there would cut on evidence that was never weighed.
     *
     * @param  list<array{0: int, 1: int, 2: int}> $anchors
     * @param  array{startA: int, lengthA: int, startB: int, lengthB: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, ...} $candidate
     * @return list<list<array{0: int, 1: int, 2: int}>>
     */
    private static function splitAtWidestGap(array $anchors, array $candidate): array
    {
        $widest = null;
        $cost   = 0;

        foreach ($candidate['gaps'] as $gap) {
            [$gapStartA, $gapLengthA, , $gapLengthB] = $gap;

            if ($gapStartA < $candidate['startA'] || $gapStartA >= $candidate['startA'] + $candidate['lengthA']) {
                continue;
            }

            if (max($gapLengthA, $gapLengthB) > $cost) {
                $cost   = max($gapLengthA, $gapLengthB);
                $widest = $gap;
            }
        }

        if ($widest === null || $cost === 0) {
            return [];
        }

        [$gapStartA, $gapLengthA, $gapStartB, $gapLengthB] = $widest;

        $before = [];
        $after  = [];

        foreach ($anchors as $anchor) {
            if ($anchor[0] + $anchor[2] <= $gapStartA && $anchor[1] + $anchor[2] <= $gapStartB) {
                $before[] = $anchor;

                continue;
            }

            if ($anchor[0] >= $gapStartA + $gapLengthA && $anchor[1] >= $gapStartB + $gapLengthB) {
                $after[] = $anchor;
            }
        }

        $halves = [];

        foreach ([$before, $after] as $half) {
            if ($half !== [] && count($half) < count($anchors)) {
                $halves[] = $half;
            }
        }

        return $halves;
    }

    /**
     * Is this anchor part of the duplication the given region describes?
     *
     * It is when it overlaps that region on *both* sides: that is what it means
     * for it to be the same duplication. An anchor overlapping on only one side
     * belongs to a different clone that happens to share a file.
     *
     * @param array{0: int, 1: int, 2: int} $anchor
     */
    private static function spanHolds(array $anchor, int $startA, int $lengthA, int $startB, int $lengthB): bool
    {
        return $anchor[0] < $startA + $lengthA && $startA < $anchor[0] + $anchor[2]
            && $anchor[1] < $startB + $lengthB && $startB < $anchor[1] + $anchor[2];
    }

    /**
     * The anchors left once a candidate has claimed its region — the ones it
     * did not take, which have to survive for the next round to find.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private static function withoutSpan(array $anchors, int $startA, int $lengthA, int $startB, int $lengthB): array
    {
        $left = [];

        foreach ($anchors as $anchor) {
            if (!self::spanHolds($anchor, $startA, $lengthA, $startB, $lengthB)) {
                $left[] = $anchor;
            }
        }

        return $left;
    }

    /**
     * The complement of {@see withoutSpan()} — the anchors a candidate's region
     * does claim. Used by the periodicity decomposition in {@see fromCluster()},
     * where one period step's own anchors are re-classified on their own.
     *
     * @param list<array{0: int, 1: int, 2: int}> $anchors
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private static function withinSpan(array $anchors, int $startA, int $lengthA, int $startB, int $lengthB): array
    {
        $held = [];

        foreach ($anchors as $anchor) {
            if (self::spanHolds($anchor, $startA, $lengthA, $startB, $lengthB)) {
                $held[] = $anchor;
            }
        }

        return $held;
    }

    /**
     * Is this candidate already covered by one the raw view produced?
     *
     * A *covering* question, so it asks {@see subsumes()} and not the symmetric
     * {@see overlaps()} — see that method for why the two are now different.
     *
     * @param array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>} $candidate
     * @param list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}> $existing
     */
    private static function alreadyReported(array $candidate, array $existing): bool
    {
        foreach ($existing as $other) {
            if (
                $other['fileA'] === $candidate['fileA']
                && $other['fileB'] === $candidate['fileB']
                && self::coveredBy($candidate['startA'], $candidate['lengthA'], $other['startA'], $other['lengthA'])
                && self::coveredBy($candidate['startB'], $candidate['lengthB'], $other['startB'], $other['lengthB'])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this range mostly inside that one?
     *
     * Directional, and the direction is the whole point. {@see subsumes()}
     * measures the shared span against the *shorter* of the two ranges, which is
     * the right question for "do these describe the same duplication" and the
     * wrong one for "is this already covered": a short range and a long one that
     * merely contains it share all of the short one, so asking symmetrically
     * lets the short range suppress the long one.
     *
     * That is not hypothetical. Once namespaced names normalize, the normalized
     * view chains a Builder header and its class body into one match while the
     * raw view still breaks at the header; the raw fragment then covered all of
     * itself inside the longer normalized candidate, `alreadyReported()` called
     * the longer one a repeat, and the header duplication stopped being reported
     * at all. Measured against the candidate's own length it is 39% covered,
     * which is not a repeat of anything.
     *
     * The same shape as M2 ruling G, which separated {@see overlaps()} from
     * {@see subsumes()} for site identity; this separates the covering question
     * from both.
     */
    private static function coveredBy(int $start, int $length, int $otherStart, int $otherLength): bool
    {
        $shared = min($start + $length, $otherStart + $otherLength) - max($start, $otherStart);

        return $length > 0 && $shared >= self::SITE_OVERLAP * $length;
    }

    /**
     * Do two candidates describe the same duplication, in both files?
     *
     * Reads {@see subsumes()} rather than {@see overlaps()}: the raw candidate
     * whose gap this is asking about is often the shorter of the two, because the
     * normalized view chains through renames the raw view breaks on, and a rename
     * that the normalized view sees as one unbroken match is a rename whether or
     * not the two spans happen to be the same length. Measured on both bench
     * corpora, the symmetric rule here changes no reported clone either way.
     *
     * @param array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>} $left
     * @param array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>} $right
     */
    private static function samePlace(array $left, array $right): bool
    {
        return $left['fileA'] === $right['fileA']
            && $left['fileB'] === $right['fileB']
            && self::subsumes($left['startA'], $left['lengthA'], $right['startA'], $right['lengthA'])
            && self::subsumes($left['startB'], $left['lengthB'], $right['startB'], $right['lengthB']);
    }

    /**
     * Are these two token ranges the same **site** — sharing at least
     * SITE_OVERLAP of the **longer** one?
     *
     * The longer, not the shorter, and that is the whole content of M2 audit
     * ruling G. Measured against the shorter range, containment scores 1.0: a
     * 378-token block sitting inside a 14,584-token span is "the same place" as
     * the span, so the block is absorbed and stops being reported at all.
     * Measured against the longer it scores 0.026 and stays its own site.
     *
     * The derivation is symmetry. "Same place" is an equivalence relation, and a
     * relation that holds between a range and something containing it is not
     * one — it makes every block in a repetitive file identical to the file. The
     * ordinary case this rule exists for is untouched, because there the two
     * ranges are nearly the same size: one block found twice, ten tokens longer
     * the second time, is 100 shared of 110 = 0.91, still one site.
     *
     * The scope is site identity, which is {@see classes()} and nothing else.
     * The other two questions this file asks about a pair of ranges — "is this
     * already reported?" and "is this the same duplication the other view saw?"
     * — are *covering* questions, and {@see subsumes()} answers those. Applying
     * symmetry to them as well was measured and is not done: it makes the
     * pairs-to-classes pass emit the same material twice on a second alignment
     * (probe 4's reordered class, whose displaced block then also appears as its
     * own gapped clone), and takes phpunit from 369 reported clones to 401 and
     * from 68,399 duplicated lines to 95,985 for 20 baseline locations.
     */
    private static function overlaps(int $startA, int $lengthA, int $startB, int $lengthB): bool
    {
        $shared = min($startA + $lengthA, $startB + $lengthB) - max($startA, $startB);
        $longer = max($lengthA, $lengthB);

        return $longer > 0 && $shared >= self::SITE_OVERLAP * $longer;
    }

    /**
     * Does one of these two ranges lie (mostly) inside the other?
     *
     * Deliberately asymmetric, and deliberately not {@see overlaps()}. This is
     * the rule that governed site identity too until ruling G separated them; it
     * survives unchanged at the two call sites the ruling did not address, where
     * containment is the answer wanted rather than a defect: a candidate lying
     * inside one the report already carries is the same finding a second time.
     */
    private static function subsumes(int $startA, int $lengthA, int $startB, int $lengthB): bool
    {
        $shared  = min($startA + $lengthA, $startB + $lengthB) - max($startA, $startB);
        $shorter = min($lengthA, $lengthB);

        return $shorter > 0 && $shared >= self::SITE_OVERLAP * $shorter;
    }

    /**
     * Turn pairwise candidates into clone classes.
     *
     * Every engine before this one reported *classes*: N copies of one block are
     * one finding naming N places. A seed-and-extend engine naturally produces
     * *pairs* instead, and N copies give N(N−1)/2 of them — the same duplication
     * counted a quadratic number of times, which inflates the duplicated-line
     * total without finding anything extra.
     *
     * The rules, stated so they can be argued with:
     *
     *   1. every accepted candidate contributes two **locations**, each a token
     *      range in a file;
     *   2. two locations in the same file are the same **site** when their ranges
     *      overlap by at least {@see SITE_OVERLAP} of the *longer* — one block
     *      found twice, a few tokens longer the second time, is one place, while
     *      a block *contained* in a much larger span is not (ruling G);
     *   3. candidates are **edges** between sites, and each connected component
     *      of that graph is one clone class;
     *   4. a class is reported once, naming every site in it, sized by its
     *      largest member, gapped if any of its edges was, and carrying the
     *      divergences of its edges.
     *
     * Sites are formed in sorted order and components are emitted in sorted
     * order, so the classes are a function of the candidates and not of the order
     * they were produced in.
     *
     * @param list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}> $candidates
     * @param array<int, string>     $names
     * @param array<int, FileTokens> $tokens
     * @return list<CodeClone>
     */
    private function classes(array $candidates, array $names, array $tokens): array
    {
        if ($candidates === []) {
            return [];
        }

        /** @var list<array{0: int, 1: int, 2: int}> $locations file, start, length */
        $locations = [];

        foreach ($candidates as $candidate) {
            $locations[] = [$candidate['fileA'], $candidate['startA'], $candidate['lengthA']];
            $locations[] = [$candidate['fileB'], $candidate['startB'], $candidate['lengthB']];
        }

        usort($locations, static fn(array $a, array $b): int => [$a[0], $a[1], -$a[2]] <=> [$b[0], $b[1], -$b[2]]);

        /** @var list<array{0: int, 1: int, 2: int}> $sites */
        $sites = [];

        foreach ($locations as $location) {
            $merged = false;

            foreach ($sites as $index => $site) {
                if ($site[0] === $location[0] && self::overlaps($site[1], $site[2], $location[1], $location[2])) {
                    // Keep the widest range seen for the site, so a class is
                    // sized by the most complete view of the duplication.
                    if ($location[2] > $site[2]) {
                        $sites[$index] = [$site[0], min($site[1], $location[1]), $location[2]];
                    }

                    $merged = true;

                    break;
                }
            }

            if (!$merged) {
                $sites[] = $location;
            }
        }

        $siteOf = function (int $file, int $start, int $length) use ($sites): int {
            foreach ($sites as $index => $site) {
                if ($site[0] === $file && self::overlaps($site[1], $site[2], $start, $length)) {
                    return $index;
                }
            }

            return -1;
        };

        $parent = [];

        foreach ($sites as $index => $_) {
            $parent[$index] = $index;
        }

        $find = static function (int $node) use (&$parent): int {
            while ($parent[$node] !== $node) {
                $parent[$node] = $parent[$parent[$node]];
                $node          = $parent[$node];
            }

            return $node;
        };

        /** @var array<int, list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}>> $edgesOf */
        $edgesOf = [];

        foreach ($candidates as $candidate) {
            $a = $siteOf($candidate['fileA'], $candidate['startA'], $candidate['lengthA']);
            $b = $siteOf($candidate['fileB'], $candidate['startB'], $candidate['lengthB']);

            if ($a === -1 || $b === -1) {
                continue;
            }

            $rootA = $find($a);
            $rootB = $find($b);

            if ($rootA !== $rootB) {
                $parent[max($rootA, $rootB)] = min($rootA, $rootB);
            }

            $edgesOf[$find($a)][] = $candidate;
        }

        // Edges were filed under whatever root existed at the time, so re-file
        // them under the roots the completed unions produced.
        /** @var array<int, list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}>> $components */
        $components = [];

        foreach ($edgesOf as $root => $edges) {
            foreach ($edges as $edge) {
                $components[$find($root)][] = $edge;
            }
        }

        /** @var array<int, list<int>> $members */
        $members = [];

        foreach ($sites as $index => $_) {
            $members[$find($index)][] = $index;
        }

        $clones = [];
        $roots  = array_keys($components);
        sort($roots);

        foreach ($roots as $root) {
            $clone = $this->classToClone($components[$root], $members[$root] ?? [], $sites, $names, $tokens);

            if ($clone !== null) {
                $clones[] = $clone;
            }
        }

        return $clones;
    }

    /**
     * @param list<array{fileA: int, fileB: int, startA: int, lengthA: int, startB: int, lengthB: int, kind: string, tokens: int, gaps: list<array{0: int, 1: int, 2: int, 3: int}>, displaced: list<array{0: int, 1: int, 2: int}>}> $edges
     * @param list<int>                        $memberSites
     * @param list<array{0: int, 1: int, 2: int}> $sites
     * @param array<int, string>               $names
     * @param array<int, FileTokens>           $tokens
     */
    private function classToClone(array $edges, array $memberSites, array $sites, array $names, array $tokens): ?CodeClone
    {
        if (count($memberSites) < 2) {
            return null;
        }

        sort($memberSites);

        // Two ranges in one file that overlap are one region.
        //
        // The candidate loop says this already — "two ranges that merely
        // *overlap* describe one region matched against a shifted view of
        // itself" — and refuses such a candidate. A class is assembled from many
        // candidates, though, and site identity is a question about 80% overlap
        // (ruling G), so two ranges overlapping by less than that stayed two
        // sites of one class and the reader was told a region duplicates itself.
        // Measured on php-parser they miss the bar by a hair: 98 tokens shared
        // where 100 were needed, 119 where 132 were, 92 where 107 were.
        //
        // Merging rather than dropping, because dropping was measured first and
        // is wrong: it cost `MetadataTest`'s class 16 of its 88 sites and turned
        // 226 of the token bag's own pairs into misses. The union keeps every
        // token both readings claimed.
        //
        // Any overlap at all, which is a membership test and not a second
        // fraction to tune: it is the loop's own rule applied one layer up.
        // Containment falls out of it — a contained range overlaps, so the union
        // is the container.
        /** @var list<array{0: int, 1: int, 2: int}> $ranges */
        $ranges = [];

        foreach ($memberSites as $member) {
            $ranges[] = $sites[$member];
        }

        usort($ranges, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $merged = [];

        foreach ($ranges as [$file, $start, $length]) {
            $last = count($merged) - 1;

            if ($last >= 0 && $merged[$last][0] === $file && $start <= $merged[$last][1] + $merged[$last][2] - 1) {
                $end                = max($merged[$last][1] + $merged[$last][2], $start + $length) - 1;
                $merged[$last][2]   = $end - $merged[$last][1] + 1;

                continue;
            }

            $merged[] = [$file, $start, $length];
        }

        $ranges = $merged;

        if (count($ranges) < 2) {
            return null;
        }

        [$leadFile, $leadStart, $leadLength] = $ranges[0];

        $lines = self::lineSpan($tokens[$leadFile], $leadStart, $leadLength);

        if ($lines < $this->config->minLines || $leadLength < $this->config->minTokens) {
            return null;
        }

        $gapped      = false;
        $reordered   = false;
        $divergences = [];
        $seen        = [];

        foreach ($edges as $edge) {
            if ($edge['kind'] === 'gapped' || $edge['kind'] === 'reordered') {
                $gapped = true;
            }

            if ($edge['kind'] === 'reordered') {
                $reordered = true;
            }

            foreach ($edge['gaps'] as [$startA, $lengthA, $startB, $lengthB]) {
                // Inside the span, or past its edge? A bounded edge divergence
                // (ruling C) is material one copy has beyond the shared part, so
                // it belongs to neither side's span — the same test
                // {@see CloneClassifier::acceptance()} uses to decide what the
                // aligner is and is not responsible for. Reported rather than
                // inferred: once a class is sized by its lead member, no reader
                // and no checker can tell the two apart from line numbers alone.
                $isEdge = $startA < $edge['startA'] || $startA >= $edge['startA'] + $edge['lengthA'];

                foreach ([[$edge['fileA'], $startA, $lengthA], [$edge['fileB'], $startB, $lengthB]] as [$file, $start, $length]) {
                    $key = $file . ':' . $start . ':' . $length;

                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key]    = true;
                    $divergences[] = self::divergence($names[$file], $tokens[$file], $start, $length, $isEdge);
                }
            }

            // A reordered clone's "divergence" is not a place the copies stop
            // agreeing — it is the block that moved. Naming it the same way
            // (file, lines, token range) is what "the displaced segments named"
            // means in plan §1 Stage D3: a reader needs to know *which*
            // statements swapped, and the anchors the best in-order chain had
            // to drop are exactly that.
            foreach ($edge['displaced'] as [$posA, $posB, $length]) {
                foreach ([[$edge['fileA'], $posA], [$edge['fileB'], $posB]] as [$file, $start]) {
                    $key = $file . ':' . $start . ':' . $length;

                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key]    = true;
                    $divergences[] = self::divergence($names[$file], $tokens[$file], $start, $length);
                }
            }
        }

        usort(
            $divergences,
            static fn(CloneDivergence $a, CloneDivergence $b): int
                => [$a->file, $a->startToken] <=> [$b->file, $b->startToken],
        );

        $files = [];

        foreach ($ranges as [$file, $start, $length]) {

            // Each occurrence is measured on its own token range rather than
            // given the lead's. A site carries its own length here, and the
            // sites of one class routinely differ: they were merged for being
            // the same place, not for spanning equally many lines, and comments
            // and blank lines between two copies are not significant tokens.
            $files[] = new CodeCloneFile(
                $names[$file],
                $tokens[$file]->tokenRealLines[$start] ?? 1,
                self::lineSpan($tokens[$file], $start, $length),
                $length,
                $start,
            );
        }

        $clone = new CodeClone($files[0], $files[1], $lines, $leadLength, $gapped, $divergences, $reordered);

        foreach (array_slice($files, 2) as $file) {
            $clone->add($file);
        }

        return $clone;
    }

    /** The divergence one gap describes on one side of a clone. */
    private static function divergence(string $name, FileTokens $tokens, int $start, int $length, bool $edge = false): CloneDivergence
    {
        $lines = $tokens->tokenRealLines;
        $last  = $length > 0 ? $start + $length - 1 : $start;

        return new CloneDivergence(
            $name,
            $lines[$start] ?? ($lines[$last] ?? 1),
            $lines[$last] ?? ($lines[$start] ?? 1),
            $start,
            $length,
            $edge,
        );
    }

    /** How many source lines a token range spans. */
    private static function lineSpan(FileTokens $tokens, int $start, int $length): int
    {
        if ($length <= 0) {
            return 0;
        }

        $lines = $tokens->tokenRealLines;
        $last  = $start + $length - 1;

        if (!isset($lines[$start], $lines[$last])) {
            return 0;
        }

        return $lines[$last] + 1 - $lines[$start];
    }

    /** How many fingerprints hit either frequency cap — reported, never silent. */
    public function cappedFingerprintCount(): int
    {
        return $this->cappedFingerprints;
    }

    /** How many occurrences the two frequency caps discarded in total. */
    public function discardedPostingCount(): int
    {
        return $this->discardedPostings;
    }

    /**
     * How many fingerprints hit {@see AnchorSet::SEED_PAIR_CAP} — never silent.
     *
     * Separate from {@see cappedFingerprintCount()} because it is a different
     * trade at a different axis: those two cap how many places a fingerprint
     * keeps, this caps how many pairs those places are enumerated into.
     */
    public function pairCappedFingerprintCount(): int
    {
        return $this->pairCappedFingerprints;
    }

    /** How many seed pairs the seed-pair cap discarded, in total. */
    public function discardedSeedPairCount(): int
    {
        return $this->discardedSeedPairs;
    }

    public function filesEncoded(): int
    {
        return $this->filesEncoded;
    }

    public function filesFingerprinted(): int
    {
        return $this->filesFingerprinted;
    }

    /**
     * How many candidates were a literal table repeating itself. Reported for
     * the same reason the caps are: a decision that removes findings should be
     * countable.
     */
    public function tableRepetitionCount(): int
    {
        return $this->tableRepetitions;
    }

    /** How many findings the bag channel contributed that the seeded path missed. */
    public function bagSeededCandidateCount(): int
    {
        return $this->bagSeededCandidates;
    }

    /** How many aligner refusals the order-free verdict answered differently. */
    public function orderFreeVerdictCount(): int
    {
        return $this->orderFreeVerdicts;
    }

    public function fileCount(): int
    {
        return count($this->files);
    }
}
