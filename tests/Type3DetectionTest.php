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

require_once __DIR__ . '/_guard.php';

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTreeStrategy;

/**
 * E1 — the empirical proof of the paper's central claim.
 *
 * Fixtures are identical except for inserted statement(s) (Type-3 / gapped clones).
 * The Rabin-Karp engine hashes fixed exact windows, so an inserted statement splits
 * the identical run into halves below the token threshold and it finds nothing. The
 * suffix-tree (ConQAT) engine compares by edit distance, so a small edit budget
 * bridges the gap and the clone is found.
 *
 * Converts "phpcpd already does Type-3 detection" from a code-reading argument into a
 * reproducible result. All thresholds below are empirically pinned via CLI sweeps.
 *
 * Finding worth noting: the recall flip sits at the SAME edit distance (3) for both a
 * one-statement and a two-statement gap — because ConQAT's edit distance aligns shared
 * tokens, the cost to bridge a gap is not a linear function of statement count. The
 * `--edit-distance` budget is measured in token edits *after alignment*, not in statements.
 */
#[CoversClass(SuffixTreeStrategy::class)]
#[CoversClass(DefaultStrategy::class)]
#[CoversClass(Detector::class)]
final class Type3DetectionTest extends TestCase
{
    private const string BASE     = __DIR__ . '/fixtures/type3/clone_base.php';
    private const string GAPPED   = __DIR__ . '/fixtures/type3/clone_gapped.php';   // 1 inserted statement
    private const string WIDE_GAP = __DIR__ . '/fixtures/type3/clone_wide_gap.php'; // 2 inserted statements

    /**
     * Control: the two files genuinely share a large run. Below the gap size,
     * Rabin-Karp sees it — the engine is not simply blind to these files.
     */
    #[Test]
    public function rabin_karp_finds_the_shared_run_below_gap_size(): void
    {
        $clones = $this->detect(DefaultStrategy::class, self::GAPPED, minTokens: 40, editDistance: 0);

        self::assertFalse(
            $clones->isEmpty(),
            'Below the gap size, Rabin-Karp should see the shared run',
        );
    }

    /**
     * The gap defeats exact windows: raise the threshold past each half and
     * Rabin-Karp loses the clone entirely.
     */
    #[Test]
    public function rabin_karp_loses_the_clone_once_the_gap_splits_it(): void
    {
        $clones = $this->detect(DefaultStrategy::class, self::GAPPED, minTokens: 60, editDistance: 0);

        self::assertTrue(
            $clones->isEmpty(),
            'Rabin-Karp cannot bridge the inserted statement at this threshold',
        );
    }

    /**
     * The headline contrast: at the SAME threshold where Rabin-Karp fails, the
     * suffix tree's edit-distance budget bridges the gap and detects the clone.
     */
    #[Test]
    public function suffix_tree_bridges_the_gap_at_the_same_threshold(): void
    {
        $clones = $this->detect(SuffixTreeStrategy::class, self::GAPPED, minTokens: 60, editDistance: 3);

        self::assertFalse(
            $clones->isEmpty(),
            'The suffix tree should detect the gapped Type-3 clone Rabin-Karp missed',
        );
    }

    /**
     * Recall rises with the edit budget, with a sharp flip: edit distance 2 cannot
     * bridge the one-statement gap; edit distance 3 can.
     */
    #[Test]
    public function suffix_tree_recall_flips_at_the_edit_distance_threshold(): void
    {
        $belowThreshold = $this->detect(SuffixTreeStrategy::class, self::GAPPED, minTokens: 60, editDistance: 2);
        $atThreshold    = $this->detect(SuffixTreeStrategy::class, self::GAPPED, minTokens: 60, editDistance: 3);

        self::assertTrue(
            $belowThreshold->isEmpty(),
            'Edit distance 2 is below the budget needed to bridge the gap',
        );
        self::assertFalse(
            $atThreshold->isEmpty(),
            'Edit distance 3 reaches the budget needed to bridge the gap',
        );
    }

    /**
     * Generality across gap size: a two-statement gap is also missed by Rabin-Karp
     * and detected by the suffix tree (at the same edit distance, per the note above).
     */
    #[Test]
    public function suffix_tree_detects_a_wider_two_statement_gap(): void
    {
        $rabinKarp = $this->detect(DefaultStrategy::class, self::WIDE_GAP, minTokens: 60, editDistance: 0);
        $suffixTree = $this->detect(SuffixTreeStrategy::class, self::WIDE_GAP, minTokens: 60, editDistance: 3);

        self::assertTrue($rabinKarp->isEmpty(), 'Rabin-Karp misses the wider gap too');
        self::assertFalse($suffixTree->isEmpty(), 'The suffix tree bridges the wider gap');
    }

    /** @param class-string<AbstractStrategy> $strategyClass */
    private function detect(string $strategyClass, string $variant, int $minTokens, int $editDistance): CodeCloneMap
    {
        $config = new StrategyConfiguration(new Arguments(
            directories: [],
            suffixes: ['.php'],
            exclude: [],
            pmdCpdXmlLogfile: null,
            linesThreshold: 1,
            tokensThreshold: $minTokens,
            fuzzy: false,
            verbose: false,
            help: false,
            version: false,
            algorithm: null,
            editDistance: $editDistance,
            headEquality: 10,
        ));

        return (new Detector(new $strategyClass($config)))
            ->copyPasteDetection([self::BASE, $variant]);
    }
}
