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

use LucianoPereira\PhpcpdNext\Detector\CloneSuppressions;
use LucianoPereira\PhpcpdNext\Phpcpd;
use LucianoPereira\PhpcpdNext\Util\TokenCursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Declaring a duplication intentional. Before this there was no way to do it:
 * deliberately parallel code — a dispatch table with one arm per type, say — was
 * reported every run, and the only remedy was excluding the whole file, which
 * also hid the duplication worth fixing.
 *
 * Each fixture pair duplicates the same function body. The unmarked pair must
 * still be reported, which is what proves a passing marked case is suppression
 * rather than a detector that stopped finding anything.
 */
#[CoversClass(CloneSuppressions::class)]
#[CoversClass(TokenCursor::class)]
final class CloneSuppressionTest extends TestCase
{
    private const string UNMARKED = __DIR__ . '/fixtures/with_clones';

    /** @return iterable<string, array{string}> */
    public static function notations(): iterable
    {
        yield 'region markers'      => ['region'];
        yield 'declaration docblock' => ['declaration'];
        yield 'single line'         => ['line'];
    }

    #[Test]
    #[DataProvider('notations')]
    public function a_marked_duplication_is_not_reported(string $notation): void
    {
        $map = Phpcpd::detect(__DIR__ . '/fixtures/ignore/' . $notation, minTokens: 20, minLines: 2);

        self::assertSame(0, $map->count(), $notation . ' markers must suppress the clone');
    }

    #[Test]
    public function the_same_duplication_without_markers_is_still_reported(): void
    {
        $map = Phpcpd::detect(self::UNMARKED, minTokens: 20, minLines: 2);

        self::assertGreaterThan(0, $map->count());
    }

    #[Test]
    public function suppressing_a_clone_removes_it_from_the_duplicated_line_count(): void
    {
        $map = Phpcpd::detect(__DIR__ . '/fixtures/ignore/region', minTokens: 20, minLines: 2);

        self::assertSame(0, $map->numberOfDuplicatedLines());
        self::assertSame(0, $map->numberOfFilesWithClones());
    }

    #[Test]
    public function a_file_with_no_markers_costs_nothing(): void
    {
        self::assertTrue(CloneSuppressions::forFiles([self::UNMARKED . '/Alpha.php'])->isEmpty());
    }
}
