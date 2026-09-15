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
 * Determinism check.
 *
 * Identical input must produce byte-identical output, every run. That is a
 * contract with anyone who diffs two reports in CI, and it is fragile in exactly
 * the way that is hard to notice: PHP arrays iterate in insertion order, so a
 * result set assembled from a hash table is stable only as long as the order
 * things were inserted is itself derived from something sorted. Change the order
 * files arrive in and an engine that never sorts its output will hand back the
 * same clones arranged differently — which reads as a diff, and which no single
 * repeated run will ever reveal.
 *
 * So the check has three parts, and the third is the one with teeth:
 *
 *   1. repeated  — the same file list twice, in-process;
 *   2. reversed  — the same files in the opposite order, which must not change
 *                  a single byte of the report;
 *   3. CLI       — two full command-line runs writing JSON, byte-compared, so
 *                  the guarantee is checked at the surface users actually see.
 *
 * Usage:
 *   php bench/check-determinism.php [<dir> ...] [--algorithm=a,b,...]
 *
 * Directories default to src/. Algorithms default to the fast set; pass e.g.
 * --algorithm=unified to check one engine, or "default" for the merged pipeline.
 * Exit 0 only if every algorithm is deterministic in all three senses.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Engine;

const DETERMINISM_OPTS = ['minTokens' => 70, 'minLines' => 5];

$dirs       = [];
$algorithms = ['default', 'rabin-karp', 'tokenbag'];

foreach (array_slice(bcb_argv(), 1) as $arg) {
    if (str_starts_with($arg, '--algorithm=')) {
        $algorithms = explode(',', substr($arg, strlen('--algorithm=')));

        continue;
    }

    $dirs[] = $arg;
}

if ($dirs === []) {
    $dirs = [dirname(__DIR__) . '/src'];
}

bcb_require_dirs($dirs);

// The corpus as the product defines it — see bcb_gate_files(). Pointing this
// check at a directory under vendor/ still works: find() prunes descendants of
// the roots it is given, never the roots themselves.
$files = bcb_gate_files($dirs);

if ($files === []) {
    fwrite(STDERR, "No PHP files found in: " . implode(', ', $dirs) . "\n");

    exit(1);
}

/**
 * Run one algorithm over one file list. A named function rather than a closure
 * so the list<string> contract is visible to a static analyser, and so the
 * reversed-order call below is checked against the same signature.
 *
 * @param list<string> $files
 */
function determinism_detect(array $files, string $algorithm): CodeCloneMap
{
    return (new Engine(bcb_config(DETERMINISM_OPTS), $algorithm === 'default' ? null : $algorithm))->detect($files);
}

/** The report exactly as the engine ordered it — the byte-identity comparison. */
function determinism_verbatim(CodeCloneMap $map): string
{
    return implode("\n", array_map(static fn($clone): string => (string) json_encode($clone->toArray()), $map->clones()));
}

/**
 * The same report with the clones sorted: equal here but not verbatim means the
 * engine found the same things and emitted them in a different order.
 */
function determinism_sorted(CodeCloneMap $map): string
{
    $rows = array_map(static fn($clone): string => (string) json_encode($clone->toArray()), $map->clones());
    sort($rows);

    return implode("\n", $rows);
}

/**
 * The same report with the copies *inside* each clone also sorted: equal here
 * but not sorted() means the engine found the same clone pairs and disagreed
 * only about which copy to name first. That is a much smaller defect than
 * finding different clones, and worth telling apart — a report that swaps the
 * two halves of every pair still points at exactly the same duplication.
 */
function determinism_unordered(CodeCloneMap $map): string
{
    $rows = [];

    foreach ($map->clones() as $clone) {
        $row   = $clone->toArray();
        $files = array_map(static fn(array $f): string => $f['path'] . ':' . $f['line'], $row['files']);
        sort($files);
        $row['files'] = $files;
        $rows[]       = (string) json_encode($row);
    }

    sort($rows);

    return implode("\n", $rows);
}

/** Why two reports differ, in the most specific terms available. */
function determinism_diagnosis(CodeCloneMap $a, CodeCloneMap $b): string
{
    if (determinism_sorted($a) === determinism_sorted($b)) {
        return 'the same clones, emitted in a different order — the report is not sorted by a total order';
    }

    if (determinism_unordered($a) === determinism_unordered($b)) {
        return 'the same clone pairs, but with the two copies\' roles exchanged — the report names whichever copy it happened to reach first';
    }

    return 'a different set of clones entirely';
}

