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
 * The two tools as each is configured out of the box.
 *
 * `run-compare.php` runs every variant at min-tokens 70 to match the phar's
 * default, which was the right comparison while both tools counted tokens the
 * same way. They no longer do: this engine stopped discarding single-character
 * tokens — about half the program text — and raised its own default to 100 in
 * consequence, so a "70-token" clone means one thing to the phar and another
 * here. The accounting diverged too, this engine charging only lines that hold
 * a token the matchers can see where the phar charges the whole range.
 *
 * There is therefore no shared setting at which the two are commensurable, and
 * the equivalence the paper once claimed cannot be restored by re-running at
 * some other number. What can be compared is what a user actually meets: each
 * tool at its own defaults. That is what this records.
 *
 * The percentages are each tool's own and are NOT comparable across the two
 * halves — they are written down so each half can be read against its own
 * tool's output, and the paper's table says so where it prints them.
 *
 * Written to bench/results/out-of-the-box.tsv, which the paper typesets
 * directly. Nothing here is typed into a document by hand: a hand-typed number
 * is true once and then rots silently, which is the whole reason this file
 * exists rather than a paragraph of figures.
 *
 * Usage:
 *   php bench/run-out-of-the-box.php [<corpus> ...]
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

$root  = dirname(__DIR__);
$phar  = $root . '/bench/vendor/phpcpd.phar';
$mine  = $root . '/phpcpd';
$out   = $root . '/bench/results/out-of-the-box.tsv';

if (!is_file($phar)) {
    fwrite(STDERR, "bench/vendor/phpcpd.phar is missing — run bench/fetch.sh.\n");

    exit(1);
}

$corpora = array_slice(bcb_argv(), 1);

if ($corpora === []) {
    foreach (glob($root . '/bench/corpus/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $corpora[] = basename($dir);
    }

    sort($corpora);
}

/** Run a command and return its stdout. */
function oob_run(string $command): string
{
    $handle = popen($command . ' 2>/dev/null', 'r');

    if ($handle === false) {
        return '';
    }

    $output = (string) stream_get_contents($handle);
    pclose($handle);

    return $output;
}

$rows = [];

foreach ($corpora as $corpus) {
    $dir = $root . '/bench/corpus/' . $corpus;

    if (!is_dir($dir)) {
        fwrite(STDERR, "  skip: no such corpus '$corpus'\n");

        continue;
    }

    printf("  %s\n", $corpus);

    // The phar at its own defaults.
    $pharOut    = oob_run(sprintf('php %s --min-lines 5 --min-tokens 70 %s', escapeshellarg($phar), escapeshellarg($dir)));
    $pharClones = preg_match_all('/^  - /m', $pharOut);
    preg_match('/([0-9.]+)% duplicated/', $pharOut, $m);
    $pharPct = $m[1] ?? '0';

    // This tool at its own defaults. The corpus is copied out of bench/,
    // because the shipped exclude list prunes it — which is correct behaviour
    // and would otherwise make the run scan nothing.
    $staging = sys_get_temp_dir() . '/oob-' . $corpus;
    exec(sprintf('rm -rf %s && cp -r %s %s', escapeshellarg($staging), escapeshellarg($dir), escapeshellarg($staging)));

    $mineOut = oob_run(sprintf('php %s %s', escapeshellarg($mine), escapeshellarg($staging)));
    preg_match('/Found clones \((\d+)\)/', $mineOut, $m);
    $mineClones = $m[1] ?? '0';
    preg_match('/([0-9.]+)% of scanned lines/', $mineOut, $m);
    $minePct = $m[1] ?? '0';

    exec(sprintf('rm -rf %s', escapeshellarg($staging)));

    $rows[] = [$corpus, (string) $pharClones, $pharPct, $mineClones, $minePct];
}

$lines = ["corpus\tphar_clones\tphar_pct\tnext_clones\tnext_pct"];

foreach ($rows as $row) {
    $lines[] = implode("\t", $row);
}

file_put_contents($out, implode("\n", $lines) . "\n");

printf("\nwrote %s (%d corpora)\n", str_replace($root . '/', '', $out), count($rows));
