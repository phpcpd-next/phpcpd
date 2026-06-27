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

use const T_CONSTANT_ENCAPSED_STRING;
use const T_DNUMBER;
use const T_FOREACH;
use const T_LNUMBER;
use const T_RETURN;
use const T_STRING;
use const T_VARIABLE;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenNormalizer;

#[CoversClass(TokenNormalizer::class)]
final class TokenNormalizerTest extends TestCase
{
    #[Test]
    public function abstracts_variables_identifiers_and_literals_to_type_classes(): void
    {
        $normalizer = new TokenNormalizer();

        self::assertSame('$', $normalizer->normalize(T_VARIABLE, '$total'));
        self::assertSame('ID', $normalizer->normalize(T_STRING, 'summarize'));
        self::assertSame('NUM', $normalizer->normalize(T_LNUMBER, '42'));
        self::assertSame('NUM', $normalizer->normalize(T_DNUMBER, '3.14'));
        self::assertSame('STR', $normalizer->normalize(T_CONSTANT_ENCAPSED_STRING, "'hello'"));
    }

    #[Test]
    public function differently_named_identifiers_collapse_to_the_same_class(): void
    {
        $normalizer = new TokenNormalizer();

        // The whole point: renamed identifiers become indistinguishable (Type-2).
        self::assertSame(
            $normalizer->normalize(T_VARIABLE, '$total'),
            $normalizer->normalize(T_VARIABLE, '$sum'),
        );
        self::assertSame(
            $normalizer->normalize(T_STRING, 'summarize'),
            $normalizer->normalize(T_STRING, 'aggregate'),
        );
    }

    #[Test]
    public function keeps_structural_tokens_raw(): void
    {
        $normalizer = new TokenNormalizer();

        // Keywords and operators are the skeleton that defines a clone — left as-is.
        self::assertSame('foreach', $normalizer->normalize(T_FOREACH, 'foreach'));
        self::assertSame('return', $normalizer->normalize(T_RETURN, 'return'));
    }
}
