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

use function count;
use function file_put_contents;
use function defined;
use function fwrite;
use function in_array;
use function printf;
use function sort;

use const PHP_EOL;
use const STDERR;

use LucianoPereira\PhpcpdNext\Cache\CloneCache;
use LucianoPereira\PhpcpdNext\Cache\IncrementalIndex;
use LucianoPereira\PhpcpdNext\Console\Ink;
use LucianoPereira\PhpcpdNext\Console\Progress;
use LucianoPereira\PhpcpdNext\Console\Style;
use LucianoPereira\PhpcpdNext\Console\Terminal;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Log\Json;
use LucianoPereira\PhpcpdNext\Log\Logger;
use LucianoPereira\PhpcpdNext\Log\PMD;
use LucianoPereira\PhpcpdNext\Log\Sarif;
use LucianoPereira\PhpcpdNext\Log\Text;
use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;
use LucianoPereira\PhpcpdNext\Orphan\OrphanConfiguration;
use LucianoPereira\PhpcpdNext\Orphan\OrphanDetector;
use LucianoPereira\PhpcpdNext\Orphan\OrphanTextReport;
use LucianoPereira\PhpcpdNext\Orphan\ProjectContext;
use LucianoPereira\PhpcpdNext\Presentation\AcknowledgmentLedger;
use LucianoPereira\PhpcpdNext\Presentation\Presenter;
use LucianoPereira\PhpcpdNext\Triage\Stage0;
use LucianoPereira\PhpcpdNext\Triage\TriageDecision;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use LucianoPereira\PhpcpdNext\Util\ResourceUsageFormatter;
use LucianoPereira\PhpcpdNext\Util\Timer;

