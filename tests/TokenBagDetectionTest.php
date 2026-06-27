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

use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTreeStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBagStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * R1 — SourcererCC token-bag, and the complementarity that justifies it.
 *
 * `reorder_swapped.php` is `reorder_base.php` with two statements swapped: the same
 * multiset of tokens in a different order. With min-tokens above the longest
 * contiguous identical run (so neither half alone qualifies), only an order-invariant
 * engine can see the clone. The token-bag engine does; the suffix tree (a swap
 * exceeds the edit budget) and Rabin-Karp (exact windows broken) do not.
 *
 * Thresholds empirically pinned (block body = 35 tokens, longest contiguous run ≈ 20,
 * so min-tokens 25 forces order-invariance).
 */
#[CoversClass(TokenBagStrategy::class)]
#[CoversClass(Detector::class)]
final class TokenBagDetectionTest extends TestCase
{
    private const string BASE    = __DIR__ . '/fixtures/r1/reorder_base.php';
    private const string SWAPPED = __DIR__ . '/fixtures/r1/reorder_swapped.php';
    private const string UNIQUE  = __DIR__ . '/fixtures/no_clones/Unique.php';

    #[Test]
    public function token_bag_detects_a_reordered_clone(): void
    {
        $clones = $this->detect(TokenBagStrategy::class, [self::BASE, self::SWAPPED], similarity: 0.9);

        self::assertFalse(
            $clones->isEmpty(),
            'the order-invariant bag should match a function whose statements were reordered',
        );
    }

    #[Test]
    public function the_suffix_tree_misses_the_reordered_clone(): void
    {
        $clones = $this->detect(SuffixTreeStrategy::class, [self::BASE, self::SWAPPED], editDistance: 5);

        self::assertTrue(
            $clones->isEmpty(),
            'a statement swap exceeds the suffix-tree edit budget',
        );
    }

    #[Test]
    public function rabin_karp_misses_the_reordered_clone(): void
    {
        $clones = $this->detect(DefaultStrategy::class, [self::BASE, self::SWAPPED]);

        self::assertTrue(
            $clones->isEmpty(),
            'reordering breaks the exact contiguous windows',
        );
    }

    #[Test]
    public function two_unrelated_functions_are_not_flagged(): void
    {
        $clones = $this->detect(TokenBagStrategy::class, [self::BASE, self::UNIQUE], similarity: 0.7);

        self::assertTrue($clones->isEmpty(), 'low token overlap must not be reported as a clone');
    }

    /**
     * @param class-string<AbstractStrategy> $strategyClass
     * @param list<string> $files
     */
    private function detect(string $strategyClass, array $files, float $similarity = 0.7, int $editDistance = 5): CodeCloneMap
    {
        $config = new StrategyConfiguration(new Arguments(
            directories: [],
            suffixes: ['.php'],
            exclude: [],
            pmdCpdXmlLogfile: null,
            linesThreshold: 1,
            tokensThreshold: 25,
            fuzzy: false,
            verbose: false,
            help: false,
            version: false,
            algorithm: null,
            editDistance: $editDistance,
            headEquality: 10,
            similarity: $similarity,
        ));

        return (new Detector(new $strategyClass($config)))->copyPasteDetection($files);
    }
}
