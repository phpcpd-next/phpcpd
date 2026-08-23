<?php

declare(strict_types=1);
/*
 * This file is part of PHP Copy/Paste Detector (PHPCPD).
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LucianoPereira\PhpcpdNext;

use function explode;
use function trim;

final class ArgumentsBuilder
{
    /**
     * @param list<string> $argv
     * @throws ArgumentsBuilderException
     */
    public function build(array $argv): Arguments
    {
        $definitions = Options::definitions();
        $parsed      = (new OptionParser())->parse($definitions, $argv);

        // Settings are parsed as if they preceded argv, so an explicit flag wins
        // on scalars and a repeatable option appends to what the file declared.
        $parsed['options'] = [
            ...$this->settings($parsed['options'], $parsed['arguments'], $definitions),
            ...$parsed['options'],
        ];

        $directories = [];

        foreach ($parsed['arguments'] as $directory) {
            if ($directory !== '') {
                $directories[] = $directory;
            }
        }

        // A preset seeds the defaults below. Explicit flags then override
        // (thresholds) or append to (suffix/exclude) those seeds, so a preset
        // is a starting point, never a ceiling.
        $preset = null;

        foreach ($parsed['options'] as [$name, $value]) {
            if ($name === 'preset' && $value !== null && $value !== '') {
                $preset = Presets::get($value);

                if ($preset === null) {
                    throw new ArgumentsBuilderException('Unknown preset: ' . $value);
                }
            }
        }

        $exclude          = $preset->exclude ?? [];
        $suffixes         = $preset->suffixes ?? ['.php'];
        $pmdCpdXmlLogfile = null;
        $jsonLogfile      = null;
        $sarifLogfile     = null;
        $cacheDir         = null;
        $linesThreshold   = $preset->minLines ?? 5;
        $tokensThreshold  = $preset->minTokens ?? 70;
        $editDistance     = 5;
        $headEquality     = 10;
        $fuzzy            = false;
        $typeAnchored     = false;
        $incremental      = false;
        $orphans          = false;
        $defaultExcludes  = true;
        $noSuppress       = [];
        $failOn           = ['dead'];
        $explain          = false;
        $showConfig       = false;
        $verbose          = false;
        $help             = false;
        $version          = false;
        $algorithm        = null; // null = combined rk+tb (default)
        $similarity       = 0.7;

        foreach ($parsed['options'] as [$name, $value]) {
            switch ($name) {
                case 'suffix':
                    if ($value !== null && $value !== '') {
                        $suffixes[] = $value;
                    }
                    break;
                case 'exclude':
                    if ($value !== null && $value !== '') {
                        $exclude[] = $value;
                    }
                    break;
                case 'log-pmd':
                    $pmdCpdXmlLogfile = $value;
                    break;
                case 'log-json':
                    $jsonLogfile = $value;
                    break;
                case 'log-sarif':
                    $sarifLogfile = $value;
                    break;
                case 'fuzzy':
                    $fuzzy = true;
                    break;
                case 'type-anchored':
                    $typeAnchored = true;
                    break;
                case 'min-lines':
                    $linesThreshold = (int) $value;
                    break;
                case 'min-tokens':
                    $tokensThreshold = (int) $value;
                    break;
                case 'head-equality':
                    $headEquality = (int) $value;
                    break;
                case 'edit-distance':
                    $editDistance = (int) $value;
                    break;
                case 'verbose':
                    $verbose = true;
                    break;
                case 'help':
                    $help = true;
                    break;
                case 'version':
                    $version = true;
                    break;
                case 'rk':
                    $algorithm = 'rabin-karp';
                    break;
                case 'algorithm':
                    $algorithm = (string) $value;
                    break;
                case 'min-similarity':
                    $similarity = (float) $value;
                    break;
                case 'cache':
                    if ($cacheDir === null) {
                        $cacheDir = '.phpcpd-cache';
                    }
                    break;
                case 'cache-dir':
                    if ($value !== null && $value !== '') {
                        $cacheDir = $value;
                    }
                    break;
                case 'incremental':
                    $incremental = true;
                    break;
                case 'orphans':
                    $orphans = true;
                    break;
                case 'no-default-excludes':
                    $defaultExcludes = false;
                    break;
                case 'no-suppress':
                    $noSuppress = [...$noSuppress, ...$this->listValue($value)];
                    break;
                case 'fail-on':
                    $failOn = $this->listValue($value);
                    break;
                case 'explain':
                    $explain = true;
                    break;
                case 'show-config':
                    $showConfig = true;
                    break;
                case 'config':
                case 'no-config':
                    break;
            }
        }

        // No directory on the command line falls back to the preset's paths
        // (e.g. `phpcpd --preset=laravel` scans app/routes/database/config).
        if (empty($directories) && $preset !== null) {
            $directories = $preset->paths;
        }

        if (empty($directories) && !$help && !$version && !$showConfig) {
            throw new ArgumentsBuilderException('No directory specified');
        }

        return new Arguments(
            directories: $directories,
            suffixes: $suffixes,
            exclude: $exclude,
            pmdCpdXmlLogfile: $pmdCpdXmlLogfile,
            linesThreshold: $linesThreshold,
            tokensThreshold: $tokensThreshold,
            fuzzy: $fuzzy,
            typeAnchored: $typeAnchored,
            verbose: $verbose,
            help: $help,
            version: $version,
            algorithm: $algorithm,
            editDistance: $editDistance,
            headEquality: $headEquality,
            jsonLogfile: $jsonLogfile,
            sarifLogfile: $sarifLogfile,
            similarity: $similarity,
            cacheDir: $cacheDir,
            incremental: $incremental,
            orphans: $orphans,
            defaultExcludes: $defaultExcludes,
            noSuppress: $noSuppress,
            failOn: $failOn,
            explain: $explain,
            showConfig: $showConfig,
        );
    }

    /**
     * Settings from phpcpd.ini, as parser options, lowest precedence first: user
     * config then project config, or exactly the file --config names. An
     * implicit file is used only when found, so the common case stays
     * zero-configuration.
     *
     * @param list<array{0: string, 1: ?string}> $options
     * @param list<string>                       $paths the directories being scanned
     * @param list<OptionDefinition>             $definitions
     * @throws ArgumentsBuilderException
     * @return list<array{0: string, 1: ?string}>
     */
    private function settings(array $options, array $paths, array $definitions): array
    {
        $file = null;

        foreach ($options as [$name, $value]) {
            if ($name === 'no-config') {
                return [];
            }

            if ($name === 'config' && $value !== null && $value !== '') {
                $file = $value;
            }
        }

        // Concatenating layers low-to-high is all the precedence rule needs: the
        // option loop below already lets a later value replace a single-valued
        // option and append to a repeatable one.
        $files = $file !== null ? [$file] : ConfigFile::discoverAll($paths);

        $settings = [];

        foreach ($files as $candidate) {
            $settings = [...$settings, ...ConfigFile::read($candidate, $definitions)];
        }

        return $settings;
    }

    /**
     * Split a comma-separated option value. The parser has already validated
     * each element against the option's allowed set.
     *
     * @return list<non-empty-string>
     */
    private function listValue(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = [];

        foreach (explode(',', $value) as $item) {
            $item = trim($item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }
}
