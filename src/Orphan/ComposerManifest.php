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

namespace LucianoPereira\PhpcpdNext\Orphan;

use function dirname;
use function is_array;
use function is_file;
use function is_string;
use function json_decode;
use function file_get_contents;
use function realpath;
use function rtrim;
use function str_starts_with;

/**
 * composer.json read as a set of reference roots.
 *
 * A collector that only opens `.php` files cannot see any of this, yet every one
 * of these entries wires code that nothing in the source references by name:
 *
 *   bin              extensionless console entry points a `.php` suffix filter skips
 *   autoload.files   loaded for side effects into every consuming project
 *   autoload.psr-4   which namespaces the project actually OWNS
 *   extra.*          framework auto-discovery, instantiated by name from JSON
 *
 * The psr-4 map is the most valuable of the four and the least obviously about
 * references: a symbol declared in a namespace the project does not own is a
 * compatibility shim, published under another package's namespace precisely so
 * that someone else's call resolves to it. Nothing here can reference it.
 */
final readonly class ComposerManifest
{
    private const int MAX_ASCENT = 8;

    /**
     * @param list<string>          $prefixes        declared psr-4 / psr-0 namespace prefixes
     * @param list<string>          $mappedDirs      absolute directories those prefixes map to
     * @param array<string, true>   $entryPointFiles absolute paths from autoload.files
     * @param list<string>          $binFiles        absolute paths from bin
     * @param array<string, string> $references      name => location, from extra.*
     */
    private function __construct(
        public string $file,
        public array $prefixes,
        public array $mappedDirs,
        public array $entryPointFiles,
        public array $binFiles,
        public array $references,
    ) {}

    /**
     * The nearest composer.json at or above any scan root. Walking up matters:
     * scanning `src` is the normal case, and the manifest is its parent's.
     *
     * @param list<string> $roots
     */
    public static function locate(array $roots): ?self
    {
        foreach ($roots as $root) {
            $dir = realpath($root);

            if ($dir === false) {
                continue;
            }

            for ($i = 0; $i < self::MAX_ASCENT; $i++) {
                $candidate = $dir . '/composer.json';

                if (is_file($candidate)) {
                    return self::read($candidate);
                }

                $parent = dirname($dir);

                if ($parent === $dir) {
                    break;
                }

                $dir = $parent;
            }
        }

        return null;
    }

    private static function read(string $file): ?self
    {
        $raw = file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        /** @var mixed $data */
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            return null;
        }

        $dir        = dirname($file);
        $prefixes   = [];
        $mappedDirs = [];
        $entries    = [];
        $bins       = [];
        $references = [];

        foreach (['autoload', 'autoload-dev'] as $section) {
            $autoload = $data[$section] ?? null;

            if (!is_array($autoload)) {
                continue;
            }

            foreach (['psr-4', 'psr-0'] as $standard) {
                $map = $autoload[$standard] ?? null;

                if (!is_array($map)) {
                    continue;
                }

                /** @var mixed $paths */
                foreach ($map as $prefix => $paths) {
                    if (!is_string($prefix) || $prefix === '') {
                        continue;
                    }

                    $prefixes[] = rtrim($prefix, '\\');

                    foreach (is_array($paths) ? $paths : [$paths] as $path) {
                        if (is_string($path)) {
                            $mappedDirs[] = rtrim($dir . '/' . rtrim($path, '/'), '/');
                        }
                    }
                }
            }

            foreach (self::strings($autoload['files'] ?? null) as $path) {
                $resolved = realpath($dir . '/' . $path);

                if ($resolved !== false) {
                    $entries[$resolved] = true;
                }
            }
        }

        foreach (self::strings($data['bin'] ?? null) as $path) {
            $resolved = realpath($dir . '/' . $path);

            if ($resolved !== false) {
                $bins[] = $resolved;
            }
        }

        if (isset($data['extra']) && is_array($data['extra'])) {
            self::collectExtra($data['extra'], 'composer.json', $references);
        }

        return new self($file, $prefixes, $mappedDirs, $entries, $bins, $references);
    }

    /**
     * Any FQN-shaped string anywhere under `extra` is a name some tool will
     * instantiate — Laravel providers and aliases are only the common case.
     *
     * @param array<array-key, mixed> $node
     * @param array<string, string>   $into
     */
    private static function collectExtra(array $node, string $label, array &$into): void
    {
        /** @var mixed $value */
        foreach ($node as $value) {
            if (is_array($value)) {
                self::collectExtra($value, $label, $into);
            } elseif (is_string($value)) {
                FqnScanner::collect($value, $label, $into);
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        $result = [];

        foreach (is_array($value) ? $value : [$value] as $item) {
            if (is_string($item) && $item !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }

    /** Does $namespace fall under a prefix this project declares? */
    public function owns(string $namespace): bool
    {
        if ($this->prefixes === []) {
            return true;
        }

        foreach ($this->prefixes as $prefix) {
            if ($namespace === $prefix || str_starts_with($namespace . '\\', $prefix . '\\')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function mappedDirectories(): array
    {
        return $this->mappedDirs;
    }
}
