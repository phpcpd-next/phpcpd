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

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Util\CodeLines;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A clone is measured in tokens and reported as a line range, and the range
 * runs from the first matched token to the last — so every docblock and blank
 * line between them sits inside it without ever having been compared.
 *
 * Counting those said a file shared material it did not. On php-parser's
 * `Builder/Method.php:20-80` it said it of 40 lines in 61, against a copy whose
 * text reads "Makes the **property** public" where this one reads "method", and
 * which carries four `@var` lines this one has not got.
 *
 * The two files below are that shape at a size a reader can check: identical
 * code, comments that differ, and a comment block long enough that counting it
 * would dominate the total.
 */
#[CoversClass(CodeCloneMap::class)]
#[CoversClass(CodeLines::class)]
final class DuplicatedLinesCountCodeOnlyTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phpcpd-codelines-' . uniqid();

        mkdir($this->directory);

        file_put_contents($this->directory . '/One.php', self::source('One', 'method'));
        file_put_contents($this->directory . '/Two.php', self::source('Two', 'property'));
    }

    protected function tearDown(): void
    {
        foreach (['One.php', 'Two.php'] as $name) {
            $path = $this->directory . '/' . $name;

            if (is_dir($this->directory)) {
                @unlink($path);
            }
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    #[Test]
    public function commentsInsideACloneAreNotCountedAsDuplicated(): void
    {
        $map = $this->detect();

        self::assertGreaterThan(0, $map->count(), 'the fixture has to hold a clone for the count to mean anything');

        $site = null;

        foreach ($map->clones() as $clone) {
            foreach ($clone->files() as $file) {
                $site ??= $file->lastLine($clone->numberOfLines()) - $file->startLine + 1;
            }
        }

        self::assertNotNull($site);

        // Every copy but the first is charged, so one occurrence is counted
        // here — and it spans more lines than it is charged for, because the
        // comment block inside it holds no token either engine compared.
        self::assertLessThan(
            $site,
            $map->numberOfDuplicatedLines(),
            'the comment block inside the clone was counted as duplicated',
        );
    }

    #[Test]
    public function theCountIsExactlyTheLinesHoldingATokenTheMatcherSaw(): void
    {
        $map   = $this->detect();
        $lines = new CodeLines();

        $expected = 0;

        foreach ($map->clones() as $clone) {
            $first = true;

            foreach ($clone->files() as $file) {
                if ($first) {
                    $first = false;

                    continue;
                }

                $last = $file->lastLine($clone->numberOfLines());

                for ($line = $file->startLine; $line <= $last; $line++) {
                    $expected += $lines->isCode($file->name, $line) ? 1 : 0;
                }
            }
        }

        self::assertSame($expected, $map->numberOfDuplicatedLines());
    }

    private function detect(): CodeCloneMap
    {
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 40,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        return (new Engine($config, 'rabin-karp'))->detect([
            $this->directory . '/One.php',
            $this->directory . '/Two.php',
        ]);
    }

    /** Identical code, comments that differ, one long block comment inside. */
    private static function source(string $class, string $noun): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            final class {$class}
            {
                /**
                 * Makes the {$noun} public.
                 *
                 * A block long enough that counting it would dominate the total,
                 * which is the whole point of the fixture: it is not shared text,
                 * it merely sits between two runs of tokens that are.
                 *
                 * @return \$this
                 */
                public function totals(array \$rows): array
                {
                    \$sum = 0;
                    \$count = 0;

                    foreach (\$rows as \$row) {
                        \$sum += \$row['amount'];
                        \$count++;
                    }

                    if (\$count === 0) {
                        return ['sum' => 0, 'average' => 0];
                    }

                    return ['sum' => \$sum, 'average' => \$sum / \$count];
                }
            }
            PHP;
    }
}
