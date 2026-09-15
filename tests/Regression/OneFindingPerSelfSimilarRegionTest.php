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
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A region of period D matches itself at D, at 2D, at 3D — so a file built from
 * a run of near-identical blocks yields one finding per multiple of the period,
 * each a coarser gluing of the same blocks. On phpunit's `MetadataTest.php`,
 * 83 near-identical test methods produced 33 findings naming 240 sites, at
 * granularities from 67 lines to 1,114, all describing one fact.
 *
 * A run of similar blocks is one finding. The region is pinned by the finest
 * reading's own extremes — where its first block starts to where its last block
 * ends — and a coarser reading living inside those bounds is that same fact
 * with the blocks glued together.
 */
#[CoversClass(CodeCloneMap::class)]
final class OneFindingPerSelfSimilarRegionTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phpcpd-periodic-' . uniqid();

        mkdir($this->directory);

        $body = '';

        // Twelve near-identical blocks: the shape that matches itself at every
        // multiple of its period.
        for ($i = 0; $i < 12; $i++) {
            $body .= "    public function check{$i}(array \$rows): int\n    {\n";
            $body .= "        \$total = 0;\n\n";

            for ($j = 0; $j < 8; $j++) {
                $body .= "        \$total += \$rows['field{$j}'] * 2;\n";
            }

            $body .= "        return \$total;\n    }\n\n";
        }

        file_put_contents($this->directory . '/Periodic.php', "<?php\n\nfinal class Periodic\n{\n" . $body . "}\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->directory . '/Periodic.php');

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    #[Test]
    public function aRunOfSimilarBlocksIsNotReportedOncePerPeriod(): void
    {
        $map = $this->detect();

        self::assertGreaterThan(0, $map->count(), 'the fixture has to be seen as duplication at all');

        // The blocks are one fact. Before the region collapse this fixture
        // reported the run again at every multiple of its period.
        self::assertLessThanOrEqual(
            3,
            $map->count(),
            'the same run of blocks is being reported once per period multiple',
        );
    }

    #[Test]
    public function theFinestReadingSurvives(): void
    {
        $most = 0;

        foreach ($this->detect()->clones() as $clone) {
            $most = max($most, count($clone->files()));
        }

        // The reading that names the most blocks is the one a reader wants; it
        // is the coarser gluings that go.
        self::assertGreaterThanOrEqual(4, $most);
    }

    private function detect(): CodeCloneMap
    {
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 38,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        return (new Engine($config, 'unified'))->detect([$this->directory . '/Periodic.php']);
    }
}
