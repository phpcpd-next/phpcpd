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

use LucianoPereira\PhpcpdNext\Orphan\OrphanDetector;
use LucianoPereira\PhpcpdNext\Orphan\OrphanResult;
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
 * @api
 */
final class Orphans
{
    /**
     * @param string|list<non-empty-string> $paths    one or more directories to scan
     * @param list<non-empty-string>         $exclude  substring/glob patterns to skip (merged after a preset's)
     * @param list<non-empty-string>         $suffixes file suffixes to include
     * @param ?string                        $preset   a built-in preset name (e.g. 'laravel'); seeds the defaults
     *
     * @throws InvalidStrategyException for an unknown preset
     */
    public static function detect(
        string|array $paths = [],
        array $exclude = [],
        array $suffixes = ['.php'],
        ?string $preset = null,
    ): OrphanResult {
        $paths = array_values(array_filter(
            (array) $paths,
            static fn(string $path): bool => $path !== '',
        ));

        if ($preset !== null) {
            $definition = Presets::get($preset)
                ?? throw new InvalidStrategyException('Unknown preset: ' . $preset);

            $suffixes = $definition->suffixes;
            $exclude  = [...$definition->exclude, ...$exclude];

            if ($paths === []) {
                $paths = $definition->paths;
            }
        }

        $files = (new FileFinder())->find($paths, $suffixes, $exclude);

        // A Rabin–Karp duplication pass over the same files lets the detector
        // flag orphans that are superseded copies of live code. Reuse the clone
        // facade rather than re-deriving a configuration (the preset has already
        // been folded into $paths/$suffixes/$exclude above).
        $clones = Phpcpd::detect($paths, algorithm: 'rabin-karp', exclude: $exclude, suffixes: $suffixes);

        return (new OrphanDetector())->detect($files, $clones);
    }
}
