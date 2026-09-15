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

namespace LucianoPereira\PhpcpdNext\Tests\Regression;

use function count;
use function sort;
use function max;
use function min;

use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\UnifiedStrategy;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The candidate loop already refuses a self-overlapping candidate — "two ranges
 * that merely overlap describe one region matched against a shifted view of
 * itself". A class is assembled from many candidates, though, and site identity
 * is a question about 80% overlap (ruling G), so two ranges overlapping by less
 * than that stayed two sites of one class and a reader was told a region
 * duplicates itself.
 *
 * On php-parser they missed the bar by a hair — 98 tokens shared where 100 were
 * needed, 119 where 132 were, 92 where 107 were — giving 5 such clones on
 * php-parser, 22 on symfony-console and 13 on phpunit's Metadata.
 *
 * They are merged rather than dropped. Dropping was measured first and is
 * wrong: it cost MetadataTest's class 16 of its 88 sites and turned 226 of the
 * token bag's own pairs into misses.
 */
#[CoversClass(UnifiedStrategy::class)]
final class OverlappingSitesAreOneRegionTest extends TestCase
{
    #[Test]
    public function noCloneNamesTwoOverlappingRangesInOneFile(): void
    {
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 38,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        $files = (new FileFinder())->find([__DIR__ . '/../fixtures'], ['.php'], [], true);
        sort($files);

        $map = (new Engine($config, 'unified'))->detect($files);

        self::assertGreaterThan(0, $map->count(), 'the fixtures must produce findings for this to mean anything');

        foreach ($map->clones() as $clone) {
            $ranges = [];

            foreach ($clone->files() as $site) {
                if ($site->startToken === null) {
                    continue;
                }

                $ranges[] = [$site->name, $site->startToken, $site->startToken + $site->tokens($clone->numberOfTokens()) - 1];
            }

            $count = count($ranges);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    [$name, $first, $last]          = $ranges[$i];
                    [$otherName, $otherFirst, $otherLast] = $ranges[$j];

                    if ($name !== $otherName) {
                        continue;
                    }

                    self::assertLessThan(
                        max($first, $otherFirst),
                        min($last, $otherLast),
                        'two sites of one clone overlap inside ' . $name . ' — a region reported as a duplicate of itself',
                    );
                }
            }
        }
    }
}