/**
 * The CLI entry point, invoked from the `phpcpd` binary.
 *
 * A run is four phases, in this order for a reason:
 *
 *   1. RESOLVE  — settings from argv, config files and presets. Failures here are
 *                 user error, reported before anything is read from disk.
 *   2. SCOPE    — refuse a runaway root, warn about preset paths that do not
 *                 exist, then find the files and state what was actually scanned.
 *   3. DETECT   — clones, or orphans under `--orphans`.
 *   4. REPORT   — text, log files, and the exit code CI gates on.
 *
 * Scope precedes detection because every wrong answer this tool has produced was
 * a scope failure that read as a result: a cache directory counted as source, a
 * preset that matched 2% of a project, a scan too narrow to judge what it named.
 * The phase that can invalidate everything downstream therefore runs first and
 * says what it did.
 *
 * @api
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class Application
{
    private const string VERSION      = Version::NUMBER;
    private const string AUTHOR       = 'Luciano Federico Pereira';
    private const string ORIGIN       = 'phpcpd 7.0-dev';
    private const string ORIGIN_AUTHOR = 'Sebastian Bergmann';

    /**
     * Stdout and stderr are redirected independently, so each is asked about
     * itself: the report is coloured when the report is going to a terminal,
     * and a diagnostic is coloured when the diagnostic is.
     */
    private readonly Terminal $terminal;
    private readonly Ink $ink;
    private readonly Ink $errorInk;
    private readonly ?Progress $progress;

    /** Overrides the process language when a caller pins one. Null on a real run. */
    private readonly ?Catalogue $injectedStrings;

    public function __construct(?Terminal $terminal = null, ?Catalogue $strings = null)
    {
        $errors = defined('STDERR') ? STDERR : null;

        $this->terminal = $terminal ?? Terminal::detect();
        $this->ink      = Ink::of($this->terminal);
        $this->errorInk = Ink::of(Terminal::detect($errors));
        $this->progress = Progress::on($errors);
        $this->injectedStrings = $strings;
    }

    /**
     * The progress callback, or none when nothing is watching.
     *
     * `Progress::on()` already returned null for a stderr that is not a
     * terminal, so this is null on every redirected run — and a null callback is
     * what keeps the per-file cost of an unwatched scan at one comparison.
     *
     * @return ?callable(string, int, int): void
     */
    private function watcher(): ?callable
    {
        $progress = $this->progress;

        if ($progress === null) {
            return null;
        }

        return static function (string $phase, int $done, int $total) use ($progress): void {
            $progress->file($phase, $done, $total);
        };
    }

    /**
     * A diagnostic, on the stream diagnostics belong on.
     *
     * Everything this class printed went to stdout, errors included, so
     * `phpcpd src > report.txt` wrote its failures into the report and
     * `phpcpd src | grep` piped them through. Nothing downstream could tell a
     * finding from a refusal.
     *
     * The line is drawn where the exit code draws it: what accompanies a
     * non-zero exit and is not the report is a diagnostic. The banner, the scan
     * line and the findings stay on stdout, because in the text format they are
     * the output.
     */
    private function diagnostic(string $message): void
    {
        fwrite(STDERR, $this->errorInk->paint(Style::Problem, $this->strings()->error($message)) . PHP_EOL);
    }

    /**
     * Built per call, not once: the language is not known when this object is
     * constructed, and the banner prints before the command line is read.
     */
    private function strings(): Catalogue
    {
        return $this->injectedStrings ?? new Catalogue();
    }

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        // Provisional, so the banner and any refusal the parse itself raises
        // come out in the right language. Replaced below once the parse settles.
        Catalogue::useLanguage(LanguagePreference::fromArgv($argv));

        $this->printBanner();

        try {
            $settings = Settings::fromArgv($argv);
        } catch (Exception $e) {
            $this->diagnostic($e->getMessage());

            return 1;
        }

        Catalogue::useLanguage($settings->language);

        // --version has printed its answer in the banner already.
        if ($settings->version) {
            return 0;
        }

        print PHP_EOL;

        if ($settings->showConfig) {
            print ConfigReport::render($argv, $settings, $this->terminal);

            return 0;
        }

        if ($settings->help) {
            print Options::help($this->terminal);

            return 0;
        }

        return $this->scan($settings);
    }

    /**
     * Phases 2 to 4. Split from {@see run()} so the modes that answer without
     * scanning anything cannot accidentally acquire scanning concerns.
     */
    private function scan(Settings $settings): int
    {
        // A runaway root is refused rather than walked, and a preset that
        // resolves to almost nothing says so rather than reporting a clean
        // result over a fraction of the source.
        $refusal = ScanScope::refusal($settings);

        if ($refusal !== null) {
            $this->diagnostic($refusal);

            return 1;
        }

        // Never silent: a default that changes what is scanned announces itself,
        // and names the flag that turns it off.
        if ($settings->presetDetected) {
            print $this->strings()->get('notice.preset.detected', [
                'preset' => PresetDetection::label($settings->preset),
            ]) . PHP_EOL . PHP_EOL;
        }

        $presetWarning = ScanScope::presetWarning($settings);

        if ($presetWarning !== null) {
            print $this->strings()->warning($presetWarning) . PHP_EOL . PHP_EOL;
        }

        $finder = new FileFinder();
        $files  = $finder->find(
            $settings->directories,
            $settings->suffixes,
            $settings->exclude,
            $settings->defaultExcludes,
        );

        if ($files === []) {
            $this->diagnostic($this->strings()->get('refuse.nothingToScan.files'));

            return 1;
        }

        // Entry points are resolved once, for both modes: the advisory orphan
        // report in a default run must see the same file set --orphans does, or
        // the two modes disagree about whether a symbol is reachable.
        $orphanConfig  = new OrphanConfiguration(
            $settings->directories,
            $settings->noSuppress,
            $settings->failOn,
            $settings->explain,
        );
        $orphanContext = ProjectContext::discover($settings->directories, $settings->exclude, $orphanConfig);
        $files         = $this->withManifestEntryPoints($files, $orphanContext);

        // `--orphans` reports unwired files itself, in more detail than a label
        // and with the evidence for each one, so running Stage 0 alongside it
        // would run the orphan detector a second time to print a fact the report
        // already carries.
        //
        // Under the discarding posture it would do something worse than
        // redundant. Stage 0's first rung *is* the orphan machinery, so
        // discarding would remove precisely the files `--orphans` was asked
        // about, and the report would come back empty on a tree full of them.
        // The stage is therefore skipped in this mode whichever posture is in
        // force: the request is "tell me what is unwired", and pre-filtering the
        // input by the answer is not a way to give it.
        // Triage narrows what is reviewed for duplication. It must not narrow
        // what is read for references: a discarded file still calls what it
        // calls, and its references still count.
        $referenced = $files;

        if ($settings->triage && !$settings->orphans) {
            $files = $this->triaged($files, $settings, $finder, $orphanConfig, $orphanContext);

            if ($files === []) {
                $this->diagnostic($this->strings()->get('refuse.nothingToScan.afterTriage'));

                return 1;
            }
        }

        $timer = new Timer();
        $timer->start();

        print ScanScope::render(
            $settings,
            count($files),
            $finder->skippedDirectoryCount(),
            $finder->skippedGeneratedCount(),
        );

        $exit = $settings->orphans
            ? $this->reportOrphans($files, $settings, $orphanConfig, $orphanContext)
            : $this->reportClones($files, $referenced, $settings, $orphanConfig, $orphanContext);

        print (new ResourceUsageFormatter())->format($timer->seconds(), count($files)) . PHP_EOL;

        return $exit;
    }

    /**
     * Stage 0, applied to a run (ruling T, wired by ruling (a), default-on since
     * 2.0.0 in the `label` posture).
     *
     * Never silent, and that requirement now cuts both ways. A file that vanishes
     * from a scan without saying why is the failure mode this stage would
     * otherwise introduce — so a removal always prints its reason. And a *default*
     * that runs without being asked has to announce itself and name the flag that
     * turns it off, exactly as preset detection does, which is what the `label`
     * posture's line is for.
     *
     * `label` is the posture that makes the default safe: it stamps and stops.
     * Zero files dropped, zero findings changed, zero effect on the exit code —
     * asserted against a `--no-triage` run by `TriagePostureTest` rather than
     * argued for here.
     *
     * @param  list<string> $files
     * @return list<string> the files that survive
     */
    private function triaged(
        array $files,
        Settings $settings,
        FileFinder $finder,
        OrphanConfiguration $orphanConfig,
        ProjectContext $orphanContext,
    ): array {
        // Ruling V: only program text may witness that another file is wired.
        // In an ordinary run the walk already applied the default excludes, so
        // the file list *is* the witness set. Under --no-default-excludes it is
        // not, and passing it unchanged would reintroduce exactly the cache-state
        // dependence ruling V removed — so the witness set is walked separately.
        $witnesses = $settings->defaultExcludes
            ? $files
            : $finder->find($settings->directories, $settings->suffixes, $settings->exclude, true);

        $result = (new Stage0())->triage(
            $files,
            ComposerManifest::locate($settings->directories),
            $orphanContext,
            $orphanConfig,
            witnesses: $witnesses,
        );

        // Every rung proves its case, so every rung may remove — but only the
        // posture that was asked for acts on that. `discard` removes; `label` and
        // `label` keeps the file and says what was decided about it.
        $removed  = $settings->triagePosture === 'discard' ? $result->discarded : [];
        $labelled = $settings->triagePosture === 'discard' ? [] : $result->discarded;

        if ($result->discarded === []) {
            print $this->strings()->get('report.triage.nothing') . PHP_EOL . PHP_EOL;

            return $files;
        }

        if ($removed !== []) {
            print $this->strings()->get('report.triage.removed', ['removed' => count($removed), 'total' => count($files)]);
            $this->printReasonCounts($removed);
        }

        if ($labelled !== []) {
            print $this->strings()->get('report.triage.labelled', ['labelled' => count($labelled), 'total' => count($files)]);
            $this->printReasonCounts($labelled);
            print $this->strings()->get('advise.triage.posture') . PHP_EOL;
        }

        // The reasons themselves, one file per line, behind the same flag that
        // makes every other part of this tool show its working. Every rung names
        // the evidence a reader can check — the wired file for a shadow, the
        // unclaimed namespaces for a foreign file — so a removal can be argued
        // with rather than merely obeyed.
        if ($settings->explain) {
            foreach ($result->discarded as $decision) {
                printf('  %-8s %s — %s' . PHP_EOL, $decision->reason, $decision->file, $decision->detail);
            }
        } else {
            print $this->strings()->get('advise.triage.explain') . PHP_EOL;
        }

        print PHP_EOL;

        if ($removed === []) {
            return $files;
        }

        $gone = [];

        foreach ($removed as $decision) {
            $gone[$decision->file] = true;
        }

        $kept = [];

        foreach ($files as $file) {
            if (!isset($gone[$file])) {
                $kept[] = $file;
            }
        }

        return $kept;
    }

    /**
     * The one-line breakdown of a set of triage decisions by the rung that made
     * them, so a count is never reported without saying what produced it.
     *
     * @param list<TriageDecision> $decisions
     */
    private function printReasonCounts(array $decisions): void
    {
        $counts = [];

        foreach ($decisions as $decision) {
            $counts[$decision->reason] = ($counts[$decision->reason] ?? 0) + 1;
        }

        foreach ($counts as $reason => $count) {
            printf(' · %s %d', $reason, $count);
        }

        print PHP_EOL;
    }

    /**
     * The default mode. Orphans ride along as an advisory — reported, reusing
     * the clone map just computed to flag superseded copies, but only clones
     * gate the exit code.
     *
     * @param list<string> $files      reviewed for duplication, after triage
     * @param list<string> $referenced read for references, before triage — the
     * same set `--orphans` sees
     */
    private function reportClones(
        array $files,
        array $referenced,
        Settings $settings,
        OrphanConfiguration $orphanConfig,
        ProjectContext $context,
    ): int {
        try {
            $clones = $this->detectClones($files, $settings);
        } catch (InvalidStrategyException $e) {
            $this->diagnostic($e->getMessage());

            return 1;
        }

        // One presentation, shared by every format: the console and the log files
        // must not be able to disagree about what the tool asserts.
        $findings = (new Presenter(
            ledger: $settings->acknowledged === null
                ? null
                : AcknowledgmentLedger::load($settings->acknowledged),
            minConfidence: $settings->minConfidence,
        ))->present($clones);

        if ($settings->writeAcknowledged !== null) {
            file_put_contents($settings->writeAcknowledged, AcknowledgmentLedger::render($findings));

            print $this->strings()->get('report.ledger.wrote', [
                'count' => $findings->count(),
                'path'  => $settings->writeAcknowledged,
            ]) . PHP_EOL . PHP_EOL;
        }

        (new Text($this->ink))->printResult($findings, $settings->verbose, $settings->hidden);

        // A report that could not be written is a failed run, whatever the
        // findings were. The console report is already on screen by now, so the
        // findings are not lost — but a pipeline that asked for `--log-sarif`
        // and got no file has not had its question answered, and the one answer
        // it must never get is a silent success.
        foreach ($this->fileLoggers($settings) as $logger) {
            try {
                $logger->process($findings);
            } catch (LogWriteException $e) {
                $this->diagnostic($e->getMessage());

                return 1;
            }
        }

        (new OrphanTextReport())->printAdvisory(
            (new OrphanDetector())->detect($referenced, $clones, $orphanConfig, $context),
        );

        return count($clones) > 0 ? 1 : 0;
    }

    /**
     * `--orphans`: report unreferenced symbols instead of clones, and gate on
     * them — a definite orphan yields a non-zero exit so CI can fail on it,
     * exactly like a clone. A Rabin–Karp pass over the same files feeds the
     * "superseded copy of ..." annotation.
     *
     * @param list<string> $files
     */
    private function reportOrphans(
        array $files,
        Settings $settings,
        OrphanConfiguration $orphanConfig,
        ProjectContext $context,
    ): int {
        // An orphan verdict is only as sound as the scan's coverage, so a scan
        // that cannot see the whole project says so before it names any symbol.
        $subtreeWarning = ScanScope::subtreeWarning($context);

        if ($subtreeWarning !== null) {
            print $subtreeWarning . PHP_EOL . PHP_EOL;
        }

        $clones = (new Engine($settings->strategy(), 'rabin-karp'))->detect($files);
        $result = (new OrphanDetector())->detect($files, $clones, $orphanConfig, $context);

        (new OrphanTextReport())->printResult($result, $orphanConfig);

        return $result->fails($orphanConfig) ? 1 : 0;
    }

    /**
     * Pick how the clone pass is computed. Three routes, narrowest first:
     *
     *   - the per-file incremental index, which caches a file's encoding (and,
     *     for the unified engine, its fingerprints) and so applies only when one
     *     of those two algorithms was explicitly selected;
     *   - the default combined pass (Rabin–Karp for exact clones, TokenBag for
     *     reordered ones, merged), which has no incremental form;
     *   - a single named algorithm, optionally served from the coarse cache.
     *
     * Each route announces itself when it does something the user did not ask
     * for — a cache hit, or an `--incremental` that could not be honoured.
     *
     * @param list<string> $files
     * @throws InvalidStrategyException
     */
    private function detectClones(array $files, Settings $settings): CodeCloneMap
    {
        $config = $settings->strategy();

        // Deprecated under ruling 6, and deliberately *not* removed. The unified
        // engine's order-free channel beats the token bag on the capability the
        // bag exists for (permutation recall 89.1 % / 89.7 % against 77.5 % /
        // 81.4 %), but the merged-default gate still finds locations the bag
        // reports, an independent bijective recompute supports, and this engine
        // does not cover. A class of real findings would die with a removal now,
        // so it stays selectable and its removal waits on a ruled successor
        // rather than on this engine's own opinion of itself. The notice states
        // the gap rather than only the recommendation, because a user whose
        // pipeline depends on that class needs to know it is still uncovered.
        if ($settings->algorithm === 'tokenbag') {
            print $this->strings()->get('notice.engine.tokenbagDeprecated') . PHP_EOL;
        }

        // No `--algorithm` is the combined default, Rabin-Karp and the token
        // bag merged.
        $combined = $settings->algorithm === null;

        if (!$combined && $settings->incremental && in_array($settings->algorithm, ['rabin-karp', 'unified'], true)) {
            // Its own index, its own cache; the clone cache below would be a
            // second one over the same answer.
            return $this->detectIncrementally($files, $settings, $config);
        }

        if ($settings->incremental) {
            print $this->strings()->get($combined
                ? 'notice.incremental.combined'
                : 'notice.incremental.unsupported') . PHP_EOL;
        }

        // The cache covers every pipeline, the default one included.
        //
        // It used to sit below an early return taken whenever no `--algorithm`
        // was given, which is the default invocation — so `--cache` wrote
        // nothing and hit nothing for everyone who did not name an engine, and
        // said nothing about it either. The fingerprint already keys on the
        // algorithm, so the default's entry cannot be confused with a
        // single-engine one.
        $cache  = $settings->cacheDir !== null
            ? new CloneCache($settings->cacheDir, CloneCache::configFingerprint($settings))
            : null;
        $cached = $cache?->get($files);

        if ($cached !== null) {
            print $this->strings()->get('notice.cache.hit') . PHP_EOL;

            return $cached;
        }

        $clones = $this->watched(
            static fn(?callable $watcher): CodeCloneMap => (new Engine($config, $settings->algorithm))->detect($files, $watcher),
        );

        $cache?->put($files, $clones);

        return $clones;
    }

    /**
     * Runs a detection with the bar up, and takes the bar down afterwards —
     * including when the detection throws, because a bar left on the screen
     * under an error message is the last thing a failing run should print.
     *
     * @param callable(?callable): CodeCloneMap $detection
     * @throws InvalidStrategyException
     */
    private function watched(callable $detection): CodeCloneMap
    {
        try {
            return $detection($this->watcher());
        } finally {
            $this->progress?->done();
        }
    }

    /** @param list<string> $files */
    private function detectIncrementally(array $files, Settings $settings, StrategyConfiguration $config): CodeCloneMap
    {
        $index = new IncrementalIndex(
            $settings->cacheDir ?? '.phpcpd-cache',
            CloneCache::configFingerprint($settings),
            $config,
            $settings->algorithm ?? 'rabin-karp',
        );

        $result = $index->detect($files);

        print $this->strings()->get('notice.incremental.index', ['reused' => $result->reused, 'scanned' => $result->scanned]) . PHP_EOL;

        return $result->clones;
    }

    /**
     * Console entry points declared in composer's `bin` are conventionally
     * extensionless, so a `--suffix .php` scan never opens the one file where an
     * application wires its top level together.
     *
     * Membership is decided on the resolved path. The two sources spell the
     * same file differently — the finder as the scan root spells it, the
     * manifest as an absolute path — so comparing strings admits both and the
     * detector matches the file against itself.
     *
     * @param list<string> $files
     * @return list<string>
     */
    private function withManifestEntryPoints(array $files, ProjectContext $context): array
    {
        if (!$context->manifestApplies || !$context->manifest instanceof ComposerManifest) {
            return $files;
        }

        $seen = [];

        foreach ($files as $file) {
            $seen[self::identity($file)] = true;
        }

        foreach ($context->manifest->binFiles as $bin) {
            $identity = self::identity($bin);

            if (isset($seen[$identity])) {
                continue;
            }

            $seen[$identity] = true;
            $files[]         = $bin;
        }

        sort($files);

        return $files;
    }

    /** What makes two paths the same file; the string, when one will not resolve. */
    private static function identity(string $path): string
    {
        $resolved = realpath($path);

        return $resolved === false ? $path : $resolved;
    }

    /** @return list<Logger> */
    private function fileLoggers(Settings $settings): array
    {
        $loggers = [];

        if ($settings->pmdLog !== null) {
            $loggers[] = new PMD($settings->pmdLog);
        }

        if ($settings->jsonLog !== null) {
            $loggers[] = new Json($settings->jsonLog);
        }

        if ($settings->sarifLog !== null) {
            $loggers[] = new Sarif($settings->sarifLog);
        }

        return $loggers;
    }

    /**
     * The banner credits the idea, which is what is owed.
     *
     * It used to say "based on", which described the code and stopped being
     * true: no file here is inherited any longer, and
     * `bench/check-provenance.php` is the gate that keeps it that way. "After"
     * is the older and more exact word for a work made in another's manner —
     * it credits without claiming descent.
     */
    private function printBanner(): void
    {
        // The names are not translated: a person and a package are called what
        // they are called in every language.
        print $this->strings()->get('report.run.banner', [
            'version'      => self::VERSION,
            'author'       => self::AUTHOR,
            'origin'       => self::ORIGIN,
            'originAuthor' => self::ORIGIN_AUTHOR,
        ]) . PHP_EOL;
    }
}
