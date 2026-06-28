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

use LucianoPereira\PhpcpdNext\Phpcpd;
use LucianoPereira\PhpcpdNext\PHPUnit\AssertNoDuplication;
use LucianoPereira\PhpcpdNext\PHPUnit\DuplicationConstraint;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DuplicationConstraint::class)]
#[CoversClass(AssertNoDuplication::class)]
final class PHPUnitIntegrationTest extends TestCase
{
    use AssertNoDuplication;

    #[Test]
    public function constraint_passes_on_an_empty_map(): void
    {
        $map = Phpcpd::detect(__DIR__ . '/fixtures/no_clones', minTokens: 20, minLines: 2);

        self::assertThat($map, new DuplicationConstraint());
    }

    #[Test]
    public function constraint_describes_itself(): void
    {
        self::assertSame('contains no duplicated code', (new DuplicationConstraint())->toString());
    }

    #[Test]
    public function trait_assertion_passes_on_clean_sources(): void
    {
        $this->assertNoDuplication(__DIR__ . '/fixtures/no_clones', minTokens: 20, minLines: 2);
    }

    #[Test]
    public function trait_assertion_fails_and_lists_offenders_on_duplication(): void
    {
        try {
            $this->assertNoDuplication(__DIR__ . '/fixtures/with_clones', minTokens: 20, minLines: 2);
            self::fail('expected the duplication assertion to fail');
        } catch (AssertionFailedError $e) {
            self::assertStringContainsString('contains no duplicated code', $e->getMessage());
            self::assertStringContainsString('clone', $e->getMessage());
            self::assertStringContainsString('↔', $e->getMessage());
        }
    }
}
