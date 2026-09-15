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

use function explode;
use function trim;

use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;

/**
 * Every setting a run resolves, in one declaration.
 *
 * The property initializers below are the built-in defaults — the ONLY copy of
 * them. Resolution is a fold: a preset seeds first (the lowest layer above these
 * defaults), then every `[option, value]` pair from config files and the command
 * line is applied in order by {@see apply()}, whose `match` has exactly one arm
 * per option and no default arm. The layering rule falls out of the fold order
 * instead of being implemented anywhere: a later scalar replaces, a repeatable
 * option appends, and whichever layer speaks last wins.
 *
 * This replaces a pipeline in which one option lived in five places — a parse
 * definition, a builder local carrying a second copy of the default, a switch
 * case, a constructor parameter carrying a third copy, and a getter — and in
 * which the headless facade re-implemented preset seeding by hand and then
 * forged a fake command line to feed the engine. Now the CLI, `phpcpd.ini`,
 * presets, and the embedding API all pass through this one fold, so they cannot
 * disagree; a new option is its {@see Options} definition plus one `match` arm,
 * and the arm is the documentation of what the option does to the run.
 *
 * Asymmetric visibility does the job the 28 getters used to do: every property
 * reads publicly and writes only in here. Consumers get `$settings->minLines`
 * with the same immutability guarantee, minus the ceremony — and minus the class
 * of bug where a getter and its constructor parameter drift apart.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class Settings
{
    // ── File selection ────────────────────────────────────────────────────

    /** @var list<non-empty-string> */
    public private(set) array $directories = [];

    /** @var list<non-empty-string> */
    public private(set) array $suffixes = ['.php'];

    /** @var list<non-empty-string> */
    public private(set) array $exclude = [];

    public private(set) bool $defaultExcludes = true;

    public private(set) bool $allowRootScan = false;

    /**
     * Run Stage 0 before detection? **On by default since 2.0.0**, in the `label`
     * posture — which is what makes the default safe to take.
     *
     * Turning triage on used to be a flip in its own right, because every posture
     * it had changed which files a scan reported on. `label` does not: it stamps
     * files and stops. The owner's flip test — a default may change what the tool
     * *says about* a finding but never which findings there are — is met by that
     * posture and by no other, which is why that posture is the default and the
     * other two stay opt-in. `--no-triage` skips the stage entirely.
     */
    public private(set) bool $triage = true;

    /**
     * What triage does with a file its rungs reject.
     *
     *  - `discard` — drop it from the scan. Every rung proves its case, so every
     *                rung may remove, and this is the posture that acts on the
     *                labels. The default.
     *  - `label`   — stamp it and nothing else. Zero files dropped, zero findings
     *                changed, zero effect on the exit code; `TriagePostureTest`
     *                asserts that identity against a `--no-triage` run rather
     *                than trusting it.
     *
     * The default is `discard`. The cost is what keeps `label` in the product: removing a file can only
     * ever *lose* a finding, so a rung that is wrong about one file takes real
     * duplication with it and nothing says so. That is why the stage prints what
     * it removed, why `--explain` names the evidence per file, and why the
     * posture that judges without acting is one flag away.
     *
     * A third value, `demote`, was retired at the M6 landing audit **before
     * 2.0.0 ever shipped**: after the classifier's retirement it kept the
     * identical file set to `label` — the same posture under two names — and a
     * never-released synonym owes no deprecation cycle, because the cycle
     * convention protects shipped names and this one never shipped. See the M6
     * packet, §1.4 and the landing audit's ruling on it.
     */
    public private(set) string $triagePosture = 'discard';

    /**
     * The language the report is written in.
     *
     * A project setting as much as a command-line one: a team whose reports are
     * read in one language should say so once in `phpcpd.ini` rather than on
     * every invocation. Defaults to the fallback the package always ships, so a
     * run that never mentions a language behaves exactly as it did before there
     * were any.
     */
    public private(set) string $language = Catalogue::FALLBACK;

    /** The preset in force, or null when none was requested. */
    public private(set) ?string $preset = null;

    /**
     * Was the preset in force chosen by detection rather than asked for? Only
     * its excludes are seeded in that case, and the run announces it: a default
     * that changes what is scanned has to say so.
     */
    public private(set) bool $presetDetected = false;

    /**
     * Did the scan paths come from the preset rather than the command line? A
     * preset's paths are a guess about the project's layout, so they are the
     * ones worth checking against the filesystem before a clean result is
     * believed.
     */
    public private(set) bool $directoriesFromPreset = false;

    // ── Thresholds and engine ─────────────────────────────────────────────

    public private(set) int $minLines = 5;

    public private(set) int $minTokens = 100;

    /** null = the combined default (Rabin-Karp + TokenBag). */
    public private(set) ?string $algorithm = null;

    /**
     * Identifier normalization, on by default and type-anchored with it.
     *
     * Raw matching finds a copy only where the names agree too, which is a
     * claim about spelling rather than about behaviour. Measured across three
     * corpora the normalized view finds three to five times as much for well
     * under a second: php-parser 8 to 41 (+0.37s), symfony-console 22 to 98
     * (+0.44s), phpunit 192 to 639 (+1.0s).
     *
     * Those counts are lower than the ones this comment first carried — 66, 128
     * and 805 — because normalization had been reporting periodic code as a
     * duplicate of itself. A run of near-identical members normalizes to one
     * repeating token sequence and then matches itself shifted by a period, and
     * a third of php-parser's normalized findings were that artifact. It is
     * clamped now, and the numbers above are what is left after it.
     *
     * Both raters of the precision audit put normalized Rabin-Karp above the
     * pre-registered bar — 0.850 and 0.950 asserted, where the unified engine
     * reaches 0.700 and 0.800 — but that pool was drawn before the clamp, so it
     * scored some findings that no longer exist. The audit's own agreement
     * statistic also failed its bar (kappa 0.484 against 0.7). Both are reasons
     * to re-pool rather than to read those figures as settled.
     *
     * `--raw` turns normalization off and restores exact-text matching, and
     * `--fuzzy` leaves it on while dropping the type anchor.
     *
     * ## Why type keywords are kept concrete
     *
     * The paper's E2 result: type-anchored normalization Pareto-dominates
     * name-blind fuzzing — equal recall at +10.9 to +45.3 specificity — which
     * is why name-blind is not the default it enables.
     *
     * Reproduced locally, and the shape of the reproduction is the point rather
     * than its size: on php-parser the anchor drops one finding and adds none,
     * and the finding it drops is false. It pairs `initializeRemovalMap()`
     * against `initializeInsertionMap()` — two different tables of `\T_*`
     * constants, which name-blind fuzzing conflates because every one of those
     * tokens normalizes alike. Keeping type keywords concrete is exactly what
     * tells the two tables apart. On the larger corpora the same anchor drops
     * 13 of symfony-console's 111 and 58 of phpunit's 697.
     *
     * ## One value, not two flags
     *
     * This was a `fuzzy`/`typeAnchored` pair: four combinations for three
     * behaviours, the fourth a silent duplicate, because normalization ran when
     * either was set while the normalizer read only the anchor. Measured on
     * three corpora, `--type-anchored` and `--raw --type-anchored` produced
     * byte-identical clone sets. {@see Normalization} records the rest.
     */
    public private(set) Normalization $normalization = Normalization::TypeAnchored;

    public private(set) float $minSimilarity = 0.7;

    /**
     * The log-odds below which a finding is listed only on request.
     *
     * Null, and hiding nothing, is the shipped posture and the only one the
     * project's own evidence licenses as a default: the M5 pre-commitment
     * measured that no rule derivable from its labels reaches the 0.80 bar by
     * silencing. That is a finding about what the *tool* may decide unasked. A
     * reader who sets a threshold has decided for their own report, and gets a
     * line saying how many findings it cost and `--hidden` to read them.
     */
    public private(set) ?float $minConfidence = null;

    /** List what `--min-confidence` held back, rather than only counting it. */
    public private(set) bool $hidden = false;

    // ── Orphan detection ──────────────────────────────────────────────────

    public private(set) bool $orphans = false;

    /** @var list<non-empty-string> */
    public private(set) array $noSuppress = [];

    /** @var list<non-empty-string> */
    public private(set) array $failOn = ['dead'];

    public private(set) bool $explain = false;

    // ── Reports and cache ─────────────────────────────────────────────────

    public private(set) ?string $pmdLog = null;

    public private(set) ?string $jsonLog = null;

    public private(set) ?string $sarifLog = null;

    /**
     * A committed acknowledgment ledger to read, or null.
     *
     * Reading one only ever *demotes*: an acknowledged finding is still
     * reported, still counted and still gates the exit code. That is what
     * separates this from a baseline in the usual sense, and it is why it needs
     * no companion flag to "show what was hidden" — nothing is.
     */
    public private(set) ?string $acknowledged = null;

    /** Where to write this run's findings as a ledger, or null. */
    public private(set) ?string $writeAcknowledged = null;

    public private(set) ?string $cacheDir = null;

    public private(set) bool $incremental = false;

    // ── Run-shaping flags ─────────────────────────────────────────────────

    public private(set) bool $verbose = false;

    public private(set) bool $help = false;

    public private(set) bool $version = false;

    public private(set) bool $showConfig = false;

    /** Resolution goes through {@see fromArgv()} or {@see resolve()}. */
    private function __construct() {}

    /**
     * The CLI entry point: parse argv, layer in config-file settings below it,
     * and resolve. The only rule that lives here rather than in the fold is the
     * CLI-specific one — a run with nothing to scan and nothing else to do is a
     * user error worth stopping.
     *
     * @param list<string> $argv
     * @throws SettingsException
     */
    public static function fromArgv(array $argv): self
    {
        $definitions = Options::definitions();
        $cli         = (new OptionParser())->parse($definitions, $argv);

        $directories = [];

        foreach ($cli['arguments'] as $directory) {
            if ($directory !== '') {
                $directories[] = $directory;
            }
        }

        // Config-file settings are folded as if they preceded argv, so an
        // explicit flag replaces a scalar the file set and a repeatable option
        // appends to what the file declared.
        $settings = self::resolve(
            [...ConfigFile::settings($cli['options'], $directories, $definitions), ...$cli['options']],
            $directories,
        );

        if ($settings->directories === [] && !$settings->help && !$settings->version && !$settings->showConfig) {
            throw new SettingsException((new Catalogue())->get('refuse.missingArgument.directory'));
        }

        return $settings;
    }

    /**
     * The fold itself, shared by the CLI and the headless facades: defaults,
     * then the preset (wherever the flag appeared — it is a layer, not a
     * position), then every pair in order.
     *
     * @param list<array{0: string, 1: ?string}> $options
     * @param list<non-empty-string>             $directories explicit scan paths; empty falls back to the preset's
     * @throws SettingsException
     */
    public static function resolve(array $options, array $directories = []): self
    {
        $settings = new self();
        $preset   = null;

        foreach ($options as [$name, $value]) {
            if ($name === 'preset' && $value !== null && $value !== '') {
                $preset = Presets::get($value)
                    ?? throw new SettingsException('Unknown preset: ' . $value);
            }
        }

        $suppressed = false;

        foreach ($options as [$name, $_value]) {
            if ($name === 'no-preset') {
                $suppressed = true;
            }
        }

        if ($preset !== null) {
            $settings->seed($preset);
        } elseif (!$suppressed) {
            // Detection is the lowest layer of all: it seeds only the preset's
            // excludes, never its scan paths. A preset asked for by name is the
            // user saying "scan this project the framework way"; detection has
            // only established what the project *is*, which justifies skipping
            // the framework's scratch trees and does not justify silently
            // narrowing the scan to four directories.
            $detected = PresetDetection::detect($directories);

            if ($detected !== null) {
                $settings->seedDetected($detected);
            }
        }

        foreach ($options as [$name, $value]) {
            $settings->apply($name, $value);
        }

        if ($directories !== []) {
            $settings->directories = $directories;
        } elseif ($preset !== null) {
            $settings->directories           = $preset->paths;
            $settings->directoriesFromPreset = true;
        }

        return $settings;
    }

    /**
     * The clone engine's slice of the settings, for handing to {@see Engine}
     * without giving it the whole run.
     */
    public function strategy(): StrategyConfiguration
    {
        return new StrategyConfiguration(
            minLines: $this->minLines,
            minTokens: $this->minTokens,
            normalization: $this->normalization,
            minSimilarity: $this->minSimilarity,
        );
    }

    /**
     * What one option does to the run — the whole of it, one arm per option.
     *
     * Deliberately no default arm: an option that gains a definition in
     * {@see Options} without gaining an arm here fails loudly on first use
     * instead of being silently swallowed, and the test suite folds every
     * defined option to keep that from ever reaching a user.
     */
    private function apply(string $name, ?string $value): void
    {
        match ($name) {
            'suffix'              => $this->suffixes = self::appended($this->suffixes, $value),
            'exclude'             => $this->exclude = self::appended($this->exclude, $value),
            'triage'              => $this->triage = true,
            'no-triage'           => $this->triage = false,
            // Naming a posture implies asking for the stage: nobody types
            // --triage-posture=discard meaning "and also do not run it".
            'triage-posture'      => $this->triagePosture = self::posture($value, $this->triage = true),
            'no-default-excludes' => $this->defaultExcludes = false,
            'allow-root-scan'     => $this->allowRootScan = true,
            'min-lines'           => $this->minLines = (int) $value,
            'min-tokens'          => $this->minTokens = (int) $value,
            'rk'                  => $this->algorithm = 'rabin-karp',
            'algorithm'           => $this->algorithm = (string) $value,
            'language'            => $this->language = (string) $value,
            // Name-blind: normalization without the type anchor. Dominated by
            // the default, and kept because E2 measured it rather than assumed.
            'fuzzy'               => $this->normalization = Normalization::Fuzzy,
            'raw'                 => $this->normalization = Normalization::Raw,
            'type-anchored'       => $this->normalization = Normalization::TypeAnchored,
            'min-similarity'      => $this->minSimilarity = (float) $value,
            'min-confidence'      => $this->minConfidence = (float) $value,
            'hidden'              => $this->hidden = true,
            'orphans'             => $this->orphans = true,
            'no-suppress'         => $this->noSuppress = [...$this->noSuppress, ...self::csv($value)],
            'fail-on'             => $this->failOn = self::csv($value),
            'explain'             => $this->explain = true,
            'acknowledged'        => $this->acknowledged = $value,
            'write-acknowledged'  => $this->writeAcknowledged = $value,
            'log-pmd'             => $this->pmdLog = $value,
            'log-json'            => $this->jsonLog = $value,
            'log-sarif'           => $this->sarifLog = $value,
            'cache'               => $this->cacheDir ??= '.phpcpd-cache',
            'cache-dir'           => $this->cacheDir = ($value === null || $value === '') ? $this->cacheDir : $value,
            'incremental'         => $this->incremental = true,
            'verbose'             => $this->verbose = true,
            'help'                => $this->help = true,
            'version'             => $this->version = true,
            'show-config'         => $this->showConfig = true,
            // Structural options — consumed before the fold, by design:
            // `preset` seeds in resolve(), `config`/`no-config` pick the files
            // whose settings are already part of the pair list being folded.
            'preset', 'no-preset', 'config', 'no-config' => null,
            // Not user-reachable: the parser rejects unknown options before the
            // fold ever sees them. This arm exists so an option added to
            // {@see Options} without an arm above fails loudly on first use
            // instead of being silently swallowed — and the test suite folds
            // every defined option so it fails in CI, not on a user.
            default => throw new SettingsException((new Catalogue())->get('refuse.unwired.option', ['flag' => '--' . $name])),
        };
    }

    /**
     * A preset is only configuration, seeded below every explicit layer: its
     * lists are the starting point later `--suffix`/`--exclude` append to, and
     * its thresholds hold only until any layer states its own.
     */
    private function seed(Preset $preset): void
    {
        $this->preset    = $preset->name;
        $this->suffixes  = $preset->suffixes;
        $this->exclude   = $preset->exclude;
        $this->minLines  = $preset->minLines ?? $this->minLines;
        $this->minTokens = $preset->minTokens ?? $this->minTokens;
    }

    /**
     * Detection's narrower seed: the framework's exclude list and nothing else.
     *
     * Suffixes, thresholds and scan paths stay at their defaults, so a detected
     * preset can only ever remove framework scratch trees from a scan the user
     * already asked for. Explicit `--exclude` still appends on top, because
     * this runs in the same slot the explicit seed does.
     */
    private function seedDetected(Preset $preset): void
    {
        $this->preset         = $preset->name;
        $this->presetDetected = true;
        $this->exclude        = $preset->exclude;
    }

    /**
     * The posture, validated here rather than trusted from the caller: the CLI
     * checks `allowedValues`, but a config file and the headless facade reach
     * {@see apply()} without passing through that check.
     *
     * @throws SettingsException
     */
    private static function posture(?string $value, bool $_enabled): string
    {
        return match ($value) {
            'label', 'discard' => $value,
            default            => throw new SettingsException(
                // The same sentence the parser uses. This path is only reached
                // through the library API — the command line and the config file
                // are both refused earlier by `allowedValues` — and two entry
                // points refusing the same thing in different words is the
                // inconsistency this catalogue exists to make visible.
                (new Catalogue())->get('refuse.invalidValue.option', [
                    'value'   => $value ?? '',
                    'flag'    => '--triage-posture',
                    'allowed' => 'label, discard',
                ]),
            ),
        };
    }

    /**
     * @param list<non-empty-string> $list
     * @return list<non-empty-string>
     */
    private static function appended(array $list, ?string $value): array
    {
        if ($value !== null && $value !== '') {
            $list[] = $value;
        }

        return $list;
    }

    /**
     * Split a comma-separated option value. The parser has already validated
     * each element against the option's allowed set.
     *
     * @return list<non-empty-string>
     */
    private static function csv(?string $value): array
    {
        $items = [];

        foreach (explode(',', (string) $value) as $item) {
            $item = trim($item);

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }
}
