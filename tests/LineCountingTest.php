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

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBagStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * How big a file is, which is the denominator of everything the report says
 * about how much of a project is duplicated.
 */
#[CoversClass(DefaultStrategy::class)]
#[CoversClass(TokenBagStrategy::class)]
final class LineCountingTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: int}> */
    public static function sources(): iterable
    {
        yield 'ends with a newline'      => ["<?php\n\$a = 1;\n\$b = 2;\n", 3];
        yield 'ends without a newline'   => ["<?php\n\$a = 1;\n\$b = 2;",   3];
        yield 'one line, no newline'     => ['<?php $a = 1;',               1];
        yield 'a single newline'         => ["\n",                          1];
    }

    /**
     * Counting newlines is one short whenever the last line has none. The file
     * that ends `return $x;` with no final break has all of its lines, and the
     * scan credited it with one fewer — which shrinks the total the duplicated
     * share is measured against, so the percentage came out too high.
     */
    #[Test]
    #[DataProvider('sources')]
    public function aFileIsCountedAtItsFullLength(string $source, int $lines): void
    {
        foreach ([DefaultStrategy::class, TokenBagStrategy::class] as $strategy) {
            $path = (string) tempnam(sys_get_temp_dir(), 'phpcpd-lines-');

            try {
                file_put_contents($path, $source);

                $map = new CodeCloneMap();
                (new $strategy(new StrategyConfiguration(
                    minLines: 5,
                    minTokens: 70,
                    normalization: Normalization::Raw,
                    minSimilarity: 0.7,
                )))->processFile($path, $map);

                self::assertSame($lines, $map->numberOfLines(), $strategy);
            } finally {
                unlink($path);
            }
        }
    }
}