/** @var list<array{ok: bool, claim: string, detail: string}> $log */
$log = [];
$clonesSeen = 0;

printf("Determinism check — %d files, algorithms: %s\n\n", count($files), implode(', ', $algorithms));

foreach ($algorithms as $algorithm) {
    $name = $algorithm === 'default' ? 'default (rk+tokenbag)' : $algorithm;

    $first = bcb_time(static fn(): CodeCloneMap => determinism_detect($files, $algorithm));

    if ($first['outcome'] !== BCB_OK) {
        bcb_check($log, false, $name . ': the engine runs at all', (string) $first['error']);

        continue;
    }

    $second   = determinism_detect($files, $algorithm);
    $reversed = determinism_detect(array_reverse($files), $algorithm);

    $firstMap = $first['value'];
    assert($firstMap instanceof CodeCloneMap);

    printf("  %s — %d clones in %.3fs\n", $name, $firstMap->count(), $first['seconds']);
    $clonesSeen += $firstMap->count();

    bcb_check(
        $log,
        determinism_verbatim($firstMap) === determinism_verbatim($second),
        $name . ': two runs over the same file list are byte-identical',
    );

    // The teeth. An engine that never sorts its output passes the check above
    // and fails this one.
    bcb_check(
        $log,
        determinism_verbatim($firstMap) === determinism_verbatim($reversed),
        $name . ': reversing the file list does not change a byte of the report',
        determinism_verbatim($firstMap) === determinism_verbatim($reversed)
            ? ''
            : determinism_diagnosis($firstMap, $reversed),
    );

    // And at the surface users see.
    $binary = dirname(__DIR__) . '/phpcpd';
    $tmp    = sys_get_temp_dir() . '/bcb-determinism-' . getmypid();
    @mkdir($tmp, 0o755, true);

    $reports = [];
    $ran     = true;

    foreach (['a', 'b'] as $pass) {
        $out     = $tmp . '/report-' . $algorithm . '-' . $pass . '.json';
        $command = [
            PHP_BINARY, $binary,
            '--min-tokens', '70', '--min-lines', '5',
            // Default excludes ON, matching bcb_gate_files() above, so the CLI
            // half and the in-process half scan one corpus rather than two.
            '--allow-root-scan',
            // And no `phpcpd.ini`. The project keeps one for scanning itself,
            // and it excludes `bench/corpus` — so without this the CLI half
            // finds no files at all and the comparison is between two empty
            // reports, while the in-process half, which never reads the file,
            // scans the corpus. A benchmark that changes answer with the
            // working directory is measuring the working directory.
            '--no-config',
            '--log-json', $out,
        ];

        if ($algorithm !== 'default') {
            $command = [...$command, '--algorithm', $algorithm];
        }

        $result = bcb_run([...$command, ...$dirs], 600.0);

        // phpcpd exits non-zero when it finds clones, which is not a failure of
        // the run; only a run that never produced its report is.
        if (!is_file($out)) {
            bcb_check($log, false, $name . ': the CLI produced a JSON report', (string) $result['error'] . $result['stderr']);
            $ran = false;

            break;
        }

        $reports[] = (string) file_get_contents($out);
        @unlink($out);
    }

    if ($ran && count($reports) === 2) {
        bcb_check(
            $log,
            $reports[0] === $reports[1],
            $name . ': two CLI runs write byte-identical JSON',
            sprintf('%d bytes', strlen($reports[0])),
        );
    }

    @rmdir($tmp);
}

// Two empty reports are byte-identical, and so are three. A determinism check
// over a corpus with no duplication in it passes every comparison above while
// proving nothing at all, which is precisely the kind of green this project has
// been burned by before.
bcb_check(
    $log,
    $clonesSeen > 0,
    'the corpus actually contains clones, so the comparisons above mean something',
    $clonesSeen > 0 ? $clonesSeen . ' clones compared' : 'every report was empty — pass a corpus with duplication in it',
);

exit(bcb_check_summary($log, 'determinism check'));
