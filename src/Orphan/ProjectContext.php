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
use function file_get_contents;
use function realpath;
use function str_starts_with;

use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * What the project says about itself, from the files a `.php` scan never opens:
 * composer.json and the config formats that wire classes by name.
 *
 * Project-level rules stand down when the scan is pointed BELOW the directories
 * the manifest maps. Aiming a scan at one subtree is a statement that the subtree
 * is the world for this run — its files are ordinary code, not "code sitting in
 * someone else's project" — so a namespace or fixture rule derived from the
 * enclosing project would be judging files by a structure the user opted out of.
 * The same principle governs the fixture-path rule in {@see OrphanDetector}.
 */
final readonly class ProjectContext
{
    /** @var list<string> */
    private const array CONFIG_SUFFIXES = ['.neon', '.yaml', '.yml', '.xml', '.dist'];

    /**
     * @param array<string, string> $manifestNames   name => location, from composer.json
     * @param array<string, string> $configNames     name => "file:line", from neon/yaml/xml
     * @param array<string, true>   $entryPointFiles files whose declarations are entry points
     */
    public function __construct(
        public ?ComposerManifest $manifest = null,
        public bool $manifestApplies = false,
        public array $manifestNames = [],
        public array $configNames = [],
        public array $entryPointFiles = [],
    ) {}

    /**
     * @param list<string> $roots
     * @param list<string> $excludes
     */
    public static function discover(array $roots, array $excludes, OrphanConfiguration $config): self
    {
        $manifest = ComposerManifest::locate($roots);
        $applies  = $manifest !== null && self::coversProject($roots, $manifest);

        $manifestNames = [];
        $configNames   = [];
        $entries       = [];
        $configRoots   = $roots;

        if ($manifest !== null && $applies) {
            $configRoots = [dirname($manifest->file)];

            if ($config->ruleEnabled(Rule::MANIFEST)) {
                $manifestNames = $manifest->references;
                $entries       = $manifest->entryPointFiles;
            }
        }

        if ($config->ruleEnabled(Rule::CONFIG)) {
            self::scanConfigFiles($configRoots, $excludes, $configNames);
        }

        return new self($manifest, $applies, $manifestNames, $configNames, $entries);
    }

    /**
     * Is $file one of the manifest's `autoload.files`? Its top-level declarations
     * are loaded for side effects into every consuming project, so they are entry
     * points for code this scan cannot see.
     */
    public function isEntryPointFile(string $file): bool
    {
        $resolved = realpath($file);

        return $resolved !== false && isset($this->entryPointFiles[$resolved]);
    }

    /** Does the project declare ownership of $namespace? */
    public function ownsNamespace(string $namespace): bool
    {
        return !$this->manifestApplies
            || $this->manifest === null
            || $this->manifest->owns($namespace);
    }

    /**
     * Is at least one scan root at or above a directory the manifest maps?
     *
     * @param list<string> $roots
     */
    private static function coversProject(array $roots, ComposerManifest $manifest): bool
    {
        foreach ($roots as $root) {
            $resolved = realpath($root);

            if ($resolved === false) {
                continue;
            }

            foreach ($manifest->mappedDirectories() as $mapped) {
                $target = realpath($mapped);

                if ($target !== false && self::isAncestorOrSelf($resolved, $target)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isAncestorOrSelf(string $ancestor, string $path): bool
    {
        return $path === $ancestor || str_starts_with($path . '/', $ancestor . '/');
    }

    /**
     * @param list<string>          $roots
     * @param list<string>          $excludes
     * @param array<string, string> $names
     */
    private static function scanConfigFiles(array $roots, array $excludes, array &$names): void
    {
        foreach ((new FileFinder())->find($roots, self::CONFIG_SUFFIXES, $excludes) as $file) {
            $contents = file_get_contents($file);

            if ($contents !== false) {
                FqnScanner::collect($contents, $file, $names);
            }
        }
    }
}
