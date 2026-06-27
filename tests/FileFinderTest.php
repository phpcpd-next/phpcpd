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

use function array_map;
use function basename;
use function str_contains;

use LucianoPereira\PhpcpdNext\Util\FileFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileFinder::class)]
final class FileFinderTest extends TestCase
{
    private const string TYPE2    = __DIR__ . '/fixtures/type2';
    private const string FIXTURES = __DIR__ . '/fixtures';

    #[Test]
    public function finds_files_matching_the_suffix(): void
    {
        $files = (new FileFinder())->find([self::TYPE2], ['.php'], []);

        self::assertSame(['rename_base.php', 'rename_variant.php'], $this->basenames($files));
    }

    #[Test]
    public function a_non_matching_suffix_finds_nothing(): void
    {
        self::assertSame([], (new FileFinder())->find([self::TYPE2], ['.txt'], []));
    }

    #[Test]
    public function substring_exclude_skips_matching_files(): void
    {
        $files = (new FileFinder())->find([self::TYPE2], ['.php'], ['variant']);

        self::assertSame(['rename_base.php'], $this->basenames($files));
    }

    #[Test]
    public function glob_exclude_skips_matching_files(): void
    {
        // The improvement over substring-only excludes: real glob patterns.
        $files = (new FileFinder())->find([self::TYPE2], ['.php'], ['*variant*']);

        self::assertSame(['rename_base.php'], $this->basenames($files));
    }

    #[Test]
    public function excluded_directories_are_pruned(): void
    {
        // Excluding a directory name skips everything under it (the type3/ fixtures),
        // while files in sibling directories are still found.
        $files = (new FileFinder())->find([self::FIXTURES], ['.php'], ['type3']);

        foreach ($files as $file) {
            self::assertStringNotContainsString('/type3/', $file);
        }

        self::assertContains('rename_base.php', $this->basenames($files));
        self::assertNotContains('clone_base.php', $this->basenames($files));
    }

    #[Test]
    public function nonexistent_directory_is_skipped(): void
    {
        self::assertSame([], (new FileFinder())->find(['/no/such/directory'], ['.php'], []));
    }

    #[Test]
    public function result_is_sorted_and_deduplicated(): void
    {
        // The same directory listed twice must not double-count.
        $files = (new FileFinder())->find([self::TYPE2, self::TYPE2], ['.php'], []);

        self::assertSame(['rename_base.php', 'rename_variant.php'], $this->basenames($files));
    }

    /**
     * @param  list<string> $files
     * @return list<string>
     */
    private function basenames(array $files): array
    {
        $names = array_map(static fn (string $f): string => basename($f), $files);

        // sanity: only files we expect (no stray excludes leaking)
        foreach ($files as $file) {
            self::assertTrue(str_contains($file, '/fixtures/'));
        }

        return $names;
    }
}
