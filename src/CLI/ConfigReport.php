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

use function implode;
use function in_array;
use function max;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;

use const PHP_EOL;

/**
 * What `--show-config` prints: every setting in force, its value, and which
 * layer put it there.
 *
 * Layered configuration is only trustworthy if it can be inspected. A value can
 * arrive from a user file, a project file, or the command line, and a setting no
 * layer mentions silently keeps its built-in default — which is the useful
 * behaviour and also the one that is impossible to verify by reading any single
 * file. This report is the answer to "why is it doing that?", and it is the same
 * argument the orphan report makes for counting suppressed symbols rather than
 * dropping them.
 */
final class ConfigReport
{
    private const string FALLBACK = '(*)';

    /**
     * Not reported: options that act on this run rather than configuring it, and
     * aliases whose value is already shown under their canonical name.
     *
     * @var list<string>
     */
    private const array UNREPORTED = [
        'help', 'version', 'config', 'no-config', 'show-config',
        'rk', 'cache',
    ];

    /**
     * @param list<string> $argv
     * @throws ArgumentsBuilderException
     */
    public static function render(array $argv, Arguments $arguments): string
    {
        $definitions = Options::definitions();
        $cli         = (new OptionParser())->parse($definitions, $argv);
        $sources     = self::sources($cli, $definitions);

        $rows = [];

        foreach ($definitions as $definition) {
            if (in_array($definition->name, self::UNREPORTED, true)) {
                continue;
            }

            $source = $sources[$definition->name] ?? null;

            // Research flags are hidden from --help; show them only once someone
            // has actually set one, so the table stays about the real config.
            if ($definition->advanced && $source === null) {
                continue;
            }

            $value = self::value($arguments, $definition->name);

            if ($value === null) {
                continue;
            }

            $rows[] = [$definition->name, $value, $source ?? self::FALLBACK . ' default'];
        }

        return self::layerList($cli) . PHP_EOL . self::table($rows) . PHP_EOL
            . '  ' . self::FALLBACK . ' falling back to the built-in default' . PHP_EOL;
    }

    /**
     * Which layer last set each option. Later layers override earlier ones, and
     * a repeatable option names every layer that contributed, because those
     * append rather than replace.
     *
     * @param array{options: list<array{0: string, 1: ?string}>, arguments: list<string>} $cli
     * @param list<OptionDefinition>                                                      $definitions
     * @return array<string, string>
     */
    private static function sources(array $cli, array $definitions): array
    {
        $repeatable = [];

        foreach ($definitions as $definition) {
            $repeatable[$definition->name] = $definition->repeatable;
        }

        $sources = [];

        foreach (self::layers($cli) as [$label, $options]) {
            foreach ($options as [$name]) {
                $sources[$name] = ($repeatable[$name] ?? false) && isset($sources[$name])
                    ? $sources[$name] . ' + ' . $label
                    : $label;
            }
        }

        return $sources;
    }

    /**
     * The layers in precedence order, lowest first.
     *
     * @param array{options: list<array{0: string, 1: ?string}>, arguments: list<string>} $cli
     * @return list<array{0: string, 1: list<array{0: string, 1: ?string}>}>
     */
    private static function layers(array $cli): array
    {
        $definitions = Options::definitions();
        $layers      = [];

        foreach (self::files($cli) as $label => $file) {
            $layers[] = [$label, ConfigFile::read($file, $definitions)];
        }

        $layers[] = ['command line', $cli['options']];

        return $layers;
    }

    /**
     * @param array{options: list<array{0: string, 1: ?string}>, arguments: list<string>} $cli
     * @return array<string, string>
     */
    private static function files(array $cli): array
    {
        $explicit = null;

        foreach ($cli['options'] as [$name, $value]) {
            if ($name === 'no-config') {
                return [];
            }

            if ($name === 'config' && $value !== null && $value !== '') {
                $explicit = $value;
            }
        }

        if ($explicit !== null) {
            return ['--config' => $explicit];
        }

        $files   = [];
        $user    = ConfigFile::userFile();
        $project = ConfigFile::discover($cli['arguments']);

        if ($user !== null) {
            $files['user'] = $user;
        }

        if ($project !== null && $project !== $user) {
            $files['project'] = $project;
        }

        return $files;
    }

    /**
     * @param array{options: list<array{0: string, 1: ?string}>, arguments: list<string>} $cli
     */
    private static function layerList(array $cli): string
    {
        $lines = '  Layers, lowest precedence first:' . PHP_EOL
            . '    built-in defaults' . PHP_EOL;

        foreach (self::files($cli) as $label => $file) {
            $lines .= sprintf('    %-14s %s' . PHP_EOL, $label, $file);
        }

        return $lines . '    command line' . PHP_EOL;
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $rows
     */
    private static function table(array $rows): string
    {
        $nameWidth  = 0;
        $valueWidth = 0;

        foreach ($rows as [$name, $value]) {
            $nameWidth  = max($nameWidth, mb_strlen($name));
            $valueWidth = max($valueWidth, mb_strlen($value));
        }

        $table = sprintf(
            '  %s  %s  %s' . PHP_EOL,
            mb_str_pad('SETTING', $nameWidth),
            mb_str_pad('VALUE', $valueWidth),
            'SOURCE',
        );

        foreach ($rows as [$name, $value, $source]) {
            $table .= sprintf(
                '  %s  %s  %s' . PHP_EOL,
                mb_str_pad($name, $nameWidth),
                mb_str_pad($value, $valueWidth),
                $source,
            );
        }

        return $table;
    }

    /** The effective value of one setting, or null when it has none to show. */
    private static function value(Arguments $arguments, string $name): ?string
    {
        return match ($name) {
            'suffix'               => implode(', ', $arguments->suffixes()),
            'exclude'              => self::list($arguments->exclude()),
            'no-default-excludes'  => self::bool(!$arguments->defaultExcludes()),
            'orphans'              => self::bool($arguments->orphans()),
            'no-suppress'          => self::list($arguments->noSuppress()),
            'fail-on'              => self::list($arguments->failOn()),
            'explain'              => self::bool($arguments->explain()),
            'min-lines'            => (string) $arguments->linesThreshold(),
            'min-tokens'           => (string) $arguments->tokensThreshold(),
            'verbose'              => self::bool($arguments->verbose()),
            'algorithm'            => $arguments->algorithm() ?? 'rabin-karp + tokenbag',
            'fuzzy'                => self::bool($arguments->fuzzy()),
            'type-anchored'        => self::bool($arguments->typeAnchored()),
            'min-similarity'       => (string) $arguments->similarity(),
            'edit-distance'        => (string) $arguments->editDistance(),
            'head-equality'        => (string) $arguments->headEquality(),
            'log-pmd'              => $arguments->pmdCpdXmlLogfile() ?? '—',
            'log-json'             => $arguments->jsonLogfile() ?? '—',
            'log-sarif'            => $arguments->sarifLogfile() ?? '—',
            'cache-dir'            => $arguments->cacheDir() ?? '—',
            'incremental'          => self::bool($arguments->incremental()),
            default                => null,
        };
    }

    /** @param list<string> $values */
    private static function list(array $values): string
    {
        return $values === [] ? '—' : implode(', ', $values);
    }

    private static function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
