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

namespace LucianoPereira\PhpcpdNext\Triage;

use function dirname;
use function is_file;

use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;

/**
 * Every composer.json above a file, nearest first.
 *
 * A monorepo does not have *a* manifest. The corpus this tool is developed
 * against carries 33 package manifests under one root, and a package's tests
 * live in a namespace only that package's own manifest declares. Asking the root
 * manifest alone whether it owns `Shared\Cache\Tests\Unit` gets the answer "no"
 * and condemns the project's own test suite as someone else's code — which is
 * what the first run of Stage 0's foreign rung did, and why this class exists.
 *
 * So the question is asked of the whole chain: a file is foreign only when *no*
 * manifest between it and the scan root claims it, by namespace or by wired
 * directory. Manifests are read once per directory and cached; a directory with
 * no manifest costs one `is_file()`.
 */
final class ManifestChain
{
    /** @var array<string, ?ComposerManifest> directory => its own manifest, or null */
    private array $manifests = [];

    /** @param list<ComposerManifest> $roots manifests to consult for every file */
    public function __construct(private array $roots = []) {}

    /**
     * Is $file claimed by no manifest above it — neither by a namespace one of
     * them owns, nor by sitting inside a directory one of them wires?
     *
     * @param list<string> $namespaces what the file declares; an empty namespace
     *                                 is never evidence, since the root namespace
     *                                 belongs to nobody
     */
    public function foreign(string $file, array $namespaces): bool
    {
        if ($namespaces === []) {
            return false;
        }

        foreach ($namespaces as $namespace) {
            if ($namespace === '') {
                return false;
            }
        }

        foreach ($this->chain($file) as $manifest) {
            if ($manifest->wires($file)) {
                return false;
            }

            foreach ($namespaces as $namespace) {
                if ($manifest->owns($namespace)) {
                    return false;
                }
            }
        }

        return $this->chain($file) !== [];
    }

    /**
     * The manifests above $file, nearest first, then the roots this chain was
     * constructed with.
     *
     * @return list<ComposerManifest>
     */
    private function chain(string $file): array
    {
        $chain    = [];
        $directory = dirname($file);
        $seen      = 0;

        // 12 levels is deeper than any real package nesting and stops a symlink
        // loop from walking forever.
        while ($seen < 12) {
            $manifest = $this->manifestIn($directory);

            if ($manifest !== null) {
                $chain[] = $manifest;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
            $seen++;
        }

        foreach ($this->roots as $root) {
            $chain[] = $root;
        }

        return $chain;
    }

    private function manifestIn(string $directory): ?ComposerManifest
    {
        if (!isset($this->manifests[$directory])) {
            $this->manifests[$directory] = is_file($directory . '/composer.json')
                ? ComposerManifest::locate([$directory])
                : null;
        }

        return $this->manifests[$directory];
    }
}
