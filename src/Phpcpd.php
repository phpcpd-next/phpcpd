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
 *
 * Every named parameter is translated to its CLI option and folded through
 * {@see Settings::resolve()} — the same resolution the command line uses, so the
 * two can never disagree. That is also why the threshold parameters default to
 * null rather than to a number: null means "the engine's default", and the
 * default itself lives in exactly one place ({@see Settings}), not re-declared
 * in this signature.
 *
 * @api
 */
final class Phpcpd
{
    /**
     * Does this installation have the surface named by `$capability`?
     *
     * A probe an embedder can call without knowing which version it is bound to,
     * so that supporting two of them costs neither version arithmetic nor
     * `method_exists()` over every member.
     *
     * **The parameter is a string, and deliberately not an enum.** An enum
     * argument cannot name a case the installed version has never heard of,
     * which is precisely the situation the probe exists for: a consumer written
     * against a later vocabulary must degrade here, not fatal. Unrecognised
     * returns `false` for the same reason.
     *
     * **A capability answers "can I call this", never "will it be populated".**
     * `line-spans` is true because {@see CodeCloneFile::$numberOfLines} exists to
     * read, not because every occurrence carries one — Rabin-Karp measures
     * neither of its occurrences and says so with null. The data reports what it
     * knows; the capability reports what exists to call. There is no algorithm
     * argument, because the algorithm is chosen at {@see detect()} time from
     * configuration an embedder typically passes straight through, and a probe
     * that had to know it could not answer.
     *
     * Recognised in 2.0.0:
     *
     *   - `default-excludes` — {@see detect()} takes `$defaultExcludes`.
     *   - `divergences`      — {@see CodeClone::divergences()} and
     *                          {@see CodeClone::isReordered()} name the ranges
     *                          two copies disagree over, where the engine
     *                          computed them.
     *   - `line-spans`       — {@see CodeCloneFile::$numberOfLines} and
     *                          {@see CodeCloneFile::lastLine()} give an
     *                          occurrence's own extent.
     *
     * `classification` is deliberately **not** recognised. The engine computes a
     * Type-1/Type-2/gapped distinction and `CodeClone` currently discards it, so
     * there is nothing for an embedder to call; a capability string that
     * predates the surface it names is worse than no string, because a consumer
     * lights up output against it and gets nothing back. It becomes true when a
     * clone can be asked its type.
     */
    public static function supports(string $capability): bool
    {
        return match ($capability) {
            'default-excludes', 'divergences', 'line-spans' => true,
            default                                        => false,
        };
    }

    /**
     * @param string|list<non-empty-string> $paths        one or more directories to scan
     * @param ?int                          $minLines     null = the engine default
     * @param ?int                          $minTokens    null = the engine default
     * @param ?string                       $algorithm    null = default (Rabin-Karp + TokenBag);
     *                                                    or 'rabin-karp' | 'unified' | 'tokenbag'
     * @param list<non-empty-string>        $exclude      patterns appended to the preset's / defaults, exactly like --exclude
     * @param list<non-empty-string>        $suffixes     suffixes appended to the preset's / defaults, exactly like --suffix
     * @param ?string                       $preset       a built-in preset name (e.g. 'laravel'); seeds the defaults
     * @param bool                          $defaultExcludes prune generated and cache trees, as the CLI does.
     *                                                    Present so an embedder can turn it off: {@see Orphans::detect()}
     *                                                    has taken it since it shipped, and this facade reaching the
     *                                                    same setting only through the CLI's option list was an
     *                                                    asymmetry an embedder had no way around.
     *
     * @throws SettingsException        for an unknown preset
     * @throws InvalidStrategyException for an unknown algorithm
     */
    public static function detect(
        string|array $paths = [],
        ?int $minLines = null,
        ?int $minTokens = null,
        ?string $algorithm = null,
        array $exclude = [],
        array $suffixes = [],
        ?string $preset = null,
        bool $fuzzy = false,
        bool $typeAnchored = false,
        bool $defaultExcludes = true,
    ): CodeCloneMap {
        $pairs = self::pairs($preset, $suffixes, $exclude, $minLines, $minTokens, $algorithm, $fuzzy, $typeAnchored);

        if (!$defaultExcludes) {
            $pairs[] = ['no-default-excludes', null];
        }

        $settings = Settings::resolve($pairs, self::directories($paths));

        $files = (new FileFinder())->find(
            $settings->directories,
            $settings->suffixes,
            $settings->exclude,
            $settings->defaultExcludes,
        );

        return (new Engine($settings->strategy(), $settings->algorithm))->detect($files);
    }

    /**
     * The named parameters, as the option pairs the fold understands. Only what
     * the caller actually said is emitted — an omitted parameter contributes
     * nothing, so the fold's defaults (and the preset's seeds) hold exactly as
     * they would on the command line.
     *
     * @param list<non-empty-string> $suffixes
     * @param list<non-empty-string> $exclude
     * @return list<array{0: string, 1: ?string}>
     */
    private static function pairs(
        ?string $preset,
        array $suffixes,
        array $exclude,
        ?int $minLines,
        ?int $minTokens,
        ?string $algorithm,
        bool $fuzzy,
        bool $typeAnchored,
    ): array {
        $pairs = [];

        if ($preset !== null) {
            $pairs[] = ['preset', $preset];
        }

        foreach ($suffixes as $suffix) {
            $pairs[] = ['suffix', $suffix];
        }

        foreach ($exclude as $pattern) {
            $pairs[] = ['exclude', $pattern];
        }

        if ($minLines !== null) {
            $pairs[] = ['min-lines', (string) $minLines];
        }

        if ($minTokens !== null) {
            $pairs[] = ['min-tokens', (string) $minTokens];
        }

        if ($algorithm !== null) {
            $pairs[] = ['algorithm', $algorithm];
        }

        if ($fuzzy) {
            $pairs[] = ['fuzzy', null];
        }

        if ($typeAnchored) {
            $pairs[] = ['type-anchored', null];
        }

        return $pairs;
    }

    /**
     * @param string|list<non-empty-string> $paths
     * @return list<non-empty-string>
     */
    private static function directories(string|array $paths): array
    {
        return array_values(array_filter(
            (array) $paths,
            static fn(string $path): bool => $path !== '',
        ));
    }
}
