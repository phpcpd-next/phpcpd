<?php

declare(strict_types=1);

/*
 * This file is part of PHP Copy/Paste Detector (PHPCPD).
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * run-compare.php — original phpcpd.phar vs PhpcpdNext, all corpora, all combos.
 *
 * Usage:
 *   php bench/run-compare.php                    # all corpora
 *   php bench/run-compare.php symfony-string     # one corpus
 *
 * All variants use min-tokens=70 / min-lines=5 to match the phar's defaults.
 * The suffix-tree lane was removed with the engine in 2.0.0; the published
 * comparison results that included it are kept under bench/results/ unchanged.
 *
 * Results are written to bench/results/compare/<corpus>.tsv
 */

require_once __DIR__ . '/lib.php';


// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

const PHAR        = __DIR__ . '/vendor/phpcpd.phar';
const CORPUS_ROOT = __DIR__ . '/corpus';
const RESULTS_DIR = __DIR__ . '/results/compare';

// Shared detection options — same as phar defaults for a fair comparison.
// Every variant names its own normalization, both halves of it. A variant
// table that inherits any part of its configuration from a default is only a
// table of variants for as long as that default stays put, and this one did
// not: when normalization became the shipped default, `rk` and `rk+fuzzy`
// became the same run and the matrix reported them as such without complaint.
const BASE_OPTS = ['minTokens' => 70, 'minLines' => 5];

$args = array_slice($argv, 1);

$allCorpora = array_map(
    fn(string $p) => basename($p),
    glob(CORPUS_ROOT . '/*', GLOB_ONLYDIR) ?: [],
);
sort($allCorpora);

$corpora = $args !== [] ? $args : $allCorpora;

// ---------------------------------------------------------------------------
// Variant definitions
// ---------------------------------------------------------------------------

// Each variant: [label, callable(files) -> [clones, dup_lines, pct, gapped, time_s]]

$variants = [
    [
        'label'   => 'original (phar)',
        'detect'  => static function (array $files, string $dir): array {
            if (!is_file(PHAR)) {
                return ['error' => 'phar not found at ' . PHAR];
            }
            $t0  = microtime(true);
            $cmd = sprintf(
                'php %s --min-tokens 70 --min-lines 5 %s 2>&1',
                escapeshellarg(PHAR),
                escapeshellarg($dir),
            );
            $out = shell_exec($cmd) ?? '';
            $t   = round(microtime(true) - $t0, 2);

            // phar 6.x: "Found N clones with M duplicated lines in X files:"
            //           "(P)% duplicated lines out of T total lines of code."
            $clones   = 0;
            $dupLines = 0;
            $pct      = '0.00%';

            if (preg_match('/Found (\d+) clones? with (\d+) duplicated lines?/', $out, $m)) {
                $clones   = (int) $m[1];
                $dupLines = (int) $m[2];
            }

            if (preg_match('/([\d.]+)% duplicated lines out of/', $out, $mp)) {
                $pct = $mp[1] . '%';
            }

            return ['clones' => $clones, 'dup_lines' => $dupLines, 'pct' => $pct, 'gapped' => '-', 'time' => $t . 's'];
        },
    ],
    [
        'label'  => 'rk',
        'detect' => static fn(array $files, string $dir) => run_next($files, BASE_OPTS + ['algorithm' => 'rabin-karp', 'fuzzy' => false, 'typeAnchored' => false]),
    ],
    [
        'label'  => 'rk+fuzzy',
        'detect' => static fn(array $files, string $dir) => run_next($files, BASE_OPTS + ['algorithm' => 'rabin-karp', 'fuzzy' => true, 'typeAnchored' => false]),
    ],
    [
        'label'  => 'rk+anchored',
        'detect' => static fn(array $files, string $dir) => run_next($files, BASE_OPTS + ['algorithm' => 'rabin-karp', 'fuzzy' => false, 'typeAnchored' => true]),
    ],
    [
        'label'  => 'tb (sim=0.7)',
        'detect' => static fn(array $files, string $dir) => run_next($files, BASE_OPTS + ['algorithm' => 'tokenbag', 'similarity' => 0.7, 'fuzzy' => false, 'typeAnchored' => false]),
    ],
    [
        'label'  => 'tb+fuzzy (sim=0.7)',
        'detect' => static fn(array $files, string $dir) => run_next($files, BASE_OPTS + ['algorithm' => 'tokenbag', 'similarity' => 0.7, 'fuzzy' => true, 'typeAnchored' => false]),
    ],
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function run_next(array $files, array $opts): array
{
    $t0  = microtime(true);
    $map = bcb_detect($files, $opts);
    $t   = round(microtime(true) - $t0, 2);

    return [
        'clones'    => $map->count(),
        'dup_lines' => $map->numberOfDuplicatedLines(),
        'pct'       => $map->percentage(),
        'gapped'    => $map->numberOfGappedClones(),
        'time'      => $t . 's',
    ];
}

function row(string $label, array $r): string
{
    if (isset($r['error'])) {
        return sprintf("  %-22s  ERROR: %s\n", $label, $r['error']);
    }

    return sprintf(
        "  %-22s  %6s  %9s  %7s  %7s  %6s\n",
        $label,
        $r['clones'],
        $r['dup_lines'],
        $r['pct'],
        $r['gapped'],
        $r['time'],
    );
}

function tsv_row(string $corpus, string $label, array $r): string
{
    if (isset($r['error'])) {
        return implode("\t", [$corpus, $label, 'ERROR', '', '', '', '']) . "\n";
    }

    return implode("\t", [
        $corpus,
        $label,
        $r['clones'],
        $r['dup_lines'],
        $r['pct'],
        $r['gapped'],
        $r['time'],
    ]) . "\n";
}

// ---------------------------------------------------------------------------
// Main loop
// ---------------------------------------------------------------------------

@mkdir(RESULTS_DIR, 0777, true);

$header = sprintf(
    "  %-22s  %6s  %9s  %7s  %7s  %6s\n",
    'variant', 'clones', 'dup_lines', 'pct', 'gapped', 'time',
);
$sep = '  ' . str_repeat('-', 66) . "\n";

foreach ($corpora as $corpus) {
    $dir = CORPUS_ROOT . '/' . $corpus;

    if (!is_dir($dir)) {
        fprintf(STDERR, "corpus not found: %s\n", $dir);
        continue;
    }

    $files     = bcb_files($dir);
    $fileCount = count($files);

    echo "\n=== $corpus ($fileCount files) ===\n\n";

    echo $header . $sep;

    $tsvRows = [];

    foreach ($variants as $v) {
        if (!empty($v['skip'])) {
            $r = ['clones' => '-', 'dup_lines' => '-', 'pct' => '-', 'gapped' => '-', 'time' => 'skipped'];
            printf("  %-22s  %s\n", $v['label'], 'skipped (corpus too large, use --no-st to suppress)');
            $tsvRows[] = tsv_row($corpus, $v['label'], $r);
            continue;
        }

        fprintf(STDERR, "  [running] %-22s\r", $v['label']);
        $r = ($v['detect'])($files, $dir);
        fprintf(STDERR, "  %-32s\r", ''); // clear progress line

        echo row($v['label'], $r);
        $tsvRows[] = tsv_row($corpus, $v['label'], $r);
    }

    // Write TSV
    $tsvFile = RESULTS_DIR . '/' . $corpus . '.tsv';
    $tsvHead = "corpus\tvariant\tclones\tdup_lines\tpct\tgapped\ttime\n";
    file_put_contents($tsvFile, $tsvHead . implode('', $tsvRows));
    echo "\n  -> $tsvFile\n";
}

echo "\nDone.\n";
