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
 * The confidence ranking's parameters, counted over the recorded label corpus.
 *
 * The M5 charter asks for a ranking *"derived as counts over the recorded
 * 120-label corpus with log-odds printed per finding (ruling T's derivation
 * standard)"*. This is the instrument that does the counting, and it exists as a
 * separate script for the reason `bench/train-triage.php` does: a model trained
 * inside the thing it scores is a model nobody can check.
 *
 * ## What is counted
 *
 * The two preserved worksheets — 60 rated findings each, both raters — relocated
 * against ruling P's pinned manifest. **Each finding contributes one observation
 * per rater**, so a finding the two raters disagreed about contributes one to
 * each class rather than being dropped. That is deliberate: a contested finding
 * is genuinely ambiguous evidence, and a *ranking* is the one instrument that can
 * represent ambiguity as a middling score instead of having to take a side. The
 * alternative reading — consensus labels only — is measured too and reported
 * beside it, so the choice can be checked rather than trusted.
 *
 * ## What is NOT counted
 *
 * Nothing rated in the M5 round. The model is trained before that pool exists,
 * on labels that predate it, and it is never retrained on a gate it is being
 * asked to pass — ruling D's lesson, restated by ruling T.
 *
 * ## Features
 *
 * Computed through `Presentation\ConfidenceFeatures::describe()` — the shipped
 * class, not a copy — so the vector counted here and the vector scored at run
 * time come from one code path and cannot drift.
 *
 * ## The corpus is never named
 *
 * Root, manifest, worksheets and site tables are all arguments, never defaulted
 * and never recorded. The output is a PHP constant table and a log-odds report;
 * neither contains a path.
 *
 * Usage:
 *   php bench/train-confidence.php --corpus=<root> --pin=<manifest> \
 *       --sheet=<name>:<raterA.tsv>:<raterB.tsv>:<sites.tsv> [--sheet=...]
 */

require_once __DIR__ . '/lib.php';

use LucianoPereira\PhpcpdNext\Facts\FileFacts;
use LucianoPereira\PhpcpdNext\Presentation\ConfidenceFeatures;
use LucianoPereira\PhpcpdNext\Presentation\ConfidenceModel;

/**
 * The literal share of one span, through the shipped facts layer.
 *
 * @param array<string, ?FileFacts> $cache
 */
function tc_literal_share(string $root, string $relative, int $startLine, int $lineCount, array &$cache): ?float
{
    if (!array_key_exists($relative, $cache)) {
        $cache[$relative] = FileFacts::read($root . '/' . $relative);
    }

    $facts = $cache[$relative];

    if ($facts === null) {
        return null;
    }

    // The line span the worksheet records, which is all a worksheet has.
    //
    // `Strata` asks the occurrence's own token range now, because it asks a
    // fact about the code. This is the fitted feature, and it stays on the
    // definition the ratings were taken against — `ConfidenceFeatures` says the
    // same thing at the serving end. Changing one without the other is a
    // train/serve mismatch; changing both means a worksheet that records token
    // ranges, and a fresh rating round to fill it.
    $span = $facts->span($startLine, $lineCount);

    if ($span === null || $span[1] <= 0) {
        return null;
    }

    return $facts->statements->literals($span[0], $span[1]) / $span[1];
}

/** One rated finding, with both verdicts and its feature vector. */
final class TcFinding
{
    public function __construct(
        public string $sheet,
        public string $id,
        public string $a,
        public string $b,
        public ConfidenceFeatures $features,
    ) {}

    public function consensus(): string
    {
        return $this->a === $this->b ? $this->a : '';
    }
}

$options = getopt('', ['corpus:', 'pin:', 'sheet:']);

if (!isset($options['corpus'], $options['pin'], $options['sheet']) || !is_string($options['corpus'])
    || !is_string($options['pin'])) {
    fwrite(STDERR, "usage: php bench/train-confidence.php --corpus=<root> --pin=<manifest>"
        . " --sheet=<name>:<raterA>:<raterB>:<sites> [--sheet=...]\n");

    exit(1);
}

$root   = rtrim($options['corpus'], '/');
$pinned = bcb_read_pin($options['pin']);
$sheets = is_array($options['sheet']) ? $options['sheet'] : [$options['sheet']];

/** @var array<string, ?FileFacts> $cache */
$cache = [];
/** @var list<TcFinding> $findings */
$findings = [];
/** @var list<string> $excluded */
$excluded = [];
$rated    = 0;

foreach ($sheets as $sheet) {
    if (!is_string($sheet)) {
        continue;
    }

    $parts = explode(':', $sheet);

    if (count($parts) !== 4) {
        fwrite(STDERR, "--sheet needs <name>:<raterA>:<raterB>:<sites>\n");

        exit(1);
    }

    [$name, $pathA, $pathB, $pathSites] = $parts;

    $raterA = bcb_read_worksheet($pathA);
    $raterB = bcb_read_worksheet($pathB);
    $sites  = bcb_read_site_table($pathSites);

    foreach ($raterA as $id => $row) {
        $verdictA = strtoupper($row['verdict']);
        $verdictB = strtoupper($raterB[$id]['verdict'] ?? '');

        if (!in_array($verdictA, ['Y', 'N'], true) || !in_array($verdictB, ['Y', 'N'], true)) {
            $excluded[] = $name . ' ' . $id . ' — not rated Y/N by both raters';

            continue;
        }

        $rated++;
        $lead  = $sites[$id][0] ?? null;
        $files = [];

        foreach ($sites[$id] ?? [] as [$path, $_start, $_lines]) {
            $files[$path] = true;
        }

        if ($lead === null) {
            $excluded[] = $name . ' ' . $id . ' — its lead site did not relocate';

            continue;
        }

        if (($pinned[$lead[0]] ?? null) === null) {
            $excluded[] = $name . ' ' . $id . ' — its lead site is not in the pinned manifest';

            continue;
        }

        $share = tc_literal_share($root, $lead[0], $lead[1], $lead[2], $cache);

        if ($share === null) {
            $excluded[] = $name . ' ' . $id . ' — its lead span holds no significant token';

            continue;
        }

        $findings[] = new TcFinding(
            $name,
            $id,
            $verdictA,
            $verdictB,
            ConfidenceFeatures::describe($row['sites'], count($files) === 1, $row['lines'], $share),
        );
    }
}

