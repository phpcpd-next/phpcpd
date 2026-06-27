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

namespace LucianoPereira\PhpcpdNext\Util;

use function array_keys;
use function fnmatch;
use function is_dir;
use function sort;
use function str_contains;
use function str_ends_with;
use function strpbrk;

/**
 * Finds files to scan. Replaces phpunit/php-file-iterator with two improvements:
 *
 *  1. Excluded directories are PRUNED during traversal — the walk never descends
 *     into vendor/ etc., instead of walking everything and filtering afterwards.
 *  2. Exclude patterns may be glob patterns (e.g. "*.blade.php", "build/*"), not
 *     only plain substrings — while substring excludes still work for compatibility.
 */
final class FileFinder
{
    /**
     * @param list<string> $directories
     * @param list<string> $suffixes match files ending in any of these (empty = all)
     * @param list<string> $excludes substring or glob patterns to skip
     * @return list<string>
     */
    public function find(array $directories, array $suffixes, array $excludes): array
    {
        $files = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach ($this->walk($directory, $suffixes, $excludes) as $file) {
                $files[$file] = true;
            }
        }

        $result = array_keys($files);
        sort($result);

        return $result;
    }

    /**
     * @param list<string> $suffixes
     * @param list<string> $excludes
     * @return list<string>
     */
    private function walk(string $directory, array $suffixes, array $excludes): array
    {
        $filter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            function (\SplFileInfo $entry) use ($suffixes, $excludes): bool {
                if ($entry->isDir()) {
                    // Prune: returning false stops recursion into this directory entirely.
                    return !$this->isExcluded($entry->getPathname(), $entry->getFilename(), $excludes);
                }

                return $this->hasSuffix($entry->getPathname(), $suffixes)
                    && !$this->isExcluded($entry->getPathname(), $entry->getFilename(), $excludes);
            },
        );

        $result = [];

        foreach (new \RecursiveIteratorIterator($filter) as $entry) {
            if ($entry instanceof \SplFileInfo && $entry->isFile()) {
                $result[] = $entry->getPathname();
            }
        }

        return $result;
    }

    /** @param list<string> $suffixes */
    private function hasSuffix(string $path, array $suffixes): bool
    {
        if ($suffixes === []) {
            return true;
        }

        foreach ($suffixes as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $excludes */
    private function isExcluded(string $path, string $name, array $excludes): bool
    {
        foreach ($excludes as $exclude) {
            if ($this->isGlob($exclude)) {
                if (fnmatch($exclude, $path) || fnmatch($exclude, $name)) {
                    return true;
                }
            } elseif (str_contains($path, $exclude)) {
                return true;
            }
        }

        return false;
    }

    private function isGlob(string $pattern): bool
    {
        return strpbrk($pattern, '*?[') !== false;
    }
}
