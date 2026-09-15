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
 * The fact table the documents are rendered from.
 *
 * Every number a document states comes from here, and everything here was
 * measured by running something. Nothing in this file is typed by a person,
 * which is the whole point: a hand-typed number is true once and then rots
 * silently, and the only reason anyone finds out is that someone re-derives it
 * by hand and happens to notice.
 *
 * Facts come in two kinds, and conflating them is what produced a 0.35x ratio
 * on a battery-throttled CPU:
 *
 *   - INVARIANT facts are properties of the corpora and the code. A file count
 *     is the same on a 900 MHz laptop and a 4 GHz one. These are re-measured on
 *     every run, unconditionally.
 *   - CONDITIONAL facts are wall-clock. They are valid only on a machine that
 *     can deliver them, so they are measured only when bench/power.php says so,
 *     and otherwise CARRIED FORWARD from the existing table with the machine
 *     and date they were taken on. Refusing to overwrite a good number with a
 *     meaningless one is the entire safety property here.
 *
 * Usage:
 *   php bench/collect-facts.php [--out=bench/results/facts.toml] [--force]
 *
 * --force measures timings regardless of the verdict, and stamps the machine
 * state it was taken under so the reader can discount it.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/power.php';

use LucianoPereira\PhpcpdNext\Util\FileFinder;

$root = dirname(__DIR__);
$out  = $root . '/bench/results/facts.toml';
$force = false;

foreach (array_slice(bcb_argv(), 1) as $arg) {
    if ($arg === '--force') {
        $force = true;

        continue;
    }

    if (str_starts_with($arg, '--out=')) {
        $candidate = substr($arg, 6);
        $out       = str_starts_with($candidate, '/') ? $candidate : $root . '/' . $candidate;

        continue;
    }

    fwrite(STDERR, sprintf("unknown option: %s\n", $arg));

    exit(2);
}

/**
 * Read the table already on disk, so conditional facts survive a run on a
 * machine that cannot re-measure them.
 *
 * @return array<string, array<string, string>>
 */
function facts_existing(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    require_once __DIR__ . '/sigil-parse.php';

    $raw = file_get_contents($path);

    return $raw === false ? [] : sigil_parse_facts($raw, $path);
}

$existing = facts_existing($out);
$verdict  = bcb_timing_verdict();

echo "Collecting facts\n";
printf("  %s\n", bcb_power_line());

// ---------------------------------------------------------------- invariant --

echo "\n  invariant — corpus scans\n";

$scan = [];

