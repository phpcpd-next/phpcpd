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
use PHPUnit\Framework\Attributes\DataProvider;
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
 * R3 — token-type abstraction (Type-2 detection).
 *
 * The two fixtures are structurally identical but every identifier, literal, and
 * the function name differ. Both engines match on raw token content, so they see
 * NO clone. With --fuzzy, the shared TokenNormalizer collapses identifiers and
 * literals to type classes, the streams become identical, and both engines detect
 * the Type-2 clone. Before R3, --fuzzy abstracted only T_VARIABLE (and only in the
 * Rabin-Karp engine); the suffix tree had no normalization at all.
 *
 * Thresholds empirically pinned (min-tokens 30, edit-distance 0 so the suffix-tree
 * match is exact on the normalized stream).
 */
#[CoversClass(DefaultStrategy::class)]
#[CoversClass(SuffixTreeStrategy::class)]
#[CoversClass(Detector::class)]
final class Type2DetectionTest extends TestCase
{
    private const string BASE    = __DIR__ . '/fixtures/type2/rename_base.php';
    private const string VARIANT = __DIR__ . '/fixtures/type2/rename_variant.php';

    /** @return array<string, array{0: class-string<AbstractStrategy>}> */
    public static function strategies(): array
    {
        return [
            'rabin-karp' => [DefaultStrategy::class],
            'suffixtree' => [SuffixTreeStrategy::class],
        ];
    }

    /**
     * @param class-string<AbstractStrategy> $strategyClass
     */
    #[Test]
    #[DataProvider('strategies')]
    public function type2_clone_is_invisible_without_normalization(string $strategyClass): void
    {
        $clones = $this->detect($strategyClass, fuzzy: false);

        self::assertTrue(
            $clones->isEmpty(),
            'Raw matching must not see a clone when every identifier is renamed',
        );
    }

    /**
     * @param class-string<AbstractStrategy> $strategyClass
     */
    #[Test]
    #[DataProvider('strategies')]
    public function type2_clone_is_detected_with_fuzzy_normalization(string $strategyClass): void
    {
        $clones = $this->detect($strategyClass, fuzzy: true);

        self::assertFalse(
            $clones->isEmpty(),
            'Token-type normalization must surface the renamed-identifier (Type-2) clone',
        );
    }

    /** @param class-string<AbstractStrategy> $strategyClass */
    private function detect(string $strategyClass, bool $fuzzy): CodeCloneMap
    {
        $config = new StrategyConfiguration(new Arguments(
            directories: [],
            suffixes: ['.php'],
            exclude: [],
            pmdCpdXmlLogfile: null,
            linesThreshold: 1,
            tokensThreshold: 30,
            fuzzy: $fuzzy,
            verbose: false,
            help: false,
            version: false,
            algorithm: null,
            editDistance: 0,
            headEquality: 10,
        ));

        return (new Detector(new $strategyClass($config)))
            ->copyPasteDetection([self::BASE, self::VARIANT]);
    }
}
