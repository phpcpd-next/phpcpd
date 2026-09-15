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
 * One measured table, two consumers.
 *
 * The paper's tab:compare and fig:compare state thirty numbers between them,
 * every one transcribed by hand out of bench/results/compare/*.tsv — the same
 * figures, retyped into a tabular and again into a pgfplots coordinate list, so
 * a re-measurement means editing three places and the two that get missed stay
 * wrong in the artefact people actually read.
 *
 * The runners already write their results per corpus, in long form: one row per
 * corpus and variant. That is the right shape to WRITE and the wrong shape to
 * typeset, so this pivots it into the wide form a table wants and writes it back
 * out as TSV — a format `pgfplotstable` reads directly, for both the tabular and
 * the plot, and which bench/sigil.php can pull single cells from for the prose.
 *
 * Nothing here measures anything. It is a projection of files that already
 * exist, so it is safe to run on any machine, in any power state.
 *
 * Usage:
 *   php bench/build-tables.php [--check]
 *
 * --check writes nothing and exits non-zero if the derived tables are not what
 * the sources say they should be, which is the gate: a re-measurement that does
 * not rebuild them fails rather than leaving the paper quietly stale.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

/**
 * Long-form variant labels, and the column each becomes. The labels carry their
 * parameters (`tb (sim=0.7)`) because the runner records what it ran; a column
 * heading cannot, so the mapping is stated here rather than guessed by prefix.
 *
 * @var array<string, string>
 */
/**
 * Short names for the plot's x axis, where the full ones do not fit.
 *
 * @var array<string, string>
 */
const COMPARE_LABELS = [
    'symfony-console' => 'console',
    'symfony-string'  => 'string',
    'firefly-iii'     => 'firefly',
];

const COMPARE_VARIANTS = [
    'original (phar)'    => 'phar',
    'rk'                 => 'rk',
    'rk+fuzzy'           => 'rk_fuzzy',
    'tb (sim=0.7)'       => 'tb',
    'tb+fuzzy (sim=0.7)' => 'tb_fuzzy',
];

/**
 * Read a TSV into rows keyed by column name.
 *
 * @return list<array<string, string>>
 */
function bt_read(string $path): array
{
    $raw = file_get_contents($path);

    if ($raw === false) {
        fwrite(STDERR, sprintf("could not read %s\n", $path));

        exit(2);
    }

    $lines = array_values(array_filter(explode("\n", trim($raw)), static fn(string $l): bool => $l !== ''));

    if ($lines === []) {
        return [];
    }

    $header = explode("\t", array_shift($lines));
    $rows   = [];

    foreach ($lines as $line) {
        $cells = explode("\t", $line);

        if (count($cells) !== count($header)) {
            fwrite(STDERR, sprintf("%s: row has %d cells, header has %d\n", $path, count($cells), count($header)));

            exit(2);
        }

        /** @var array<string, string> $row */
        $row    = array_combine($header, $cells);
        $rows[] = $row;
    }

    return $rows;
}

$root    = dirname(__DIR__);
$check   = in_array('--check', array_slice(bcb_argv(), 1), true);
$sources = glob($root . '/bench/results/compare/*.tsv') ?: [];

sort($sources);

$wide = [];

foreach ($sources as $source) {
    $corpus = basename($source, '.tsv');

    // The plot's x axis wants a short label and the table wants the corpus's
    // real name, so the file carries both rather than making either consumer
    // rewrite the other's.
    $cells = ['corpus' => $corpus, 'label' => COMPARE_LABELS[$corpus] ?? $corpus];

    foreach (bt_read($source) as $row) {
        $column = COMPARE_VARIANTS[$row['variant']] ?? null;

        if ($column === null) {
            continue;
        }

        // The percentage is stored as it was printed, sign and all. A table
        // column is a number, so the unit comes off here rather than being
        // stripped by whoever typesets it.
        $cells[$column] = rtrim($row['pct'], '%');
    }

    $missing = array_diff(array_values(COMPARE_VARIANTS), array_keys($cells));

    if ($missing !== []) {
        fwrite(STDERR, sprintf("%s: no row for %s\n", $corpus, implode(', ', $missing)));

        exit(2);
    }

    $wide[] = $cells;
}

if ($wide === []) {
    fwrite(STDERR, "no source tables under bench/results/compare/\n");

    exit(2);
}

$columns = array_keys($wide[0]);
$lines   = [implode("\t", $columns)];

foreach ($wide as $row) {
    $ordered = [];

    foreach ($columns as $column) {
        $ordered[] = $row[$column] ?? '';
    }

    $lines[] = implode("\t", $ordered);
}

$rendered = implode("\n", $lines) . "\n";
$target   = $root . '/bench/results/compare.tsv';

/*
 * Faults accumulate rather than exiting. The first version returned as soon as
 * compare.tsv matched, so --check never reached the wall-clock table below and
 * reported a pass having verified half of what it claims to. A gate that stops
 * at its first success is worse than one that stops at its first failure.
 */
$stale = 0;

if ($check) {
    if ((is_file($target) ? file_get_contents($target) : '') === $rendered) {
        printf("  ok      bench/results/compare.tsv (%d corpora)\n", count($wide));
    } else {
        $stale++;
        fwrite(STDERR, "bench/results/compare.tsv is not what bench/results/compare/*.tsv say it should be.\n");
        fwrite(STDERR, "Run: php bench/build-tables.php\n");
    }
} else {
    file_put_contents($target, $rendered);

    printf("  wrote   bench/results/compare.tsv (%d corpora, %d variants)\n", count($wide), count(COMPARE_VARIANTS));
}

/*
 * The wall-clock table as prose renders it.
 *
 * A markdown document shows this as an indented block, and an indented block is
 * the one place a sigil marker cannot go: HTML comments render as literal text
 * inside it rather than as nothing. So the block is inserted whole, by the `@`
 * sigil, from a file rendered here — the markers sit on their own lines outside
 * the block, where they are invisible, and the rows come from the same TSV
 * check-walltime.php wrote.
 */
$walltime = $root . '/bench/results/walltime.tsv';

if (is_file($walltime)) {
    $rows = bt_read($walltime);

    if ($rows !== []) {
        $out = [sprintf('    %-18s %6s %9s %9s %8s', 'corpus', 'files', 'default', 'unified', 'ratio')];

        foreach ($rows as $row) {
            $out[] = sprintf(
                '    %-18s %6s %8ss %8ss %7sx%s',
                $row['corpus'],
                $row['files'],
                $row['default'],
                $row['unified'],
                $row['ratio'],
                (float) $row['ratio'] <= 1.0 ? '   <- passes' : '',
            );
        }

        $target = $root . '/bench/results/walltime-table.txt';
        $body   = implode("\n", $out) . "\n";

        if ($check) {
            if ((is_file($target) ? file_get_contents($target) : '') !== $body) {
                $stale++;
                fwrite(STDERR, "bench/results/walltime-table.txt is stale — run php bench/build-tables.php\n");
            } else {
                printf("  ok      bench/results/walltime-table.txt\n");
            }
        } else {
            file_put_contents($target, $body);
            printf("  wrote   bench/results/walltime-table.txt (%d rows)\n", count($rows));
        }
    }
}

exit($stale === 0 ? 0 : 1);
