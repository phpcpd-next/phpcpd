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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;

#[CoversClass(CodeCloneMap::class)]
final class CodeCloneMapTest extends TestCase
{
    // Real files so CodeClone::id() (which hashes file content) produces distinct values.
    private const string FILE_A = __DIR__ . '/fixtures/with_clones/Alpha.php';
    private const string FILE_B = __DIR__ . '/fixtures/with_clones/Beta.php';
    private const string FILE_C = __DIR__ . '/fixtures/no_clones/Unique.php';

    #[Test]
    public function fresh_map_is_empty(): void
    {
        $map = new CodeCloneMap();

        self::assertTrue($map->isEmpty());
        self::assertSame(0, $map->count());
        self::assertSame(0, $map->numberOfDuplicatedLines());
        self::assertSame(0, $map->largestSize());
    }

    #[Test]
    public function average_size_on_empty_map_returns_zero_not_division_error(): void
    {
        // Regression for §14 — upstream divided by count() which was 0 when no clones found.
        self::assertSame(0.0, (new CodeCloneMap())->averageSize());
    }

    #[Test]
    public function count_reflects_distinct_clones_added(): void
    {
        $map = new CodeCloneMap();
        $map->add($this->makeClone(self::FILE_A, 1, self::FILE_B, 1, lines: 5));
        $map->add($this->makeClone(self::FILE_A, 10, self::FILE_C, 1, lines: 5));

        self::assertSame(2, $map->count());
        self::assertFalse($map->isEmpty());
    }

    #[Test]
    public function duplicate_clone_id_merges_rather_than_double_counts(): void
    {
        $map   = new CodeCloneMap();
        $clone = $this->makeClone(self::FILE_A, 1, self::FILE_B, 1, lines: 5);

        $map->add($clone);
        $map->add($clone);

        self::assertSame(1, $map->count());
    }

    #[Test]
    public function largest_size_tracks_the_biggest_clone(): void
    {
        $map = new CodeCloneMap();
        $map->add($this->makeClone(self::FILE_A, 1, self::FILE_B, 1, lines: 5));
        $map->add($this->makeClone(self::FILE_A, 10, self::FILE_C, 1, lines: 20));

        self::assertSame(20, $map->largestSize());
    }

    #[Test]
    public function number_of_files_with_clones_is_counted(): void
    {
        $map = new CodeCloneMap();
        $map->add($this->makeClone(self::FILE_A, 1, self::FILE_B, 1, lines: 5));

        self::assertSame(2, $map->numberOfFilesWithClones());
    }

    #[Test]
    public function iterator_yields_all_added_clones(): void
    {
        $map = new CodeCloneMap();
        $map->add($this->makeClone(self::FILE_A, 1, self::FILE_B, 1, lines: 5));
        $map->add($this->makeClone(self::FILE_A, 10, self::FILE_C, 1, lines: 8));

        $collected = [];

        foreach ($map as $clone) {
            $collected[] = $clone;
        }

        self::assertCount(2, $collected);
    }

    #[Test]
    public function add_to_number_of_lines_accumulates(): void
    {
        $map = new CodeCloneMap();
        $map->addToNumberOfLines(100);
        $map->addToNumberOfLines(50);

        self::assertSame(150, $map->numberOfLines());
    }

    #[Test]
    public function percentage_is_zero_when_no_total_lines_tracked(): void
    {
        // No addToNumberOfLines call → numberOfLines is 0 → 0% (not 100%).
        self::assertSame('0.00%', (new CodeCloneMap())->percentage());
    }

    #[Test]
    public function percentage_reflects_duplicated_fraction(): void
    {
        $map = new CodeCloneMap();
        $map->addToNumberOfLines(100);
        $map->add($this->makeClone(self::FILE_A, 1, self::FILE_B, 1, lines: 10));

        // 10 duplicated lines out of 100 total = 10%
        self::assertSame('10.00%', $map->percentage());
    }

    private function makeClone(
        string $fileA,
        int $startA,
        string $fileB,
        int $startB,
        int $lines,
        int $tokens = 50,
    ): CodeClone {
        return new CodeClone(
            new CodeCloneFile($fileA, $startA),
            new CodeCloneFile($fileB, $startB),
            $lines,
            $tokens,
        );
    }
}
