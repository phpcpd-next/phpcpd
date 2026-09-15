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

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\Phpcpd;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The capability probe, and the two properties that make it usable.
 *
 * An embedder binds against more than one version of this package and cannot
 * ask which one it got without either version arithmetic or `method_exists()`
 * over every member. `supports()` is the one question it can ask instead, so
 * what is pinned here is less the current vocabulary than the two guarantees a
 * consumer writes code against: an unknown string is answerable, and a string
 * is what it takes.
 */
#[CoversClass(Phpcpd::class)]
final class SupportsTest extends TestCase
{
    /** @return list<array{0: string}> */
    public static function recognised(): array
    {
        return [['default-excludes'], ['divergences'], ['line-spans']];
    }

    #[Test]
    #[DataProvider('recognised')]
    public function it_recognises_what_1_5_0_actually_has(string $capability): void
    {
        self::assertTrue(Phpcpd::supports($capability));
    }

    #[Test]
    public function an_unknown_capability_is_answered_rather_than_refused(): void
    {
        // A consumer written against a later vocabulary must degrade here, not
        // fatal. This is the whole reason the probe exists.
        self::assertFalse(Phpcpd::supports('a-capability-from-some-later-release'));
        self::assertFalse(Phpcpd::supports(''));
    }

    #[Test]
    public function it_takes_a_string_so_an_unknown_name_can_be_spoken_at_all(): void
    {
        // Not decoration: an enum parameter could not name a case this version
        // has never heard of, which defeats the forward safety above. If this
        // ever becomes an enum, the probe stops working for the consumers it
        // was built for.
        $type = (new ReflectionMethod(Phpcpd::class, 'supports'))->getParameters()[0]->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame('string', $type->getName());
    }

    #[Test]
    public function classification_stays_unrecognised_while_a_clone_cannot_be_asked_its_type(): void
    {
        // A capability string that predates its surface is worse than none: a
        // consumer lights up output against it and gets nothing back. When a
        // clone can be asked its type, this flips -- and this test is where the
        // two are kept in step.
        self::assertFalse(
            method_exists(CodeClone::class, 'type'),
            'CodeClone::type() exists now, so `classification` should be recognised',
        );
        self::assertFalse(Phpcpd::supports('classification'));
    }

    #[Test]
    public function each_recognised_capability_names_something_callable(): void
    {
        self::assertTrue(
            (new ReflectionMethod(Phpcpd::class, 'detect'))->getParameters()[9]->getName() === 'defaultExcludes',
            '`default-excludes` must name a parameter that exists',
        );
        self::assertTrue(method_exists(CodeClone::class, 'divergences'), '`divergences` must name a method that exists');
        self::assertTrue(
            property_exists(CodeCloneFile::class, 'numberOfLines') && method_exists(CodeCloneFile::class, 'lastLine'),
            '`line-spans` must name a property and method that exist',
        );
    }
}
