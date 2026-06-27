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

#[CoversClass(Detector::class)]
#[CoversClass(DefaultStrategy::class)]
#[CoversClass(SuffixTreeStrategy::class)]
final class DetectorTest extends TestCase
{
    private const string FIXTURES_WITH_CLONES = __DIR__ . '/fixtures/with_clones';
    private const string FIXTURES_NO_CLONES   = __DIR__ . '/fixtures/no_clones';

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
    public function finds_clones_when_duplicate_blocks_exist(string $strategyClass): void
    {
        $clones = $this->detect(
            files: [
                self::FIXTURES_WITH_CLONES . '/Alpha.php',
                self::FIXTURES_WITH_CLONES . '/Beta.php',
            ],
            strategyClass: $strategyClass,
            minLines: 5,
            minTokens: 50,
        );

        self::assertFalse($clones->isEmpty());
        self::assertGreaterThan(0, $clones->count());
    }

    /**
     * @param class-string<AbstractStrategy> $strategyClass
     */
    #[Test]
    #[DataProvider('strategies')]
    public function reports_no_clones_for_unique_code(string $strategyClass): void
    {
        $clones = $this->detect(
            files: [self::FIXTURES_NO_CLONES . '/Unique.php'],
            strategyClass: $strategyClass,
            minLines: 5,
            minTokens: 50,
        );

        self::assertTrue($clones->isEmpty());
    }

    /**
     * DefaultStrategy filters by line count; a threshold above the fixture clone size suppresses it.
     */
    #[Test]
    public function rabin_karp_suppresses_clones_below_min_lines_threshold(): void
    {
        $clones = $this->detect(
            files: [
                self::FIXTURES_WITH_CLONES . '/Alpha.php',
                self::FIXTURES_WITH_CLONES . '/Beta.php',
            ],
            strategyClass: DefaultStrategy::class,
            minLines: 1000,
            minTokens: 50,
        );

        self::assertTrue($clones->isEmpty());
    }

    /**
     * SuffixTree filters by token count; a threshold above the fixture clone size suppresses it.
     */
    #[Test]
    public function suffixtree_suppresses_clones_below_min_tokens_threshold(): void
    {
        $clones = $this->detect(
            files: [
                self::FIXTURES_WITH_CLONES . '/Alpha.php',
                self::FIXTURES_WITH_CLONES . '/Beta.php',
            ],
            strategyClass: SuffixTreeStrategy::class,
            minLines: 5,
            minTokens: 10000,
        );

        self::assertTrue($clones->isEmpty());
    }

    /**
     * @param class-string<AbstractStrategy> $strategyClass
     */
    #[Test]
    #[DataProvider('strategies')]
    public function detected_clone_spans_correct_files(string $strategyClass): void
    {
        $clones = $this->detect(
            files: [
                self::FIXTURES_WITH_CLONES . '/Alpha.php',
                self::FIXTURES_WITH_CLONES . '/Beta.php',
            ],
            strategyClass: $strategyClass,
            minLines: 5,
            minTokens: 50,
        );

        self::assertFalse($clones->isEmpty());

        $fileNames = [];

        foreach ($clones->clones() as $clone) {
            foreach ($clone->files() as $file) {
                $fileNames[] = basename($file->name());
            }
        }

        self::assertContains('Alpha.php', $fileNames);
        self::assertContains('Beta.php', $fileNames);
    }

    /**
     * @param class-string<AbstractStrategy> $strategyClass
     */
    #[Test]
    #[DataProvider('strategies')]
    public function empty_file_list_produces_empty_map(string $strategyClass): void
    {
        $clones = $this->detect(
            files: [],
            strategyClass: $strategyClass,
        );

        self::assertTrue($clones->isEmpty());
        self::assertSame(0, $clones->count());
    }

    /**
     * @param list<string> $files
     * @param class-string<AbstractStrategy> $strategyClass
     */
    private function detect(
        array $files,
        string $strategyClass,
        int $minLines = 5,
        int $minTokens = 70,
        bool $fuzzy = false,
    ): CodeCloneMap {
        $config = new StrategyConfiguration(new Arguments(
            directories:      [],
            suffixes:         ['.php'],
            exclude:          [],
            pmdCpdXmlLogfile: null,
            linesThreshold:   $minLines,
            tokensThreshold:  $minTokens,
            fuzzy:            $fuzzy,
            verbose:          false,
            help:             false,
            version:          false,
            algorithm:        null,
            editDistance:     5,
            headEquality:     10,
        ));

        return (new Detector(new $strategyClass($config)))->copyPasteDetection($files);
    }
}
