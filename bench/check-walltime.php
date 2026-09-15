#!/usr/bin/env php
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

/*
 * Wall-clock comparison: the unified engine against the pipeline it replaces.
 *
 * The default pipeline is Rabin-Karp and TokenBag run over the same corpus and
 * merged, and it is the bar the unified engine has to clear — replacing three
 * engines with one is not worth doing if the one is slower than the two.
 *
 * Everything here goes through the self-tested harness: durations are in-process
 * monotonic deltas, every configuration is run at least five times, and the
 * report is a median and a spread rather than a single number. A single run of a
 * PHP process on a laptop is not a measurement.
 *
 * The **presentation tier** is measured as its own configuration rather than
 * folded into the engine's: `unified` is the detection cost the M3 and M4 sweeps
 * recorded and stays comparable with them, and `unified + presentation` is what a
 * user's run actually pays now that findings are stratified and ranked. Two
 * lines, one corpus — the posture-relative rule applied to a cost rather than to
 * a coverage number.
 *
 * **Stage 0 is measured the same way, and for the same reason.** From 2.0.0 the
 * shipped default runs corpus triage in the labelling posture before it detects
 * anything, so a sweep that measured only engines would be reporting a
 * configuration nobody runs. `triage (label)` is that stage on its own, over the
 * same file set: add it to any engine line to get what a run of that engine
 * actually costs today. It is a line rather than an addend inside `default`
 * because the M3 and M4 ratios were taken without it, and silently changing what
 * `default` means would break every comparison against them.
 *
 * Usage:
 *   php bench/check-walltime.php                      every pinned corpus
 *   php bench/check-walltime.php <dir> ...            those corpora only
 *   ... [--runs=5] [--min-tokens=100] [--force]
 *
 * Either way each corpus's row is written as soon as that corpus is measured,
 * and merged into the table rather than replacing it. A sweep interrupted in
 * its sixth corpus keeps the five it finished. A corpus whose engine and file
 * list are both unchanged since its row was taken is skipped; `--force`
 * measures anyway, and records even when the machine's state is against it.
 *
 * The machine is read before the run, and again after each corpus. A row is
 * kept only if the conditions held across the corpus it describes — which is
 * the granularity that matters, since what corrupts a timing is the machine
 * changing during it.
 *
 * Refuses to measure on a machine whose power state makes the number
 * meaningless (battery, a power-biased EPP, a capped clock, a busy CPU) and
 * exits 0 having asserted nothing. --force overrides, and says so in the
 * output. See bench/power.php for what is read and why.
 *
 * Exit 0 only if the unified engine's median is at or below the default
 * pipeline's on every directory given.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/power.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;
use LucianoPereira\PhpcpdNext\Presentation\Presenter;
use LucianoPereira\PhpcpdNext\Triage\Stage0;

$dirs      = [];
/**
 * What a row was measured against: the engine, and the corpus.
 *
 * A timing is a claim about two things — the code that ran and the files it ran
 * over — so a row stays true for exactly as long as both are unchanged. Nothing
 * recorded that, so every re-measure was every corpus, whether or not anything
 * about it had moved. On a machine that throttles inside a quarter of an hour
 * that is not a small waste: it is the difference between a measurement that
 * finishes and one that has to be thrown away.
 *
 * Timing cannot be cached — a cached run measures the cache. What can be skipped
 * is a corpus whose answer is already known to be current, which is a different
 * thing and a safe one: the row is re-used only when nothing it depends on has
 * changed at all.
 */
