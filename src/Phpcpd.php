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

use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * Headless mode: the one-call programmatic entry point for embedding phpcpd-next
 * in another tool — a PHPUnit assertion, a Laravel Artisan command, a CI script —
 * without shelling out to the binary, parsing argv, or printing a banner.
 *
 * It finds files, runs the same {@see Engine} the CLI uses, and returns the raw
 * {@see CodeCloneMap} for the caller to inspect. There is no I/O and no global
 * state, so it is safe to call repeatedly within one process (e.g. once per test).
 *
 *   $clones = Phpcpd::detect('app', preset: 'laravel', minTokens: 60);
 *
 *   if ($clones->count() > 0) { ... }
 */
final class Phpcpd
{
    /**
     * @param string|list<non-empty-string> $paths one or more directories to scan
     * @param ?string                  $algorithm null = default (Rabin-Karp + TokenBag);
     *                                            or 'rabin-karp' | 'suffixtree' | 'tokenbag'
     * @param list<non-empty-string>   $exclude  substring/glob patterns to skip (merged after a preset's)
     * @param list<non-empty-string>   $suffixes file suffixes to include (a preset overrides this default)
     * @param ?string              $preset   a built-in preset name (e.g. 'laravel'); seeds the defaults
     *
     * @throws InvalidStrategyException for an unknown algorithm or preset
     */
    public static function detect(
        string|array $paths = [],
        int $minLines = 5,
        int $minTokens = 70,
        ?string $algorithm = null,
        array $exclude = [],
        array $suffixes = ['.php'],
        ?string $preset = null,
        bool $fuzzy = false,
        bool $typeAnchored = false,
    ): CodeCloneMap {
        /** @var list<string> $paths */
        $paths = (array) $paths;

        if ($preset !== null) {
            $definition = Presets::get($preset)
                ?? throw new InvalidStrategyException('Unknown preset: ' . $preset);

            $suffixes  = $definition->suffixes;
            $exclude   = [...$definition->exclude, ...$exclude];
            $minLines  = $definition->minLines ?? $minLines;
            $minTokens = $definition->minTokens ?? $minTokens;

            if ($paths === []) {
                $paths = $definition->paths;
            }
        }

        $files = (new FileFinder())->find($paths, $suffixes, $exclude);

        $config = new StrategyConfiguration(new Arguments(
            directories:      [],
            suffixes:         $suffixes,
            exclude:          $exclude,
            pmdCpdXmlLogfile: null,
            linesThreshold:   $minLines,
            tokensThreshold:  $minTokens,
            fuzzy:            $fuzzy,
            verbose:          false,
            help:             false,
            version:          false,
            algorithm:        $algorithm,
            editDistance:     5,
            headEquality:     10,
            typeAnchored:     $typeAnchored,
        ));

        return (new Engine($config, $algorithm))->detect($files);
    }
}