/**
 * Count feature buckets per class over a set of (features, label) observations.
 *
 * @param  list<array{0: ConfidenceFeatures, 1: string}> $observations
 * @return array{counts: array<string, array<string, array{0: int, 1: int}>>, totals: array{0: int, 1: int}}
 */
function tc_count(array $observations): array
{
    /** @var array<string, array<string, array{0: int, 1: int}>> $counts */
    $counts = [];
    $yes    = 0;
    $no     = 0;

    foreach ($observations as [$features, $label]) {
        $slot = $label === 'Y' ? 0 : 1;
        $yes += $slot === 0 ? 1 : 0;
        $no  += $slot === 1 ? 1 : 0;

        foreach (ConfidenceFeatures::NAMES as $feature) {
            $bucket = $features->values[$feature] ?? '';
            $counts[$feature][$bucket] ??= [0, 0];
            $counts[$feature][$bucket][$slot]++;
        }
    }

    foreach ($counts as $feature => $buckets) {
        ksort($buckets);
        $counts[$feature] = $buckets;
    }

    ksort($counts);

    return ['counts' => $counts, 'totals' => [$yes, $no]];
}

/** @var list<array{0: ConfidenceFeatures, 1: string}> $perRater */
$perRater = [];
/** @var list<array{0: ConfidenceFeatures, 1: string}> $consensusOnly */
$consensusOnly = [];

foreach ($findings as $finding) {
    $perRater[] = [$finding->features, $finding->a];
    $perRater[] = [$finding->features, $finding->b];

    if ($finding->consensus() !== '') {
        $consensusOnly[] = [$finding->features, $finding->consensus()];
    }
}

$model = tc_count($perRater);
$check = tc_count($consensusOnly);

printf("label corpus\n");
printf("  findings rated Y/N by both raters      %d\n", $rated);
printf("  findings usable (lead site relocated)  %d\n", count($findings));
printf("  rater-label observations               %d\n", count($perRater));
printf("  consensus findings (cross-check set)   %d\n", count($consensusOnly));
printf("  Y / N observations                     %d / %d\n", $model['totals'][0], $model['totals'][1]);

if ($excluded !== []) {
    printf("\nexcluded, and why — never silent:\n");

    foreach ($excluded as $line) {
        printf("  %s\n", $line);
    }
}

printf("\nper-feature counts [Y, N] and log-odds (positive leans duplicated logic)\n");

$trained = new ConfidenceModel($model['counts'], $model['totals'][0], $model['totals'][1]);
$cross   = new ConfidenceModel($check['counts'], $check['totals'][0], $check['totals'][1]);

printf("  %-10s %-12s %8s %8s %10s %10s\n", 'feature', 'bucket', 'Y', 'N', 'log-odds', 'consensus');
printf("  %-10s %-12s %8s %8s %+10.4f %+10.4f\n", 'prior', '—', '', '', $trained->prior(), $cross->prior());

foreach (ConfidenceFeatures::NAMES as $feature) {
    foreach ($model['counts'][$feature] ?? [] as $bucket => [$yes, $no]) {
        printf(
            "  %-10s %-12s %8d %8d %+10.4f %+10.4f\n",
            $feature,
            $bucket,
            $yes,
            $no,
            $trained->logOdds($feature, $bucket),
            $cross->logOdds($feature, $bucket),
        );
    }
}

// Do the two readings order the findings the same way? A ranking is only ever
// read as an order, so that is the question worth asking of the alternative.
$agreements = 0;
$pairs      = 0;

foreach ($findings as $i => $one) {
    foreach ($findings as $j => $other) {
        if ($j <= $i) {
            continue;
        }

        $a = $trained->score($one->features) <=> $trained->score($other->features);
        $b = $cross->score($one->features) <=> $cross->score($other->features);
        $pairs++;
        $agreements += $a === $b ? 1 : 0;
    }
}

printf(
    "\nthe two readings order %d of %d finding pairs identically (%.4f)\n",
    $agreements,
    $pairs,
    $pairs === 0 ? 1.0 : $agreements / $pairs,
);

printf("\n--- ConfidenceModel::TOTALS ---\n    [%d, %d];\n", $model['totals'][0], $model['totals'][1]);
printf("\n--- ConfidenceModel::COUNTS ---\n");

foreach ($model['counts'] as $feature => $buckets) {
    printf("        '%s' => [\n", $feature);

    foreach ($buckets as $bucket => [$yes, $no]) {
        printf("            '%s' => [%d, %d],\n", $bucket, $yes, $no);
    }

    printf("        ],\n");
}