function wt_fingerprint(string $dir): string
{
    // Only the code a timing can depend on.
    //
    // This harness calls Stage0, Engine and Presenter in process. It never
    // invokes the binary and never calls a reporter, so nothing under CLI/,
    // Log/ or Console/ can change a number it records — and hashing them anyway
    // meant that editing help text or a colour role invalidated all six rows
    // and cost a quarter of an hour to re-establish numbers that had not moved.
    //
    // Erring toward re-measuring is the safe direction and this still does: the
    // three directories left out are the ones the harness demonstrably does not
    // execute, not the ones that seem unlikely to matter.
    $untimed = ['/src/CLI/', '/src/Log/', '/src/Console/'];
    $root    = dirname(__DIR__) . '/src';
    $seen    = [];

    foreach (bcb_files($root) as $file) {
        foreach ($untimed as $directory) {
            if (str_contains($file, $directory)) {
                continue 2;
            }
        }

        $seen[] = $file . ':' . (string) filemtime($file) . ':' . (string) filesize($file);
    }

    sort($seen);
    $engine = implode("\n", $seen);

    $files = bcb_gate_files([$dir]);

    // A fingerprint that cannot see what it is fingerprinting must not answer
    // "unchanged" — that is the one wrong answer it can give, and it gives it
    // silently and forever. An empty engine or an empty corpus is a broken
    // question, not a match.
    if ($engine === '' || $files === []) {
        return '';
    }

    return substr(hash('sha256', $engine), 0, 12)
        . '-' . substr(hash('sha256', implode("\n", $files)), 0, 12)
        . '-' . count($files);
}

/**
 * Names from the directories the fingerprint leaves out, if any have loaded.
 *
 * `wt_fingerprint()` excludes `src/CLI/`, `src/Log/` and `src/Console/` on the
 * grounds that this harness never executes them — it calls Stage0, Engine and
 * Presenter in process, never the binary and never a reporter. That is a claim
 * about the harness, and a claim about code is worth what enforces it: the day
 * a timed section grows a reporter call, every recorded row silently stops
 * being invalidated by changes to the code it now runs.
 *
 * So the claim is checked rather than commented. Autoloading is what makes it
 * cheap: a class in those namespaces cannot be declared unless something asked
 * for it.
 *
 * @return list<string>
 */
function wt_untimed_loaded(): array
{
    $untimed = [
        'LucianoPereira\\PhpcpdNext\\Log\\',
        'LucianoPereira\\PhpcpdNext\\Console\\',
    ];

    $found = [];

    foreach (get_declared_classes() as $class) {
        foreach ($untimed as $namespace) {
            if (str_starts_with($class, $namespace)) {
                $found[] = $class;
            }
        }
    }

    sort($found);

    return $found;
}

/**
 * What a long measurement is doing, while it does it.
 *
 * A sweep is six corpora times six configurations times five runs, and it
 * printed nothing until a whole corpus finished — so for minutes at a time the
 * only evidence it was working was that it had not exited. On WordPress that is
 * four minutes of silence per configuration.
 *
 * On stderr and only when stderr is a terminal, for the reasons the product's
 * own progress bar is: the report goes to stdout and a carriage-returned line
 * in a redirected log is one line of accumulated garbage.
 *
 * Deliberately not `Console\Progress`, which does this properly and is right
 * there. `wt_fingerprint()` leaves `src/Console/` out on the grounds that this
 * harness never runs it, and `wt_untimed_loaded()` refuses to record a row if
 * that stops being true — so importing it here would make every row of the
 * sweep unrecordable. The guard earns its keep on the first thing it was asked
 * about.
 *
 * The estimate covers the configuration being measured and not the sweep. Runs
 * within one configuration are comparable, which is the whole point of taking
 * five of them; corpora are not — WordPress is four hundred times symfony-string
 * — so an estimate spanning them would be a guess wearing a number.
 *
 * @return ?callable(int, int, float): void
 */
function wt_progress(string $corpus, int $corpusAt, int $corpora, string $label, int $labelAt, int $labels): ?callable
{
    if (!defined('STDERR') || !stream_isatty(STDERR)) {
        return null;
    }

    return static function (int $done, int $times, float $seconds) use ($corpus, $corpusAt, $corpora, $label, $labelAt, $labels): void {
        $left = ($times - $done) * $seconds;

        fwrite(STDERR, sprintf(
            "\r  %-16s %d/%d   %-22s %d/%d   run %d/%d   %5.1fs each%s   ",
            $corpus,
            $corpusAt,
            $corpora,
            $label,
            $labelAt,
            $labels,
            $done,
            $times,
            $seconds,
            $left > 0.5 ? sprintf('   ~%s left here', wt_duration($left)) : '',
        ));
    };
}

