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
use function count;
use function fclose;
use function fnmatch;
use function fopen;
use function fread;
use function in_array;
use function is_dir;
use function sort;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function stripos;
use function strpbrk;
use function strpos;
use function str_starts_with;
use function substr;

/**
 * Finds files to scan. Replaces phpunit/php-file-iterator with three improvements:
 *
 *  1. Excluded directories are PRUNED during traversal — the walk never descends
 *     into vendor/ etc., instead of walking everything and filtering afterwards.
 *  2. Exclude patterns may be glob patterns (e.g. "*.blade.php", "build/*"), not
 *     only plain substrings — while substring excludes still work for compatibility.
 *  3. Generated and cached trees are excluded by default. A tool cache is
 *     adversarial input for reference detection: PHPStan's result cache embeds
 *     every analysed class name as a string literal, which silently satisfies the
 *     orphan scan's reference check. Defaults match whole path SEGMENTS, never
 *     substrings, so a default named "out" cannot swallow "routes/".
 */
final class FileFinder
{
    /**
     * Directory names pruned unless default excludes are turned off. Cache
     * directories are here for correctness, the rest for cost.
     *
     * @var list<string>
     */
    private const array DEFAULT_EXCLUDED_DIRS = [
        'vendor', 'node_modules', '.git',
        '.phpstan.cache', '.phpunit.cache', '.php-cs-fixer.cache', '.psalm-cache', '.rector.cache',
        'build', 'dist', 'out', 'coverage',
    ];

    /**
     * Multi-segment directory paths pruned by default — framework cache trees
     * whose last segment alone ("cache", "framework") is too common to prune.
     *
     * @var list<string>
     */
    private const array DEFAULT_EXCLUDED_PATHS = [
        'var/cache', 'storage/framework', 'bootstrap/cache',
    ];

    /**
     * Markers that identify a generated file wherever it lives, for the trees a
     * path-based default cannot predict. Matched case-insensitively.
     *
     * @var list<string>
     */
    private const array GENERATED_MARKERS = ['@generated', 'do not edit', 'auto-generated'];

    private const int GENERATED_PROBE_BYTES = 2048;

    /**
     * @param list<string> $directories
     * @param list<string> $suffixes match files ending in any of these (empty = all)
     * @param list<string> $excludes substring or glob patterns to skip
     * @param bool         $defaultExcludes prune generated/cache trees and skip generated files
     * @return list<string>
     */
    public function find(array $directories, array $suffixes, array $excludes, bool $defaultExcludes = true): array
    {
        $files = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach ($this->walk($directory, $suffixes, $excludes, $defaultExcludes) as $file) {
                $files[$file] = true;
            }
        }

        $result = array_keys($files);
        sort($result);

        return $result;
    }

    /** Number of patterns the default exclude set contributes, for the scope line. */
    public static function defaultExcludeCount(): int
    {
        return count(self::DEFAULT_EXCLUDED_DIRS) + count(self::DEFAULT_EXCLUDED_PATHS);
    }

    /**
     * @param list<string> $suffixes
     * @param list<string> $excludes
     * @return list<string>
     */
    private function walk(string $directory, array $suffixes, array $excludes, bool $defaultExcludes): array
    {
        $filter = new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            function (\SplFileInfo $entry) use ($suffixes, $excludes, $defaultExcludes): bool {
                $path = $entry->getPathname();

                if ($entry->isDir()) {
                    // Prune: returning false stops recursion into this directory entirely.
                    return !$this->isExcluded($path, $entry->getFilename(), $excludes)
                        && !($defaultExcludes && $this->isDefaultExcludedDir($path, $entry->getFilename()));
                }

                return $this->isCandidate($path, $entry->getFilename(), $suffixes)
                    && !$this->isExcluded($path, $entry->getFilename(), $excludes)
                    && !($defaultExcludes && $this->isGenerated($path));
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

    /**
     * A file is scanned when its suffix matches, or — for an extensionless file —
     * when a `#!` line names php. Console entry points are conventionally
     * extensionless (`artisan`, `bin/console`), so a suffix filter never sees the
     * one file where top-level wiring lives.
     *
     * @param list<string> $suffixes
     */
    private function isCandidate(string $path, string $name, array $suffixes): bool
    {
        if ($this->hasSuffix($path, $suffixes)) {
            return true;
        }

        return !str_contains($name, '.') && $this->hasPhpShebang($path);
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

    /**
     * Segment match, not substring: "out" prunes a directory named out/, never a
     * path merely containing those letters.
     */
    private function isDefaultExcludedDir(string $path, string $name): bool
    {
        if (in_array($name, self::DEFAULT_EXCLUDED_DIRS, true)) {
            return true;
        }

        $normalized = str_replace('\\', '/', $path);

        foreach (self::DEFAULT_EXCLUDED_PATHS as $tail) {
            if (str_ends_with($normalized, '/' . $tail) || $normalized === $tail) {
                return true;
            }
        }

        return false;
    }

    private function isGenerated(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = fread($handle, self::GENERATED_PROBE_BYTES);
        fclose($handle);

        if ($head === false) {
            return false;
        }

        foreach (self::GENERATED_MARKERS as $marker) {
            if (stripos($head, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    private function hasPhpShebang(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = fread($handle, 128);
        fclose($handle);

        if ($head === false || !str_starts_with($head, '#!')) {
            return false;
        }

        $break = strpos($head, "\n");

        return stripos($break === false ? $head : substr($head, 0, $break), 'php') !== false;
    }

    private function isGlob(string $pattern): bool
    {
        return strpbrk($pattern, '*?[') !== false;
    }
}
