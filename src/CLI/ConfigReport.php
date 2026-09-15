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
use function is_dir;
use function sprintf;

use const PHP_EOL;

use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Console\Table;
use LucianoPereira\PhpcpdNext\Console\Terminal;

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
 */use LucianoPereira\PhpcpdNext\Strings\Catalogue;

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
        'rk', 'cache', 'triage', 'no-preset',
    ];

    /**
     * @param list<string> $argv
     * @throws SettingsException
     */
    public static function render(array $argv, Settings $settings, ?Terminal $terminal = null): string
    {
        $definitions = Options::definitions();
        $cli         = (new OptionParser())->parse($definitions, $argv);
        $sources     = self::sources($cli, $definitions, $settings);

        // Scan paths lead the table. For a preset they are the single most
        // important resolved value — the one a user most needs to check before
        // trusting a result — and they were the one value the report never
        // printed, so `--preset=laravel` could only be understood by running it
        // and reading the file count.
        $rows = [self::pathsRow($settings)];

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

            $value = self::value($settings, $definition->name);

            if ($value === null) {
                continue;
            }

            $rows[] = [$definition->name, $value, $source ?? self::FALLBACK . ' ' . (new Catalogue())->get('report.config.source.default')];
        }

        $table = new Table(['SETTING', 'VALUE', 'SOURCE'], $rows);

        return self::layerList($cli, $settings) . PHP_EOL
            . $table->render($terminal ?? Terminal::detect()) . PHP_EOL
            . '  ' . self::FALLBACK . (new Catalogue())->get('report.config.fallback') . PHP_EOL;
    }

    /**
     * The resolved scan paths, each annotated when it does not exist. A preset
     * declares a conventional layout; a project that does not follow it gets a
     * scan of whatever happened to match, and this row is where that shows up
     * without running anything.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private static function pathsRow(Settings $settings): array
    {
        $paths = [];

        foreach ($settings->directories as $directory) {
            $paths[] = is_dir($directory) ? $directory : (new Catalogue())->get('report.config.missingPath', ['path' => $directory]);
        }

        $source = match (true) {
            $paths === []                          => self::FALLBACK . ' ' . (new Catalogue())->get('report.config.source.default'),
            $settings->directoriesFromPreset    => 'preset:' . ($settings->preset ?? '?'),
            default                                => (new Catalogue())->get('report.config.source.commandLine'),
        };

        return ['paths', self::list($paths), $source];
    }

    /**
     * The preset as a configuration layer, or null when none is active.
     *
     * A preset seeds `suffix`, `exclude` and the thresholds before any other
     * option is read, so it genuinely sits directly above the built-in defaults —
     * including when `--preset` itself was declared in a config file. Without
     * this layer its values were attributed to `default`, which is false:
     * `*.blade.php` and `database/migrations` are not in the base default set,
     * and a report that names the wrong layer is worse than one that names none.
     *
     * @return array{0: string, 1: list<array{0: string, 1: ?string}>}|null
     */
    private static function presetLayer(Settings $settings): ?array
    {
        $name   = $settings->preset;
        $preset = $name === null ? null : Presets::get($name);

        if ($name === null || $preset === null) {
            return null;
        }

        $options = [];

        foreach ($preset->suffixes as $suffix) {
            $options[] = ['suffix', $suffix];
        }

        foreach ($preset->exclude as $exclude) {
            $options[] = ['exclude', $exclude];
        }

        if ($preset->minLines !== null) {
            $options[] = ['min-lines', (string) $preset->minLines];
        }

        if ($preset->minTokens !== null) {
            $options[] = ['min-tokens', (string) $preset->minTokens];
        }

        return ['preset:' . $name, $options];
    }

    /**
     * Options that write settings they are not named after.
     *
     * `--fuzzy` selects name-blind normalization, which means turning the type
     * anchor off; `--raw` turns both halves off. Only the option's own name is
     * parsed from the command line, so without this the table shows `fuzzy
     * true` and says nothing about the anchor that went with it, and a reader
     * cannot explain why the run found what it found.
     *
     * It began to matter when the anchor became a default. Before that,
     * `--fuzzy` left `type-anchored` where it already was and there was nothing
     * to report.
     *
     * @var array<string, list<string>>
     */
    private const array ALSO_WRITES = [
        'fuzzy' => ['type-anchored'],
        'raw'   => ['fuzzy', 'type-anchored'],
    ];

    /**
     * Which layer last set each option. Later layers override earlier ones, and
     * a repeatable option names every layer that contributed, because those
     * append rather than replace.
     *
     * @param array{options: list<array{0: string, 1: ?string}>, arguments: list<string>} $cli
     * @param list<OptionDefinition>                                                      $definitions
     * @return array<string, string>
     */

    private static function sources(array $cli, array $definitions, Settings $settings): array
    {
        $repeatable = [];

        foreach ($definitions as $definition) {
            $repeatable[$definition->name] = $definition->repeatable;
        }

        $sources = [];
        $last    = [];

        foreach (self::layers($cli, $settings) as [$label, $options]) {
            foreach ($options as [$name]) {
                // A repeatable option names every layer that contributed, but only
                // once per layer: nine excludes from one preset are one source, not
                // "preset:laravel + preset:laravel + ..." nine times over.
                if (($last[$name] ?? null) === $label) {
                    continue;
                }

                $sources[$name] = ($repeatable[$name] ?? false) && isset($sources[$name])
                    ? $sources[$name] . ' + ' . $label
                    : $label;

                $last[$name] = $label;

                // An option that writes settings it is not named after has to
                // answer for them, or the table cannot explain the run.
                foreach (self::ALSO_WRITES[$name] ?? [] as $written) {
                    $sources[$written] = $label;
                    $last[$written]    = $label;
                }
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
    private static function layers(array $cli, Settings $settings): array
    {
        $definitions = Options::definitions();
        $layers      = [];
        $preset      = self::presetLayer($settings);

        if ($preset !== null) {
            $layers[] = $preset;
        }

        foreach (self::files($cli) as $label => $file) {
            $layers[] = [$label, ConfigFile::read($file, $definitions)];
        }

        $layers[] = [(new Catalogue())->get('report.config.source.commandLine'), $cli['options']];

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
    private static function layerList(array $cli, Settings $settings): string
    {
        $lines = (new Catalogue())->get('report.config.layers') . PHP_EOL
            . (new Catalogue())->get('report.config.source.builtIn') . PHP_EOL;

        $preset = self::presetLayer($settings);

        if ($preset !== null) {
            $lines .= '    ' . $preset[0] . PHP_EOL;
        }

        foreach (self::files($cli) as $label => $file) {
            $lines .= sprintf('    %-14s %s' . PHP_EOL, $label, $file);
        }

        return $lines . '    ' . (new Catalogue())->get('report.config.source.commandLine') . PHP_EOL;
    }

    /** The effective value of one setting, or null when it has none to show. */
    private static function value(Settings $settings, string $name): ?string
    {
        return match ($name) {
            'suffix'               => implode(', ', $settings->suffixes),
            'exclude'              => self::list($settings->exclude),
            'no-default-excludes'  => self::bool(!$settings->defaultExcludes),
            'no-triage'            => self::bool(!$settings->triage),
            'triage-posture'       => $settings->triage ? $settings->triagePosture : '—',
            'orphans'              => self::bool($settings->orphans),
            'no-suppress'          => self::list($settings->noSuppress),
            'fail-on'              => self::list($settings->failOn),
            'explain'              => self::bool($settings->explain),
            'min-lines'            => (string) $settings->minLines,
            'min-tokens'           => (string) $settings->minTokens,
            'verbose'              => self::bool($settings->verbose),
            'algorithm'            => $settings->algorithm ?? 'rabin-karp + tokenbag',
            // Both halves of the normalization switch, because `--raw` turns
            // them off together and a reader checking why a scan found nothing
            // should see which one is off.
            // One decision, reported under each of the three names that set it,
            // so a reader checking why a scan found nothing sees which view is
            // in force without having to know which flag wins.
            'raw'                  => self::bool($settings->normalization === Normalization::Raw),
            'fuzzy'                => self::bool($settings->normalization === Normalization::Fuzzy),
            'type-anchored'        => self::bool($settings->normalization === Normalization::TypeAnchored),
            'min-similarity'       => (string) $settings->minSimilarity,
            'min-confidence'       => $settings->minConfidence === null
                                        ? '—'
                                        : sprintf('%+.2f', $settings->minConfidence),
            'hidden'               => self::bool($settings->hidden),
            'log-pmd'              => $settings->pmdLog ?? '—',
            'log-json'             => $settings->jsonLog ?? '—',
            'log-sarif'            => $settings->sarifLog ?? '—',
            'cache-dir'            => $settings->cacheDir ?? '—',
            'incremental'          => self::bool($settings->incremental),
            'language'             => $settings->language,
            'preset'               => $settings->preset ?? '—',
            'allow-root-scan'      => self::bool($settings->allowRootScan),
            'acknowledged'         => $settings->acknowledged ?? '—',
            'write-acknowledged'   => $settings->writeAcknowledged ?? '—',
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