/** A duration a person reads at a glance rather than converts. */
function wt_duration(float $seconds): string
{
    if ($seconds < 60) {
        return sprintf('%ds', (int) round($seconds));
    }

    return sprintf('%dm%02ds', (int) ($seconds / 60), (int) round($seconds) % 60);
}

/** Wipe the progress line, so what stays on screen is the measurement. */
function wt_progress_done(): void
{
    if (defined('STDERR') && stream_isatty(STDERR)) {
        fwrite(STDERR, "\r" . str_repeat(' ', 110) . "\r");
    }
}

/**
 * Merge these rows into the table and write it, now.
 *
 * Per corpus, not per invocation. The sweep that prompted this had measured
 * five corpora — twenty minutes, on AC, at full clock — and was still inside
 * the sixth when the machine was unplugged. Every one of those five rows was
 * sound and every one of them was lost, because nothing reached disk until the
 * whole run finished. A measurement that is good is good whatever happens
 * afterwards; holding it hostage to the rest of the sweep is the same
 * all-or-nothing mistake as refusing to write a partial run at all, one level
 * down.
 *
 * @param list<array<string, string>> $measured
 */
function wt_write(array $measured): int
{
    $target = dirname(__DIR__) . '/bench/results/walltime.tsv';
    @mkdir(dirname($target), 0o775, true);

    // Merged, not replaced. A run over named corpora updates the rows it
    // measured and leaves every other row where it was.
    $rows = wt_existing_rows($target);

    foreach ($measured as $row) {
        $rows[$row['corpus']] = $row;
    }

    // By corpus size, not by the order the directories arrived in. The table's
    // whole point is the ratio against size, and a table whose row order
    // depends on how the command was typed is a table two runs disagree about.
    $ordered = array_values($rows);
    usort($ordered, static fn(array $a, array $b): int => (int) $a['files'] <=> (int) $b['files']);

    // One column set across every row. Merging a freshly measured row into a
    // table written before a column existed would otherwise put one row's
    // values under another row's headings, which is a corrupt table that still
    // parses — the worst kind.
    $columns = [];

    foreach ($ordered as $row) {
        foreach (array_keys($row) as $column) {
            $columns[$column] = true;
        }
    }

    $columns = array_keys($columns);
    $lines   = [implode("\t", $columns)];

    foreach ($ordered as $row) {
        $values = [];

        foreach ($columns as $column) {
            $values[] = $row[$column] ?? '';
        }

        $lines[] = implode("\t", $values);
    }

    file_put_contents($target, implode("\n", $lines) . "\n");

    // The machine, in a form \input can take, so the paper states where its
    // numbers came from without anyone retyping that either.
    file_put_contents(dirname(__DIR__) . '/bench/results/walltime-machine.tex', bcb_power_line() . "\n");

    return count($ordered);
}

/**
 * The table as it stands, keyed by corpus.
 *
 * A run that measures one corpus updates that corpus's row and leaves the rest
 * alone. The first fix for the clobbering bug — only a full sweep may write —
 * stopped the damage and cost something it did not need to: re-measuring a
 * single corpus became impossible, so every re-measure was the whole sweep, a
 * quarter of an hour on a machine that cannot hold its clock that long. What the
 * bug needed was "do not replace the table with one row", not "do not write".
 *
 * @return array<string, array<string, string>> corpus => row
 */
function wt_existing_rows(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $lines = array_values(array_filter(explode("\n", (string) file_get_contents($path)), static fn(string $l): bool => trim($l) !== ''));

    if (count($lines) < 2) {
        return [];
    }

    $header = explode("\t", array_shift($lines));
    $rows   = [];

    foreach ($lines as $line) {
        $values = explode("\t", $line);

        if (count($values) !== count($header)) {
            continue;
        }

        /** @var array<string, string> $row */
        $row                     = array_combine($header, $values);
        $rows[$row['corpus'] ?? ''] = $row;
    }

    unset($rows['']);

    return $rows;
}

$runs      = 5;
$minTokens = 100;

