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


use LucianoPereira\PhpcpdNext\Console\Ink;
use LucianoPereira\PhpcpdNext\Console\Style;
use LucianoPereira\PhpcpdNext\Console\Terminal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ink::class)]
#[CoversClass(Style::class)]
final class InkTest extends TestCase
{
    #[Test]
    public function plainInkNeverPaints(): void
    {
        $ink = Ink::plain();

        foreach (Style::cases() as $style) {
            self::assertSame('text', $ink->paint($style, 'text'));
        }
    }

    #[Test]
    public function inkTakesItsAnswerFromTheTerminal(): void
    {
        self::assertSame('x', Ink::of(new Terminal(80, false))->paint(Style::Advice, 'x'));
        self::assertSame("\e[36mx\e[0m", Ink::of(new Terminal(80, true))->paint(Style::Advice, 'x'));
    }

    /**
     * Painting nothing is nothing. An empty cell wrapped in an escape and a
     * reset is two sequences a terminal has to process to display no character,
     * and it defeats any later comparison of the text with itself.
     */
    #[Test]
    public function anEmptyStringIsLeftAlone(): void
    {
        self::assertSame('', Ink::of(new Terminal(80, true))->paint(Style::Problem, ''));
    }

    /**
     * The two roles that mean "read this last" share dim deliberately; the three
     * that mean different things are distinguishable.
     */
    #[Test]
    public function theRolesThatMeanDifferentThingsLookDifferent(): void
    {
        self::assertSame(Style::Demoted->sequence(), Style::Sibling->sequence());

        $distinct = [
            Style::Demoted->sequence()    => true,
            Style::Divergence->sequence() => true,
            Style::Advice->sequence()     => true,
            Style::Problem->sequence()    => true,
        ];

        self::assertCount(4, $distinct);
    }

    /**
     * Only the eight basic colours and the two attributes every terminal since
     * the VT100 renders, so a user's own palette decides the actual shade.
     */
    #[Test]
    public function everyRoleIsABasicSgrCode(): void
    {
        foreach (Style::cases() as $style) {
            self::assertMatchesRegularExpression('/^(?:[12]|3[0-7])$/', $style->sequence());
        }
    }
}
