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

use const T_IF;
use const T_STRING;
use const T_VARIABLE;
use const T_WHILE;

use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree\SubstitutionCost;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubstitutionCost::class)]
final class SubstitutionCostTest extends TestCase
{
    #[Test]
    public function identical_tokens_cost_nothing(): void
    {
        // Token::equals compares content, so same content = no substitution.
        self::assertSame(0, $this->cost()->between(
            $this->token(T_VARIABLE, '$a'),
            $this->token(T_VARIABLE, '$a'),
        ));
    }

    #[Test]
    public function a_renamed_identifier_is_a_cheap_cosmetic_change(): void
    {
        self::assertSame(1, $this->cost()->between(
            $this->token(T_VARIABLE, '$total'),
            $this->token(T_VARIABLE, '$sum'),
        ));
        self::assertSame(1, $this->cost()->between(
            $this->token(T_STRING, 'summarize'),
            $this->token(T_STRING, 'aggregate'),
        ));
    }

    #[Test]
    public function a_changed_control_keyword_costs_more(): void
    {
        // if -> while alters program structure: it costs more than a rename.
        self::assertSame(2, $this->cost()->between(
            $this->token(T_IF, 'if'),
            $this->token(T_WHILE, 'while'),
        ));
    }

    #[Test]
    public function replacing_a_keyword_with_an_identifier_is_structural(): void
    {
        self::assertSame(2, $this->cost()->between(
            $this->token(T_IF, 'if'),
            $this->token(T_VARIABLE, '$x'),
        ));
    }

    private function cost(): SubstitutionCost
    {
        return new SubstitutionCost();
    }

    private function token(int $code, string $content): Token
    {
        return new Token($code, '', 1, 'fixture.php', $content);
    }
}
