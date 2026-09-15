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

namespace LucianoPereira\PhpcpdNext;

use function array_filter;
use function array_values;
use function implode;
use function in_array;
use function sort;

use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;
use LucianoPereira\PhpcpdNext\Orphan\OrphanConfiguration;
use LucianoPereira\PhpcpdNext\Orphan\OrphanDetector;
use LucianoPereira\PhpcpdNext\Orphan\OrphanResult;
use LucianoPereira\PhpcpdNext\Orphan\ProjectContext;
use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * Headless orphan detection: the one-call programmatic entry point, mirroring
 * {@see Phpcpd::detect()} on the clone side. Finds files and runs the same
 * {@see OrphanDetector} the CLI uses, returning the raw {@see OrphanResult} for
 * a caller to inspect — a PHPUnit assertion, a CI script, an Artisan command —
 * with no I/O and no global state.
 *
 *   $orphans = Orphans::detect('src');
 *
 *   if ($orphans->hasDefiniteOrphans()) { ... }
 *
 * Like the clone facade, every parameter is translated to its CLI option and
 * folded through {@see Settings::resolve()}, so the headless answer and the
 * `--orphans` answer come from one resolution path.
 *
 * @api
 */
final class Orphans
{
    /**
     * @param string|list<non-empty-string> $paths           one or more directories to scan
     * @param list<non-empty-string>        $exclude         patterns appended to the preset's / defaults, exactly like --exclude
     * @param list<non-empty-string>        $suffixes        suffixes appended to the preset's / defaults, exactly like --suffix
     * @param ?string                       $preset          a built-in preset name (e.g. 'laravel'); seeds the defaults
     * @param list<non-empty-string>        $noSuppress      suppression rules to disable by name, or ['all']
     * @param list<non-empty-string>        $failOn          tiers that make hasDefiniteOrphans-style gating fail
     * @param bool                          $defaultExcludes prune generated/cache trees
     *
     * @throws SettingsException for an unknown preset
     */
    public static function detect(
        string|array $paths = [],
        array $exclude = [],
        array $suffixes = [],
        ?string $preset = null,
        array $noSuppress = [],
        array $failOn = [OrphanConfiguration::TIER_DEAD],
        bool $defaultExcludes = true,
    ): OrphanResult {
        $pairs = [['orphans', null], ['fail-on', implode(',', $failOn)]];

        if ($preset !== null) {
            $pairs[] = ['preset', $preset];
        }

        foreach ($suffixes as $suffix) {
            $pairs[] = ['suffix', $suffix];
        }

        foreach ($exclude as $pattern) {
            $pairs[] = ['exclude', $pattern];
        }

        foreach ($noSuppress as $rule) {
            $pairs[] = ['no-suppress', $rule];
        }

        if (!$defaultExcludes) {
            $pairs[] = ['no-default-excludes', null];
        }

        $settings = Settings::resolve($pairs, array_values(array_filter(
            (array) $paths,
            static fn(string $path): bool => $path !== '',
        )));

        $config  = new OrphanConfiguration($settings->directories, $settings->noSuppress, $settings->failOn);
        $context = ProjectContext::discover($settings->directories, $settings->exclude, $config);
        $files   = self::filesFor($settings, $context);

        // A Rabin–Karp duplication pass over the same files lets the detector
        // flag orphans that are superseded copies of live code — the same pass,
        // over the same file set, the CLI's --orphans mode runs.
        $clones = (new Engine($settings->strategy(), 'rabin-karp'))->detect($files);

        return (new OrphanDetector())->detect($files, $clones, $config, $context);
    }

    /**
     * The scanned set, plus any console entry point composer declares in `bin`.
     * Those are conventionally extensionless, so a suffix filter never sees the
     * one file where an application's top-level wiring lives.
     *
     * @return list<string>
     */
    private static function filesFor(Settings $settings, ProjectContext $context): array
    {
        $files = (new FileFinder())->find(
            $settings->directories,
            $settings->suffixes,
            $settings->exclude,
            $settings->defaultExcludes,
        );

        if (!$context->manifestApplies || !$context->manifest instanceof ComposerManifest) {
            return $files;
        }

        foreach ($context->manifest->binFiles as $bin) {
            if (!in_array($bin, $files, true)) {
                $files[] = $bin;
            }
        }

        sort($files);

        return $files;
    }
}
