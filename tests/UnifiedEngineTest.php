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

use function count;
use function crc32;
use function substr;

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\AnchorSet;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\FingerprintIndex;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\UnifiedStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\Winnower;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\InvalidStrategyException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stages A–C of the unified engine.
 *
 * The load-bearing claim of the whole design is the winnowing guarantee: any
 * common token substring of length ≥ W + K − 1 must share a *selected*
 * fingerprint in both copies, so the sampling cannot lose a clone the tool is
 * contracted to find. That is a theorem, and the tests below check it the way a
 * theorem should be checked — over many synthetic pairs with a shared run
 * planted at a known place, not on two hand-written fixtures that were written
 * to pass.
 */
#[CoversClass(Winnower::class)]
#[CoversClass(FingerprintIndex::class)]
#[CoversClass(AnchorSet::class)]
#[CoversClass(UnifiedStrategy::class)]
final class UnifiedEngineTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/fixtures';

    private static function config(int $minTokens = 70, int $minLines = 5): StrategyConfiguration
    {
        return new StrategyConfiguration(
            minLines: $minLines,
            minTokens: $minTokens,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );
    }

    /**
     * A signature string of $tokens pseudo-random tokens.
     *
     * Deterministic by construction: the value at each position is a function of
     * the seed and the index, so a failure is reproducible from the test name
     * alone rather than from a seed printed in a log.
     */
    private static function signature(int $tokens, int $seed): string
    {
        $out = '';

        for ($i = 0; $i < $tokens; $i++) {
            $value = crc32($seed . ':' . $i);
            // The encoder production uses, so a synthetic signature cannot
            // drift from a real one — which it had, silently, until the token
            // width changed underneath it.
            $out .= FileTokens::token($value, 't' . $value);
        }

        return $out;
    }

    /**
     * One fingerprint selected at $count consecutive positions of a file, in
     * the shape `FingerprintIndex::add()` takes.
     *
     * @return list<array{0: string, 1: int}>
     */
    private static function selection(string $fingerprint, int $count): array
    {
        $selected = [];

        for ($position = 0; $position < $count; $position++) {
            $selected[] = [$fingerprint, $position];
        }

        return $selected;
    }

    /**
     * One fingerprint's posting list spread over $count files, already packed
     * the way the index hands it to {@see AnchorSet::build()}.
     *
     * @return list<int>
     */
    private static function postingsOverFiles(int $count): array
    {
        $postings = [];

        for ($file = 0; $file < $count; $file++) {
            $postings[] = ($file << 32) | 0;
        }

        return $postings;
    }

    // ── The guarantee ─────────────────────────────────────────────────────

    /** @return iterable<string, array{0: int}> */
    public static function thresholds(): iterable
    {
        foreach ([38, 50, 70, 120, 200] as $minTokens) {
            yield "minTokens={$minTokens}" => [$minTokens];
        }
    }

    #[Test]
    #[DataProvider('thresholds')]
    public function a_shared_run_at_the_guaranteed_length_always_shares_a_selected_fingerprint(int $minTokens): void
    {
        $winnower = Winnower::forMinTokens($minTokens);
        $length   = $winnower->guaranteedRunLength();

        for ($trial = 0; $trial < 40; $trial++) {
            $a = self::signature(400, $trial * 2);
            $b = self::signature(400, $trial * 2 + 1);

            // Plant the same run of exactly the guaranteed length in both, at
            // deliberately different offsets so nothing can align by accident.
            $startA = 30 + ($trial % 50);
            $startB = 130 + ($trial % 70);
            $run    = substr($a, $startA * FileTokens::TOKEN_BYTES, $length * FileTokens::TOKEN_BYTES);
            $b      = substr_replace($b, $run, $startB * FileTokens::TOKEN_BYTES, $length * FileTokens::TOKEN_BYTES);

            $inA = [];

            foreach ($winnower->select($a) as [$fingerprint, $position]) {
                if ($position >= $startA && $position + $winnower->seedLength <= $startA + $length) {
                    $inA[$fingerprint . ':' . ($position - $startA)] = true;
                }
            }

            $shared = false;

            foreach ($winnower->select($b) as [$fingerprint, $position]) {
                if (
                    $position >= $startB
                    && $position + $winnower->seedLength <= $startB + $length
                    && isset($inA[$fingerprint . ':' . ($position - $startB)])
                ) {
                    $shared = true;

                    break;
                }
            }

            self::assertTrue(
                $shared,
                "trial {$trial}: a shared run of {$length} tokens (the guaranteed length for "
                . "minTokens={$minTokens}) selected no common fingerprint",
            );
        }
    }

    #[Test]
    #[DataProvider('thresholds')]
    public function the_window_is_derived_so_the_guarantee_reaches_half_the_threshold(int $minTokens): void
    {
        $winnower = Winnower::forMinTokens($minTokens);

        // S = ceil(minTokens / 2), and W + K - 1 = S.
        self::assertSame(intdiv($minTokens + 1, 2), Winnower::guaranteeThresholdFor($minTokens));
        self::assertSame(Winnower::guaranteeThresholdFor($minTokens), $winnower->guaranteedRunLength());
        self::assertSame(Winnower::SEED_LENGTH, $winnower->seedLength);
        self::assertGreaterThanOrEqual(4, $winnower->window, 'below W=4 the sample stops being a sample');
    }

    #[Test]
    public function selection_density_tracks_the_two_over_w_plus_one_bound(): void
    {
        $winnower = Winnower::forMinTokens(70);
        $selected = $winnower->select(self::signature(20_000, 99));

        $grams   = 20_000 - $winnower->seedLength + 1;
        $density = count($selected) / $grams;
        $bound   = 2 / ($winnower->window + 1);

        // The published bound is an expectation, so this allows a margin rather
        // than pinning a number; the point is that the index samples ~10% of
        // positions and not 100%, which is the whole reason it is affordable.
        self::assertEqualsWithDelta($bound, $density, $bound * 0.25);
    }

    #[Test]
    public function selection_is_deterministic_and_ordered(): void
    {
        $winnower = Winnower::forMinTokens(70);
        $sig      = self::signature(2_000, 7);

        $first  = $winnower->select($sig);
        $second = $winnower->select($sig);

        self::assertSame($first, $second);

        $previous = -1;

        foreach ($first as [$_, $position]) {
            self::assertGreaterThan($previous, $position, 'positions must ascend, once each');
            $previous = $position;
        }
    }

    #[Test]
    public function a_signature_shorter_than_one_window_selects_nothing(): void
    {
        $winnower = Winnower::forMinTokens(70);

        self::assertSame([], $winnower->select(self::signature($winnower->seedLength - 1, 1)));
        self::assertSame([], $winnower->select(''));
    }

    // ── The floor ─────────────────────────────────────────────────────────

    #[Test]
    public function the_engine_refuses_a_threshold_below_its_floor(): void
    {
        // Refusing loudly is the specified behaviour: below 2K + 6 the window
        // drops under 4 and the index stops being a sample, so serving the run
        // anyway would quietly turn winnowing into an exhaustive scan.
        self::assertSame(38, Winnower::MINIMUM_MIN_TOKENS);

        $this->expectException(InvalidStrategyException::class);
        $this->expectExceptionMessageMatches('/at least 38/');

        new UnifiedStrategy(self::config(minTokens: 37));
    }

    #[Test]
    public function the_engine_accepts_its_floor_exactly(): void
    {
        $strategy = new UnifiedStrategy(self::config(minTokens: 38));

        self::assertSame(0, $strategy->fileCount());
    }

    // ── The two frequency caps ────────────────────────────────────────────

    #[Test]
    public function the_per_file_cap_is_counted_rather_than_applied_silently(): void
    {
        $index       = new FingerprintIndex();
        $fingerprint = 'abcdefgh';
        $overflow    = 17;

        // One file, repeating one fingerprint past the per-file cap. A second
        // file supplies the posting that makes it shared, so it is reachable.
        $index->add(0, self::selection($fingerprint, FingerprintIndex::PER_FILE_CAP + $overflow));
        $index->add(1, [[$fingerprint, 0]]);

        self::assertSame(1, $index->cappedFingerprintCount());
        self::assertSame($overflow, $index->discardedPostingCount());
        self::assertCount(FingerprintIndex::PER_FILE_CAP + 1, $index->sharedPostings()[$fingerprint]);
    }

    #[Test]
    public function the_per_file_cap_keeps_each_file_its_own_allowance(): void
    {
        $index       = new FingerprintIndex();
        $fingerprint = 'abcdefgh';
        $selected    = self::selection($fingerprint, FingerprintIndex::PER_FILE_CAP);

        $index->add(0, $selected);
        $index->add(1, $selected);

        // The cap is per file, not per fingerprint: two files at the cap keep
        // every occurrence between them, and nothing is reported as discarded.
        self::assertSame(0, $index->cappedFingerprintCount());
        self::assertSame(0, $index->discardedPostingCount());
        self::assertCount(2 * FingerprintIndex::PER_FILE_CAP, $index->sharedPostings()[$fingerprint]);
    }

    #[Test]
    public function the_postings_cap_is_counted_rather_than_applied_silently(): void
    {
        $index       = new FingerprintIndex();
        $fingerprint = 'abcdefgh';
        $overflow    = 250;

        for ($file = 0; $file < FingerprintIndex::POSTINGS_CAP + $overflow; $file++) {
            $index->add($file, [[$fingerprint, 0]]);
        }

        self::assertSame(1, $index->cappedFingerprintCount());
        self::assertSame($overflow, $index->discardedPostingCount());
        self::assertCount(FingerprintIndex::POSTINGS_CAP, $index->sharedPostings()[$fingerprint]);
    }

    #[Test]
    public function the_seed_pair_cap_is_counted_rather_than_applied_silently(): void
    {
        // A posting list long enough that the pairs it yields exceed the cap
        // while the list itself is nowhere near POSTINGS_CAP — which is the
        // whole point of ruling N: the list is bounded and the pairs are not.
        $count    = 400;
        $postings = self::postingsOverFiles($count);

        $seeds = new AnchorSet([], Winnower::SEED_LENGTH);
        $seeds->build(['abcdefgh' => $postings]);

        $possible = $count * ($count - 1) / 2;

        self::assertGreaterThan(AnchorSet::SEED_PAIR_CAP, $possible);
        self::assertSame(1, $seeds->pairCappedFingerprints());
        self::assertSame((int) $possible - AnchorSet::SEED_PAIR_CAP, $seeds->discardedSeedPairs());
    }

    #[Test]
    public function a_fingerprint_under_the_seed_pair_cap_reports_nothing_discarded(): void
    {
        $postings = self::postingsOverFiles(20);

        $seeds = new AnchorSet([], Winnower::SEED_LENGTH);
        $seeds->build(['abcdefgh' => $postings]);

        self::assertSame(0, $seeds->pairCappedFingerprints());
        self::assertSame(0, $seeds->discardedSeedPairs());
    }

    #[Test]
    public function postings_pack_and_unpack_a_file_and_a_position(): void
    {
        $index = new FingerprintIndex();
        $index->add(3, [['aaaaaaaa', 11]]);
        $index->add(9, [['aaaaaaaa', 4_000_000_000]]);

        $postings = $index->sharedPostings()['aaaaaaaa'];

        self::assertSame(3, FingerprintIndex::fileOf($postings[0]));
        self::assertSame(11, FingerprintIndex::positionOf($postings[0]));
        self::assertSame(9, FingerprintIndex::fileOf($postings[1]));
        self::assertSame(4_000_000_000, FingerprintIndex::positionOf($postings[1]));
    }

    #[Test]
    public function a_fingerprint_seen_once_is_not_a_seed(): void
    {
        $index = new FingerprintIndex();
        $index->add(0, [['aaaaaaaa', 0], ['bbbbbbbb', 5]]);
        $index->add(1, [['bbbbbbbb', 9]]);

        // Only 'bbbbbbbb' occurs twice; a lone fingerprint has no pair to extend
        // and keeping it would put corpus-sized data into the candidate stage.
        self::assertSame(['bbbbbbbb'], array_keys($index->sharedPostings()));
        self::assertSame(2, $index->fingerprintCount());
    }

    // ── Anchors ───────────────────────────────────────────────────────────

    #[Test]
    public function an_anchor_grows_a_seed_to_the_maximal_exact_match(): void
    {
        $shared = self::signature(120, 42);
        $a      = self::signature(40, 1) . $shared . self::signature(40, 2);
        $b      = self::signature(70, 3) . $shared . self::signature(10, 4);

        $winnower = Winnower::forMinTokens(70);
        $index    = new FingerprintIndex();
        $index->add(0, $winnower->select($a));
        $index->add(1, $winnower->select($b));

        $anchors = (new AnchorSet([0 => $a, 1 => $b], $winnower->seedLength))
            ->build($index->sharedPostings());

        self::assertNotSame([], $anchors, 'a 120-token shared run must be seeded');

        [$fileA, $posA, $fileB, $posB, $length] = $anchors[0];

        self::assertSame(0, $fileA);
        self::assertSame(1, $fileB);
        self::assertSame(40, $posA, 'the anchor must extend back to where the run really starts');
        self::assertSame(70, $posB);
        self::assertSame(120, $length, 'and forward to where it really ends — no more, no less');
    }

    #[Test]
    public function two_copies_inside_one_file_never_grow_into_each_other(): void
    {
        $shared = self::signature(90, 5);
        $file   = $shared . self::signature(10, 6) . $shared;

        $winnower = Winnower::forMinTokens(70);
        $index    = new FingerprintIndex();
        $index->add(0, $winnower->select($file));

        $anchors = (new AnchorSet([0 => $file], $winnower->seedLength))
            ->build($index->sharedPostings());

        self::assertNotSame([], $anchors);

        foreach ($anchors as [$fileA, $posA, $fileB, $posB, $length]) {
            self::assertSame($fileA, $fileB);
            self::assertLessThanOrEqual(
                $posB - $posA,
                $length,
                'a stretch of code reported as a duplicate of itself is not a clone',
            );
        }
    }

    #[Test]
    public function anchors_come_back_in_a_total_order(): void
    {
        $shared = self::signature(100, 11);
        $files  = [
            0 => self::signature(20, 21) . $shared,
            1 => self::signature(30, 22) . $shared,
            2 => self::signature(40, 23) . $shared,
        ];

        $winnower = Winnower::forMinTokens(70);
        $index    = new FingerprintIndex();

        foreach ($files as $id => $signature) {
            $index->add($id, $winnower->select($signature));
        }

        $anchors  = (new AnchorSet($files, $winnower->seedLength))->build($index->sharedPostings());
        $previous = null;

        foreach ($anchors as $anchor) {
            if ($previous !== null) {
                self::assertLessThan(0, $previous <=> $anchor, 'anchors must be strictly ordered');
            }

            $previous = $anchor;
        }

        self::assertCount(3, $anchors, 'three files sharing one run give three pairs');
    }

    // ── End to end ────────────────────────────────────────────────────────

    private function detect(string $directory, int $minTokens = 70, int $minLines = 5): CodeCloneMap
    {
        $files = (new \LucianoPereira\PhpcpdNext\Util\FileFinder())->find([$directory], ['.php'], [], false);
        sort($files);

        return (new Engine(self::config($minTokens, $minLines), 'unified'))->detect($files);
    }

    #[Test]
    public function the_engine_is_selectable_by_name(): void
    {
        self::assertInstanceOf(
            UnifiedStrategy::class,
            (new Engine(self::config()))->strategyFor('unified'),
        );
    }

    #[Test]
    public function it_finds_the_duplication_in_the_clone_fixtures(): void
    {
        $map = $this->detect(self::FIXTURES . '/with_clones', minTokens: 50);

        self::assertGreaterThan(0, $map->count());
        self::assertGreaterThan(0, $map->numberOfDuplicatedLines());
    }

    #[Test]
    public function it_reports_nothing_on_a_file_with_no_duplication(): void
    {
        self::assertSame(0, $this->detect(self::FIXTURES . '/no_clones', minTokens: 50)->count());
    }

    #[Test]
    public function reversing_the_file_list_does_not_change_the_report(): void
    {
        // The three engines this one replaces all fail this at their embedding
        // API: reversing the list makes rabin-karp swap which copy it names
        // first, and makes tokenbag report a different set of clones. This
        // engine assigns file ids from the sorted path list, so nothing
        // downstream can see the order the caller used.
        $files = (new \LucianoPereira\PhpcpdNext\Util\FileFinder())
            ->find([self::FIXTURES . '/type3'], ['.php'], [], false);
        sort($files);

        $forward  = (new Engine(self::config(50), 'unified'))->detect($files);
        $reversed = (new Engine(self::config(50), 'unified'))->detect(array_reverse($files));

        $render = static fn(CodeCloneMap $m): string => implode("\n", array_map(
            static fn($clone): string => (string) json_encode($clone->toArray()),
            $m->clones(),
        ));

        self::assertGreaterThan(0, $forward->count(), 'the fixtures must contain clones for this to mean anything');
        self::assertSame($render($forward), $render($reversed));
    }

    #[Test]
    public function every_gapless_clone_is_a_real_exact_match_in_one_of_the_two_views(): void
    {
        // A property test on the engine's own output. A clone reported *without*
        // divergences claims the two copies agree the whole way, and that claim
        // has to hold in one of the two views the engine works in: token for
        // token as written, or token for token after normalization. A failure
        // here means the extension arithmetic is wrong, not that a threshold is.
        //
        // Every token on the reported start line is tried, because a clone need
        // not begin at the first one — in these fixtures five tokens share the
        // start line and the clone begins at the third.
        $checked = 0;

        foreach (['with_clones', 'type3', 'type2'] as $directory) {
            $map = $this->detect(self::FIXTURES . '/' . $directory, minTokens: 50);

            foreach ($map->clones() as $clone) {
                if ($clone->divergences() !== []) {
                    continue; // a gapped clone does not claim the copies agree
                }

                $label = [];

                foreach ($clone->files() as $file) {
                    $label[] = basename($file->name) . ':' . $file->startLine;
                }

                self::assertTrue(
                    $this->matchesInSomeView($clone, raw: true) || $this->matchesInSomeView($clone, raw: false),
                    sprintf(
                        'gapless clone %s (%d tokens) is not an exact match in either view',
                        implode(' + ', $label),
                        $clone->numberOfTokens(),
                    ),
                );

                $checked++;
            }
        }

        self::assertGreaterThan(0, $checked, 'the property must have been exercised on something');
    }

    /** Are every copy's tokens identical, under the raw or the normalized view? */
    private function matchesInSomeView(\LucianoPereira\PhpcpdNext\CodeClone $clone, bool $raw): bool
    {
        $encoder = new \LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy(
            new StrategyConfiguration(
                minLines: 5,
                minTokens: 50,
                normalization: $raw ? Normalization::Raw : Normalization::TypeAnchored,
                minSimilarity: 0.7,
            ),
        );

        $copies = [];

        foreach ($clone->files() as $file) {
            $tokens = $encoder->tokenize((string) file_get_contents($file->name));
            $starts = [];

            foreach ($tokens->tokenRealLines as $index => $line) {
                if ($line === $file->startLine) {
                    $starts[] = $index;
                }
            }

            $copies[] = ['signature' => $tokens->signature, 'starts' => $starts];
        }

        if (count($copies) < 2) {
            return false;
        }

        $width = $clone->numberOfTokens() * FileTokens::TOKEN_BYTES;

        foreach ($copies[0]['starts'] as $start) {
            $candidate = substr($copies[0]['signature'], $start * FileTokens::TOKEN_BYTES, $width);

            if (strlen($candidate) !== $width) {
                continue;
            }

            $all = true;

            foreach (array_slice($copies, 1) as $copy) {
                $found = false;

                foreach ($copy['starts'] as $otherStart) {
                    if (substr($copy['signature'], $otherStart * FileTokens::TOKEN_BYTES, $width) === $candidate) {
                        $found = true;

                        break;
                    }
                }

                if (!$found) {
                    $all = false;

                    break;
                }
            }

            if ($all) {
                return true;
            }
        }

        return false;
    }
}
