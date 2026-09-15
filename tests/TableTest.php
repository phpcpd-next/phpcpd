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

use function count;
use function explode;
use function mb_strlen;
use function mb_substr;
use function rtrim;
use function str_repeat;

use const PHP_EOL;

use LucianoPereira\PhpcpdNext\Console\Table;
use LucianoPereira\PhpcpdNext\Console\Terminal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Table::class)]
final class TableTest extends TestCase
{
    /** @return list<string> */
    private function lines(string $table): array
    {
        return explode(PHP_EOL, rtrim($table, PHP_EOL));
    }

    #[Test]
    public function aTableThatFitsUsesItsNaturalWidths(): void
    {
        $table = new Table(
            ['SETTING', 'VALUE', 'SOURCE'],
            [['min-lines', '5', 'default'], ['verbose', 'false', 'command line']],
        );

        self::assertSame(
            [
                '  SETTING    VALUE  SOURCE',
                '  min-lines  5      default',
                '  verbose    false  command line',
            ],
            $this->lines($table->render(new Terminal(80))),
        );
    }

    /**
     * Trailing whitespace is invisible until someone diffs it, so the last
     * column is never padded.
     */
    #[Test]
    public function noLineCarriesTrailingWhitespace(): void
    {
        $table = new Table(
            ['A', 'B'],
            [['short', 'x'], ['a much longer cell', 'y']],
        );

        foreach ($this->lines($table->render(new Terminal(80))) as $line) {
            self::assertSame(rtrim($line), $line);
        }
    }

    /**
     * The defect this replaced: one wide cell used to set the width of every
     * row, pushing the last column off the screen.
     */
    #[Test]
    public function aWideCellIsWrappedRatherThanOverflowing(): void
    {
        $table = new Table(
            ['NAME', 'VALUE', 'SOURCE'],
            [
                ['exclude', str_repeat('directory ', 12), 'command line'],
                ['verbose', 'false', 'default'],
            ],
        );

        $lines = $this->lines($table->render(new Terminal(60)));

        foreach ($lines as $line) {
            self::assertLessThanOrEqual(60, mb_strlen($line));
        }

        self::assertGreaterThan(3, count($lines), 'the wide cell should have wrapped');
    }

    /**
     * A wrapped cell's continuation sits under itself: the columns to its left
     * go blank rather than repeating, so the row still reads across.
     */
    #[Test]
    public function continuationLinesLeaveTheOtherColumnsBlank(): void
    {
        $table = new Table(
            ['NAME', 'VALUE'],
            [['exclude', 'alpha beta gamma delta epsilon zeta eta theta iota']],
        );

        $lines = $this->lines($table->render(new Terminal(40)));

        self::assertStringStartsWith('  exclude', $lines[1]);
        self::assertStringStartsWith('          ', $lines[2]);
    }

    /**
     * Shrinking stops where a column stops being a column. Five columns cannot
     * fit forty screen columns at eight characters each, and the answer is to
     * overhang rather than to keep cutting: a table that fits by becoming a
     * stack of two-letter fragments is worse than one that runs off the edge.
     */
    #[Test]
    public function anImpossiblyNarrowTerminalGetsAnOverhangNotAStack(): void
    {
        $table = new Table(
            ['ONE', 'TWO', 'THREE', 'FOUR', 'FIVE'],
            [['alpha-one', 'beta-two', 'gamma-three', 'delta-four', 'epsilon']],
        );

        $lines = $this->lines($table->render(new Terminal(10)));

        // Every column is still at least eight wide, so the fifth one starts
        // past column forty — beyond the room a terminal this narrow has, and
        // deliberately so.
        self::assertSame('FIVE', mb_substr($lines[0], 42));
        self::assertGreaterThan(40, mb_strlen($lines[0]));
    }

    #[Test]
    public function aTableWithNoRowsPrintsNothing(): void
    {
        self::assertSame('', (new Table(['A', 'B'], []))->render(new Terminal(80)));
    }
}