/**
 * File counts to measure at, smallest first. Empty means "the whole directory,
 * once" — the shape this check had before M3 needed a size sweep.
 *
 * The plan's M3 gate is "unified >= default-pipeline speed at **every size**",
 * which a single whole-corpus number cannot answer: the two engines have
 * different shapes, and a ratio that holds at 2,500 files says nothing about 60.
 * A size is taken as the first N files of the directory in sorted order, so the
 * same N is the same file set on every run and between engines.
 *
 * @var list<int> $sizes
 */
$sizes = [];

$force = false;

foreach (array_slice(bcb_argv(), 1) as $arg) {
    if (str_starts_with($arg, '--runs=')) {
        $runs = max(1, (int) substr($arg, strlen('--runs=')));

        continue;
    }

    if (str_starts_with($arg, '--min-tokens=')) {
        $minTokens = (int) substr($arg, strlen('--min-tokens='));

        continue;
    }

    if (str_starts_with($arg, '--sizes=')) {
        foreach (explode(',', substr($arg, strlen('--sizes='))) as $size) {
            $sizes[] = max(1, (int) trim($size));
        }

        continue;
    }

    if ($arg === '--force') {
        $force = true;

        continue;
    }

    $dirs[] = $arg;
}

// A run over named directories reports; only a full sweep writes.
//
// It used to be the other way round, and the default was a trap. With no
// arguments this measured `src/` — 92 files, no duplication in them — and then
// wrote `bench/results/walltime.tsv` from that single row, replacing the
// six-corpus table `collect-facts.php` ingests and the paper renders. The
// obvious way to carry out "re-run the measured tables" destroyed them, and
// said `wrote bench/results/walltime.tsv (1 rows)` while doing it.
//
// So the no-argument run is now the sweep it was always documented to produce,
// matching `measure-logic-share.php`, and a partial run keeps its numbers to
// itself.
$sweep = $dirs === [];

if ($sweep) {
    foreach (glob(__DIR__ . '/corpus/*', GLOB_ONLYDIR) ?: [] as $corpus) {
        $dirs[] = $corpus;
    }
}

if ($dirs === []) {
    fwrite(STDERR, "No corpora under bench/corpus — run bench/fetch.sh first, or name directories to measure.\n");

    exit(1);
}

bcb_require_dirs($dirs);

$config = bcb_config(['minTokens' => $minTokens, 'minLines' => 5]);

/** @var list<array{ok: bool, claim: string, detail: string}> $log */
$log = [];

/** @var list<array<string, string>> $tsv */
$tsv = [];

printf("Wall-clock — %d runs per configuration, --min-tokens=%d\n", $runs, $minTokens);
printf("  %s\n\n", bcb_power_line());

/*
 * A wall-clock assertion is only as good as the clock underneath it. On battery
 * at 800 MHz this gate reported unified at 0.35x and called it a FAIL — a
 * verdict about the power policy wearing the costume of a verdict about the
 * engine. So the machine is asked first, and a machine that cannot deliver gets
 * to say so instead of being quoted.
 *
 * --force measures anyway, for the case where the operator knows better than
 * the sysfs files. It prints the reasons regardless, because a number taken
 * under a stated doubt is still usable and an unlabelled one never is.
 */
$verdict = bcb_timing_verdict();

if (!$verdict['ok']) {
    echo "  this machine cannot deliver a timing worth recording:\n";

    foreach ($verdict['reasons'] as $reason) {
        printf("    - %s\n", $reason);
    }

    if (!$force) {
        echo "\n  declining to measure. Re-run on AC with energy_performance_preference=performance,\n";
        echo "  or pass --force to measure anyway and label the result yourself.\n";

        exit(0);
    }

    echo "\n  --force given: measuring anyway. Do not publish these numbers unlabelled.\n\n";
}

$existing = wt_existing_rows(dirname(__DIR__) . '/bench/results/walltime.tsv');
$skipped  = 0;

