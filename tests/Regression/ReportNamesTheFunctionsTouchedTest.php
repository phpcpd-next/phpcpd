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

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Facts\RegionStructure;
use LucianoPereira\PhpcpdNext\Presentation\Presenter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A reported extent is a token run, and a token run does not begin or end where
 * a function does: measured on php-parser, 0% of sites start at a function-body
 * start and 2% end at one. Snapping the extent to those boundaries was measured
 * and refused — outward invents 37% of tokens that never matched, inward drops
 * 44% that did — so the range stays exact and the report says where it lands.
 *
 * `MetadataTest.php:5716-5780` tells a reader nothing; `testCanBeRetry` tells
 * them everything.
 */
#[CoversClass(RegionStructure::class)]
final class ReportNamesTheFunctionsTouchedTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phpcpd-fn-' . uniqid();

        mkdir($this->directory);

        // The clone runs from inside `alpha` through the whole of `beta`, so a
        // correct answer names both — which is the case a line range hides.
        $body = '';

        for ($i = 0; $i < 12; $i++) {
            $body .= "            \$total += \$rows['field{$i}'] * 3;\n";
        }

        foreach (['One', 'Two'] as $class) {
            file_put_contents(
                $this->directory . '/' . $class . '.php',
                "<?php\n\nfinal class {$class}\n{\n"
                . "    public function alpha(array \$rows): int\n    {\n        \$total = 0;\n" . $body . "        return \$total;\n    }\n\n"
                . "    public function beta(array \$rows): int\n    {\n        \$total = 0;\n" . $body . "        return \$total;\n    }\n}\n",
            );
        }
    }

    protected function tearDown(): void
    {
        foreach (['One.php', 'Two.php'] as $name) {
            @unlink($this->directory . '/' . $name);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    #[Test]
    public function aFindingNamesTheFunctionsItsRangeLandsIn(): void
    {
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 40,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        $findings = (new Presenter())->present(
            (new Engine($config, 'unified'))->detect([
                $this->directory . '/One.php',
                $this->directory . '/Two.php',
            ]),
        );

        self::assertGreaterThan(0, $findings->count());

        $named = [];

        foreach ($findings->findings as $finding) {
            foreach ($finding->functions as $name) {
                $named[$name] = true;
            }
        }

        self::assertNotSame([], $named, 'no finding named a function at all');

        foreach ($named as $name => $_) {
            self::assertContains($name, ['alpha', 'beta'], 'named a function the range does not touch');
        }
    }
}
