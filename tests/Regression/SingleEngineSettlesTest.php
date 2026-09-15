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

use function glob;
use function sort;
use function count;
use function min;
use function max;

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `dropClonesSeenTwice()` was reachable only through `mergeFrom()`, so the
 * merged default pipeline settled and a single engine never did — and
 * `--algorithm=unified` never goes through the merge. The report then showed the
 * same duplication twice by the project's own definition: two findings over the
 * same files, naming the same number of sites, every site meeting the other's.
 *
 * Nine of php-parser's 82 findings and 37 of symfony-console's 227 were that,
 * and the coverage totals do not move when they go, because a contained finding
 * never un-covers a line the container still holds.
 */
#[CoversClass(CodeCloneMap::class)]
final class SingleEngineSettlesTest extends TestCase
{
    #[Test]
    public function noTwoFindingsDescribeTheSameRegion(): void
    {
        $map = $this->detect();

        self::assertGreaterThan(0, $map->count(), 'the fixture must produce findings for this to mean anything');

        $spans = [];

        foreach ($map->clones() as $clone) {
            $sites = [];

            foreach ($clone->files() as $file) {
                $sites[] = [$file->name, $file->startLine, $file->lastLine($clone->numberOfLines())];
            }

            $spans[] = $sites;
        }

        foreach ($spans as $i => $one) {
            foreach ($spans as $j => $other) {
                if ($i === $j || count($one) !== count($other)) {
                    continue;
                }

                $meets = true;

                foreach ($one as $at => [$name, $first, $last]) {
                    [$otherName, $otherFirst, $otherLast] = $other[$at];

                    if ($name !== $otherName || min($last, $otherLast) < max($first, $otherFirst)) {
                        $meets = false;

                        break;
                    }
                }

                self::assertFalse($meets, 'two findings describe one region — the map was not settled');
            }
        }
    }

    private function detect(): CodeCloneMap
    {
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 38,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        // A single engine, so the merge path — the only thing that used to
        // settle a map — is not involved.
        $files = [];

        foreach ((array) glob(__DIR__ . '/../fixtures/probes/*.php') as $path) {
            $files[] = (string) $path;
        }

        sort($files);

        return (new Engine($config, 'unified'))->detect($files);
    }
}