foreach ($dirs as $corpusIndex => $dir) {
    $fingerprint = wt_fingerprint($dir);
    $recorded    = $existing[basename($dir)]['fingerprint'] ?? null;

    if (!$force && $fingerprint !== '' && $recorded === $fingerprint) {
        printf("  %-18s unchanged since it was measured — skipped (--force to measure anyway)\n", basename($dir));
        $skipped++;

        continue;
    }

    $all = bcb_gate_files([$dir]);

    if ($all === []) {
        bcb_check($log, false, $dir . ': has PHP files to scan');

        continue;
    }

    // No --sizes means the whole directory, once, as before.
    $wanted = $sizes === [] ? [count($all)] : $sizes;
    $seen   = [];

    foreach ($wanted as $size) {
        $take = min($size, count($all));

        // A directory smaller than the requested size would otherwise be
        // measured twice under two different labels, and the second reading
        // would claim a size the corpus does not have.
        if (isset($seen[$take])) {
            continue;
        }

        $seen[$take] = true;
        $files       = array_slice($all, 0, $take);

        printf("  %s — %d files\n", $dir, count($files));

        $configurations = [
            'default (rk+tokenbag)' => null,
            'rabin-karp'            => 'rabin-karp',
            'tokenbag'              => 'tokenbag',
            'unified'               => 'unified',
            'unified + presentation' => 'unified',
        ];

        $medians    = [];
        /** @var array<string, float> $spreads label => max minus min across the runs */
        $spreads    = [];
        $cloneCount = [];

        // The shipped default's Stage 0, in the shipped posture, over the same
        // file set: what every run pays before an engine sees a token. Reported
        // as its own line, never folded into `default`.
        $manifest = ComposerManifest::locate([$dir]);
        $stages   = count($configurations) + 1;
        $corpusAt = $corpusIndex + 1;
        $triage   = bcb_repeat(
            $runs,
            static fn(): int => (new Stage0())->triage($files, $manifest, witnesses: $files)->discardedCount(),
            wt_progress(basename($dir), $corpusAt, count($dirs), 'triage (label)', 1, $stages),
        );

        wt_progress_done();

        $medians['triage (label)'] = $triage['median'];

        printf(
            "    %-22s median %7.3fs   spread %6.3fs   min %6.3fs   max %6.3fs   %d labelled
",
            'triage (label)',
            $triage['median'],
            $triage['spread'],
            $triage['min'],
            $triage['max'],
            (new Stage0())->triage($files, $manifest, witnesses: $files)->discardedCount(),
        );

        if ($triage['errors'] !== []) {
            bcb_check($log, false, $dir . ': triage (label) ran without error', $triage['errors'][0]);
        }

        $stageAt = 1;

        foreach ($configurations as $label => $algorithm) {
            $present = $label === 'unified + presentation';
            $clones  = 0;
            ++$stageAt;
            $result  = bcb_repeat(
                $runs,
                static function () use ($files, $config, $algorithm, $present, &$clones): int {
                    $map = (new Engine($config, $algorithm))->detect($files);

                    // The tier as a run actually pays for it: strata over every
                    // finding's sites, the ranking's features, the total order.
                    // No ledger, which is the shipped default posture.
                    $clones = $present ? (new Presenter())->present($map)->count() : $map->count();

                    return $clones;
                },
                wt_progress(basename($dir), $corpusAt, count($dirs), $label, $stageAt, $stages),
            );

            wt_progress_done();

            $medians[$label]     = $result['median'];
            $spreads[$label]     = $result['spread'];
            $cloneCount[$label] = $clones;

            printf(
                "    %-22s median %7.3fs   spread %6.3fs   min %6.3fs   max %6.3fs   %d clones\n",
                $label,
                $result['median'],
                $result['spread'],
                $result['min'],
                $result['max'],
                $clones,
            );

            if ($result['errors'] !== []) {
                bcb_check($log, false, $dir . ': ' . $label . ' ran without error', $result['errors'][0]);
            }
        }

        $unified = $medians['unified'];
        $default = $medians['default (rk+tokenbag)'];

        /*
         * The row, in the file both the documents and the paper read.
         *
         * Until now this table existed only as terminal output, and reached a
         * document by being retyped — which is the practice bench/sigil.php was
         * written to end, left in place for the one measurement that most needed
         * it. A number that is transcribed by a person is a number that can be
         * transcribed wrongly, and nothing downstream can tell.
         */
        $row = [
            'corpus'    => basename($dir),
            'fingerprint' => $fingerprint,
            'files'     => (string) count($files),
            'default'   => sprintf('%.3f', $default),
            'unified'   => sprintf('%.3f', $unified),
            'ratio'     => sprintf('%.2f', $default > 0.0 ? $unified / $default : 0.0),
            // The spread of the two the gate compares, because a wall-clock
            // median without one is a number with its uncertainty deleted.
            //
            // WordPress is why this column exists. It was measured at 0.97x
            // (passing) and then at 1.13x (failing), and the disagreement is
            // not a change in the engine: the gap the gate judges there is
            // about 13% and the run-to-run spread is about 15%. The terminal
            // said so both times and the table kept only the medians, so the
            // one row whose verdict was never determined looked exactly like
            // the five that were.
            'defaultspread' => sprintf('%.3f', $spreads['default (rk+tokenbag)']),
            'unifiedspread' => sprintf('%.3f', $spreads['unified']),
            'triage'    => sprintf('%.3f', $medians['triage (label)']),
            'rabinkarp' => sprintf('%.3f', $medians['rabin-karp']),
            'tokenbag'  => sprintf('%.3f', $medians['tokenbag']),
            'clones'    => (string) $cloneCount['unified'],
        ];

        $tsv[] = $row;

        // Written now, and only if the machine held across THIS corpus. The
        // state is read before each one and again after it, so a row is kept
        // when the conditions it was taken under were sound and dropped when
        // they were not — at the granularity of the thing measured, rather than
        // of the invocation that happened to contain it.
        $after = bcb_timing_reasons(bcb_power_state());

        // Not subject to `--force`, unlike the check before the sweep starts.
        // That one is an operator override — someone who knows the sysfs files
        // are lying about their machine may say so, and the numbers are printed
        // under a stated doubt. This one says the machine changed *while this
        // corpus ran*, so its five runs did not measure one machine: the first
        // at four gigahertz, the last at eight hundred megahertz, and a median
        // across them describes neither. Nobody can knowingly want that row,
        // which is why no flag produces it.
        //
        // The two were one condition until `--force` was used to re-measure a
        // corpus whose fingerprint had not moved, and silently disarmed this as
        // well. A phpunit row was recorded off a battery run that way.
        if ($after !== []) {
            printf(
                "  NOT RECORDED — the machine changed while this corpus ran: %s\n"
                . "  Earlier corpora in this run keep their rows; re-run this one on AC.\n\n",
                implode('; ', $after),
            );

            break;
        }

        // The other condition on recording a row. A row is keyed by a
        // fingerprint that deliberately ignores three directories, so it is
        // only meaningful while this harness really does not run them.
        $loaded = wt_untimed_loaded();

        if ($loaded !== []) {
            printf(
                "  NOT RECORDED — this harness loaded %s, which wt_fingerprint() leaves out.\n"
                . "  A row keyed by that fingerprint would not be invalidated by changes to code\n"
                . "  the measurement now runs. Either stop calling it, or widen the fingerprint.\n\n",
                implode(', ', $loaded),
            );

            break;
        }

        $rows = wt_write([$row]);
        printf("  recorded — bench/results/walltime.tsv now holds %d rows\n", $rows);

        bcb_check(
            $log,
            $unified <= $default,
            sprintf('%s at %d files: unified is at or below the default pipeline', basename($dir), count($files)),
            sprintf(
                'unified %.3fs vs default %.3fs (%.2fx)',
                $unified,
                $default,
                $unified > 0.0 ? $default / $unified : INF,
            ),
        );

        echo "\n";
    }
}

if ($tsv !== []) {
    printf("%d corpus row(s) measured and recorded this run.\n\n", count($tsv));
} elseif ($skipped > 0) {
    printf("nothing to measure — %d corpus(es) unchanged since their rows were taken.\n\n", $skipped);
}

exit(bcb_check_summary($log, 'wall-clock check'));
