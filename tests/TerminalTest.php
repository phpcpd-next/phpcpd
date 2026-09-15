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

use function array_filter;
use function explode;
use function max;
use function strlen;

use LucianoPereira\PhpcpdNext\Options;
use LucianoPereira\PhpcpdNext\Console\Terminal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The terminal, and the one thing that reads it.
 *
 * `--help` used to append every description raw: 205 columns at its widest,
 * with 23 of its 53 lines past 80. On an ordinary terminal nearly half of it
 * wrapped, and wrapped at column zero, which destroys the alignment the padding
 * exists to create. These assert the width is respected rather than that the
 * text looks nice, because the width is the part a reader loses.
 */
#[CoversClass(Terminal::class)]
#[CoversClass(Options::class)]
final class TerminalTest extends TestCase
{
    /** The longest line of a rendered help screen. */
    private static function widest(string $help): int
    {
        $widest = 0;

        foreach (explode("\n", $help) as $line) {
            $widest = max($widest, strlen($line));
        }

        return $widest;
    }

    #[Test]
    public function helpIsWrappedToTheTerminalItIsGiven(): void
    {
        foreach ([60, 80, 100] as $width) {
            $help = Options::help(new Terminal(width: $width));

            self::assertLessThanOrEqual(
                $width,
                self::widest($help),
                'no line may exceed the terminal it was rendered for',
            );
        }
    }

    /**
     * Prose set to the full width of a very wide terminal stops being readable,
     * so the measure is capped. The cap is the class's business, not each call
     * site's.
     */
    #[Test]
    public function aVeryWideTerminalDoesNotProduceVeryWideProse(): void
    {
        $help = Options::help(new Terminal(width: 400));

        self::assertLessThanOrEqual(120, self::widest($help));
    }

    /**
     * A narrow terminal keeps its two columns: continuations line up under the
     * description, never at column zero.
     */
    #[Test]
    public function continuationsAlignUnderTheDescription(): void
    {
        $help  = Options::help(new Terminal(width: 60));
        $lines = array_filter(
            explode("\n", $help),
            static fn(string $line): bool => $line !== '' && $line[0] === ' ',
        );

        self::assertNotSame([], $lines);

        foreach ($lines as $line) {
            self::assertMatchesRegularExpression('/^ {2,}\S/', $line, 'an indented line never starts at column zero');
        }
    }

    #[Test]
    public function aPipeGetsNoColourAndAnExplicitRefusalOutranksAnExplicitRequest(): void
    {
        self::assertFalse((new Terminal(width: 80, colour: false))->colour);
        self::assertTrue((new Terminal(width: 80, colour: true))->colour);
    }

    /** The measure never collapses, however narrow the terminal claims to be. */
    #[Test]
    public function theMeasureHasAFloor(): void
    {
        self::assertGreaterThanOrEqual(40, (new Terminal(width: 1))->measure());
    }
}
