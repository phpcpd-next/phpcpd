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
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTreeStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBagStrategy;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The detector must report its own src/ as clone-free. A tool that ships
 * duplication it would flag in someone else's code has no standing — so this is
 * the dogfood invariant, enforced across all three engines.
 *
 * The bar is min-tokens 40 (tighter than the default 70). It already caught real
 * duplication once: the Json and Sarif loggers shared a boilerplate block, factored
 * out into AbstractJsonLogger. If this test goes red, src/ grew a clone worth
 * extracting — fix the code, don't relax the threshold.
 */
final class SelfDryTest extends TestCase
{
    private const int MIN_TOKENS = 40;
    private const int MIN_LINES  = 5;

    /** @return iterable<string, array{string}> */
    public static function engines(): iterable
    {
        yield 'rabin-karp' => ['rabin-karp'];
        yield 'suffixtree' => ['suffixtree'];
        yield 'tokenbag'   => ['tokenbag'];
    }

    #[Test]
    #[DataProvider('engines')]
    public function src_is_clone_free(string $algorithm): void
    {
        $files = (new FileFinder())->find([__DIR__ . '/../src'], ['.php'], []);

        self::assertNotEmpty($files, 'sanity: src/ should contain files to scan');

        $map = (new Detector($this->strategy($algorithm)))->copyPasteDetection($files);

        $offenders = [];

        foreach ($map->clones() as $clone) {
            $where = [];

            foreach ($clone->files() as $file) {
                $where[] = $file->name() . ':' . $file->startLine();
            }

            $offenders[] = $clone->numberOfLines() . ' lines @ ' . implode(' ↔ ', $where);
        }

        self::assertSame(
            0,
            $map->count(),
            "src/ is not DRY under $algorithm (min-tokens " . self::MIN_TOKENS . "):\n  " . implode("\n  ", $offenders),
        );
    }

    private function strategy(string $algorithm): AbstractStrategy
    {
        $config = new StrategyConfiguration(new Arguments(
            directories:      [],
            suffixes:         ['.php'],
            exclude:          [],
            pmdCpdXmlLogfile: null,
            linesThreshold:   self::MIN_LINES,
            tokensThreshold:  self::MIN_TOKENS,
            fuzzy:            false,
            verbose:          false,
            help:             false,
            version:          false,
            algorithm:        $algorithm,
            editDistance:     5,
            headEquality:     10,
        ));

        return match ($algorithm) {
            'suffixtree' => new SuffixTreeStrategy($config),
            'tokenbag'   => new TokenBagStrategy($config),
            default      => new DefaultStrategy($config),
        };
    }
}