foreach (glob($root . '/bench/corpus/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $name   = basename($dir);
    $key    = str_replace('-', '_', $name);
    $finder = new FileFinder();
    $files  = $finder->find([$dir], ['.php'], []);

    $scan[$key . '_files']   = (string) count($files);
    $scan[$key . '_skipped'] = (string) $finder->skippedGeneratedCount();

    printf("    %-18s %5d files, %d skipped as generated\n", $name, count($files), $finder->skippedGeneratedCount());
}

/*
 * Constants the documents cite by value. A `PER_FILE_CAP` of 32 appears four
 * times in the deferred-work document and is load-bearing to its arithmetic;
 * if the constant moves, every one of those sentences becomes false and nothing
 * says so. Reflecting the value costs nothing and makes the prose track the
 * code rather than remember it.
 */
echo "\n  invariant — constants cited in prose\n";

/** @var array<string, class-string> $sources */
$sources = [
    'per_file_cap'               => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\FingerprintIndex::class,
    'postings_cap'               => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\FingerprintIndex::class,
    'seed_length'                => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\Winnower::class,
    'minimum_min_tokens'         => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\Winnower::class,
    'normalized_diversity_floor' => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\Winnower::class,
    'theta'                      => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\CloneClassifier::class,
    'displaced_mass_floor'       => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\CloneClassifier::class,
    'shingle_length'             => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\ShingleBags::class,
    'seed_pair_cap'              => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\AnchorSet::class,
    'ratio'                      => \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\BandedAligner::class,
];

$code = [];

foreach ($sources as $key => $class) {
    $name = strtoupper($key);

    if (!defined($class . '::' . $name)) {
        printf("    %-26s ABSENT — remove it from this list or restore the constant\n", $name);

        continue;
    }

    /** @var scalar $value */
    $value      = constant($class . '::' . $name);
    $code[$key] = (string) $value;

    printf("    %-26s %s\n", $name, $code[$key]);
}

/*
 * Derived, not declared. The similarity floor is 1 - RATIO and exists nowhere
 * as a constant, so `0.85` is typed out in three source comments and in
 * docs/research/interpretation.md — four places that go quietly wrong the day
 * RATIO moves. Deriving it here is what lets the prose cite it.
 */
$code['similarity_floor'] = (string) round(1.0 - \LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\BandedAligner::RATIO, 10);

printf("    %-26s %s (derived: 1 - RATIO)\n", 'SIMILARITY_FLOOR', $code['similarity_floor']);

/*
 * Also derived: the pair count a single capped fingerprint yields, C(F, 2).
 * The documents cite 499,500 beside the 1,000 it comes from, so binding the cap
 * alone would leave half of each sentence able to go wrong on its own.
 */
$postings                 = (int) $code['postings_cap'];
$code['postings_pairs']   = (string) intdiv($postings * ($postings - 1), 2);

printf("    %-26s %s (derived: C(POSTINGS_CAP, 2))\n", 'POSTINGS_PAIRS', $code['postings_pairs']);

// The shipped version, so a release checklist cites it rather than restating it.
$code['version'] = \LucianoPereira\PhpcpdNext\Version::NUMBER;

printf("    %-26s %s\n", 'VERSION', $code['version']);

/*
 * The release line, without the pre-release suffix. Documents say "removed in
 * 2.0.0", never "removed in 2.0.0", so binding them to the raw constant
 * would render a build identifier into prose that means a release.
 */
$code['release'] = (string) preg_replace('/-.*$/', '', $code['version']);

printf("    %-26s %s\n", 'RELEASE', $code['release']);

$totalFiles = 0;

foreach ($scan as $key => $value) {
    if (str_ends_with($key, '_files')) {
        $totalFiles += (int) $value;
    }
}

$scan['corpus_files_total'] = (string) $totalFiles;

// -------------------------------------------------------------- conditional --

$walltime = $existing['walltime'] ?? [];
$carried  = true;

$walltimeTsv = $root . '/bench/results/walltime.tsv';

if (is_file($walltimeTsv)) {
    /*
     * Ingested, not measured. check-walltime.php writes this file itself, under
     * its own power-state check, so the numbers arrive here already having
     * passed the gate that decides whether they were worth taking. Reading them
     * costs nothing and is valid on any machine — what must not happen on a
     * throttled one is producing them, and that is check-walltime's decision to
     * make, not this script's.
     */
    echo "\n  conditional — wall clock, from bench/results/walltime.tsv\n";

    $raw   = (string) file_get_contents($walltimeTsv);
    $lines = array_values(array_filter(explode("\n", trim($raw)), static fn(string $l): bool => $l !== ''));

    if ($lines === []) {
        echo "    empty — carrying forward\n";
    } else {
        $header   = explode("\t", array_shift($lines));
        $walltime = [];

        foreach ($lines as $line) {
            $cells = explode("\t", $line);

            if (count($cells) !== count($header)) {
                fwrite(STDERR, sprintf("walltime.tsv: row has %d cells, header has %d\n", count($cells), count($header)));

                exit(2);
            }

            /** @var array<string, string> $row */
            $row    = array_combine($header, $cells);
            $corpus = str_replace('-', '_', $row['corpus']);

            foreach ($row as $column => $value) {
                if ($column === 'corpus') {
                    continue;
                }

                $walltime[$corpus . '_' . $column] = $value;
            }

            printf("    %-18s default %ss  unified %ss  ratio %sx\n", $row['corpus'], $row['default'], $row['unified'], $row['ratio']);
        }

        $carried = false;
    }
} elseif ($verdict['ok'] || $force) {
    echo "\n  conditional — wall clock: no bench/results/walltime.tsv yet\n";
    echo "    this machine can be timed — run bench/check-walltime.php to produce it\n";
} else {
    echo "\n  conditional — wall clock: NOT measured\n";

    foreach ($verdict['reasons'] as $reason) {
        printf("    - %s\n", $reason);
    }

    printf("    carrying forward %d existing value(s) untouched\n", count($walltime));
}

// -------------------------------------------------------------------- write --

$provenance = [
    'scan_measured_at'     => gmdate('Y-m-d'),
    'scan_machine'         => bcb_power_line(),
    'walltime_measured_at' => $carried
        ? ($existing['provenance']['walltime_measured_at'] ?? 'never')
        : gmdate('Y-m-d', (int) @filemtime($walltimeTsv)),
    'walltime_machine'     => $carried
        ? ($existing['provenance']['walltime_machine'] ?? 'never')
        : trim((string) @file_get_contents($root . '/bench/results/walltime-machine.tex')),
];

/*
 * Paths, for the `@` sigil: a document inserts the rendered block whole rather
 * than restating its cells, which is the only way to put measured numbers
 * inside an indented code block.
 */
$paths = [
    'walltime_table'    => 'bench/results/walltime-table.txt',
    'logic_share_table' => 'bench/results/logic-share-table.txt',
];

/*
 * How prose names these facts, where it does not use the identifier's words.
 *
 * `postings_cap` is written as "kept at most 1,000 times across the corpus" —
 * nothing in that sentence contains "postings" or "cap", so without an alias
 * the corroboration check in bench/sigil-parse.php would demote a real citation
 * to advisory. Declared here because this is where the fact's meaning is known;
 * a key with no entry corroborates on its own words alone.
 */
$alias = [
    'postings_cap'    => 'corpus fingerprint posting',
    'postings_pairs'  => 'pairs seed corpus',
    'per_file_cap'    => 'fingerprint file cap seed',
    'seed_pair_cap'   => 'pairs seed enumerated',
    'seed_length'     => 'winnowing window token K',
    'similarity_floor'=> 'similarity emission invariant',
    'theta'           => 'similarity sim',
];

/**
 * A TOML key as a LaTeX control sequence: letters only, so `walltime` and
 * `firefly_iii_ratio` become `WalltimeFireflyIiiRatio`. LaTeX has no other
 * characters available in a macro name, which is why this is a transformation
 * rather than a copy.
 */
function bcb_macro_name(string $key): string
{
    $name = '';

    foreach (explode('_', $key) as $word) {
        $name .= ucfirst(strtolower(preg_replace('/[^A-Za-z]/', '', $word) ?? ''));
    }

    return $name;
}

$tables = ['scan' => $scan, 'code' => $code, 'walltime' => $walltime, 'paths' => $paths, 'alias' => $alias, 'provenance' => $provenance];

$lines = [
    '# Generated by bench/collect-facts.php. Do not edit.',
    '#',
    '# Every value here was measured. Documents reference these keys through',
    '# bench/sigil.php rather than stating numbers of their own; `php',
    '# bench/sigil.php --check` fails when a document disagrees with this file.',
    '',
];

foreach ($tables as $name => $pairs) {
    if ($pairs === []) {
        continue;
    }

    $lines[] = sprintf('[%s]', $name);

    ksort($pairs);

    foreach ($pairs as $key => $value) {
        $lines[] = sprintf('%s = "%s"', $key, addcslashes($value, "\"\\"));
    }

    $lines[] = '';
}

@mkdir(dirname($out), 0o775, true);
file_put_contents($out, implode("\n", $lines));

printf("\n  wrote %s\n", str_replace($root . '/', '', $out));

/*
 * The same facts, as LaTeX macros.
 *
 * The paper is the one document that states measured numbers in its own prose,
 * and it is the one document `sigil --check` does not cover — which is how it
 * came to describe a triage posture the tool had stopped having. The obvious
 * repair was to teach sigil a `%`-comment span, so that the paper could hold a
 * copy of each number and the gate could check the copy was fresh.
 *
 * That is the worse of the two available answers. A span stores the value in
 * the document and detects that it went stale; a macro does not store it at
 * all, so there is nothing to go stale. The paper already reads
 * `bench/results/compare.tsv` for its plots and typesets a table straight out
 * of it — this extends what it does rather than adding a second mechanism
 * beside it.
 *
 * A fact with no value is omitted rather than emitted empty, so that a document
 * asking for one fails the build loudly instead of typesetting a blank.
 */
$macros = [
    '% Generated by bench/collect-facts.php. Do not edit.',
    '%',
    '% Measured values, for a document that would otherwise type them out.',
    '% An undefined macro is a build error; a stale number is not, which is why',
    '% the paper reads these rather than keeping copies of them.',
    '',
];

foreach ($tables as $name => $pairs) {
    ksort($pairs);

    foreach ($pairs as $key => $value) {
        if ($value === '' || str_contains($value, "\n")) {
            continue;
        }

        $macros[] = sprintf('\newcommand{\fact%s}{%s}', bcb_macro_name($name . '_' . $key), $value);
    }
}

$tex = $root . '/docs/paper/facts.tex';

file_put_contents($tex, implode("\n", $macros) . "\n");

printf("  wrote %s\n", str_replace($root . '/', '', $tex));

exit(0);
