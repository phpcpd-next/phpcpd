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
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBagStrategy;

/**
 * Verifies the two default modes:
 *
 *   Combined (rk+tb): both exact and reordered clones are reported.
 *   RK-only  (--rk):  only exact clones; reordered clones are missed.
 *
 * The r1 fixture is the canonical evidence: reorder_base.php and
 * reorder_swapped.php have the same tokens in different order — rabin-karp
 * misses it, tokenbag catches it.
 */
#[CoversClass(CodeCloneMap::class)]
final class CombinedModeTest extends TestCase
{
    private const string BASE    = __DIR__ . '/fixtures/r1/reorder_base.php';
    private const string SWAPPED = __DIR__ . '/fixtures/r1/reorder_swapped.php';

    private function makeArgs(?string $algorithm): Arguments
    {
        return new Arguments(
            directories:      [],
            suffixes:         ['.php'],
            exclude:          [],
            pmdCpdXmlLogfile: null,
            linesThreshold:   1,
            tokensThreshold:  25,
            fuzzy:            false,
            verbose:          false,
            help:             false,
            version:          false,
            algorithm:        $algorithm,
            editDistance:     5,
            headEquality:     10,
            similarity:       0.7,
        );
    }

    #[Test]
    public function combined_mode_finds_reordered_clone_that_rk_misses(): void
    {
        $files = [self::BASE, self::SWAPPED];

        // RK-only: misses the reordered clone.
        $rkArgs   = $this->makeArgs('rabin-karp');
        $rkConfig = new StrategyConfiguration($rkArgs);
        $rkMap    = (new Detector(new DefaultStrategy($rkConfig)))->copyPasteDetection($files);

        self::assertSame(0, $rkMap->count(), 'rabin-karp alone must miss a reordered clone');

        // Combined: tb finds it.
        $tbArgs   = $this->makeArgs(null);
        $tbConfig = new StrategyConfiguration($tbArgs);
        $combined = (new Detector(new DefaultStrategy($tbConfig)))->copyPasteDetection($files);
        $tbMap    = (new Detector(new TokenBagStrategy($tbConfig)))->copyPasteDetection($files);
        $combined->mergeFrom($tbMap);

        self::assertGreaterThanOrEqual(1, $combined->count(), 'combined mode must detect the reordered clone');
    }

    #[Test]
    public function rk_only_flag_restricts_to_exact_clones(): void
    {
        $files  = [self::BASE, self::SWAPPED];
        $args   = $this->makeArgs('rabin-karp');
        $config = new StrategyConfiguration($args);
        $map    = (new Detector(new DefaultStrategy($config)))->copyPasteDetection($files);

        self::assertSame(0, $map->count(), '--rk (rabin-karp) must not report a reordered clone');
    }
}
