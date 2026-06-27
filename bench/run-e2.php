#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * BCB-PHP E2 — the type lever, in-process.
 *
 * The unit is a FUNCTION, not a whole file. At file scale the surrounding
 * type-free code swamps the few type hints, so type-anchoring can never break the
 * match and the lever is invisible. A type hint is a meaningful fraction of a
 * short function, so per-function injection is what actually exercises it.
 *
 * For each sampled function that carries an int/float hint:
 *   - inject an ssdiff variant (same shape, int→string): a NON-clone. Detecting
 *     it is a false positive.
 *   - inject a type2 variant (consistent rename): a TRUE clone. Missing it is a
 *     false negative.
 * Candidates are gated to functions that (a) carry an int/float hint and (b) are
 * already >= min-tokens, so ssdiff actually changes something and a "miss" means
 * the detector missed rather than the unit being too short.
 *
 * Then detect each pair twice — once under --fuzzy, once under --type-anchored —
 * and score specificity (1 − FP rate, from ssdiff) and recall (TP rate, from
 * type2). The thesis: among type-load-bearing functions, --type-anchored raises
 * specificity (fuzzy is a 0% floor — it falls for every ssdiff) at no recall cost.
 * The lift is a property of typed FUNCTIONS; a corpus feels it in proportion to
 * how many it has — i.e. its type-density (measured separately).
 *
 * No binary, no text scraping: detection reads CodeCloneMap objects directly.
 *
 * Usage: php bench/run-e2.php [--sample N] [--min-tokens N] [--operator ssdiff|ssdiff_bool]
 *
 * --operator selects which same-shape-different-type injection to score: ssdiff
 * (int/float -> string, the default, feeds summary.tsv) or ssdiff_bool (bool ->
 * int, feeds summary-ssdiff_bool.tsv). Each pairs with its own eligibility
 * predicate so only functions carrying the relevant type kind are sampled.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/injectors.php';

$opts      = getopt('', ['sample:', 'min-tokens:', 'operator:']);
$sample    = (int) ($opts['sample'] ?? 40);
$minTokens = (int) ($opts['min-tokens'] ?? 20);
$operator  = (string) ($opts['operator'] ?? 'ssdiff');

$ssdiffOps = [
    'ssdiff'      => ['inject' => 'bcb_op_ssdiff',      'eligible' => 'bcb_has_int_type'],
    'ssdiff_bool' => ['inject' => 'bcb_op_ssdiff_bool', 'eligible' => 'bcb_has_bool_type'],
];

if (!isset($ssdiffOps[$operator])) {
    fwrite(STDERR, "Unknown --operator '$operator' (choose: " . implode(', ', array_keys($ssdiffOps)) . ")\n");

    exit(1);
}

$injectFn   = $ssdiffOps[$operator]['inject'];
$eligibleFn = $ssdiffOps[$operator]['eligible'];

$corpusDir  = __DIR__ . '/corpus';
$resultsDir = __DIR__ . '/results/e2';
@mkdir($resultsDir, 0777, true);

$injection = ['symfony-string', 'symfony-console', 'phpunit', 'php-parser'];
$control   = 'wordpress';

/**
 * Write base + variant to a throwaway dir and report whether the engine calls
 * them a clone under the given mode.
 *
 * @param array{fuzzy?:bool, typeAnchored?:bool} $mode
 */
function bcb_pair_is_clone(string $base, string $variant, int $minTokens, array $mode): bool
{
    $dir = sys_get_temp_dir() . '/bcb_e2_' . uniqid('', true);
    @mkdir($dir, 0777, true);

    $a = $dir . '/base.php';
    $b = $dir . '/variant.php';
    file_put_contents($a, $base);
    file_put_contents($b, $variant);

    $map = bcb_detect([$a, $b], $mode + ['minTokens' => $minTokens]);

    @unlink($a);
    @unlink($b);
    @rmdir($dir);

    return $map->count() > 0;
}

/** @return array{corpus:string, pairs:int, fp_fuzzy:int, fp_anchored:int, miss_fuzzy:int, miss_anchored:int} */
function bcb_run_corpus(string $name, string $dir, int $sample, int $minTokens, string $injectFn, string $eligibleFn): array
{
    $row = [
        'corpus'        => $name,
        'pairs'         => 0,
        'fp_fuzzy'      => 0,
        'fp_anchored'   => 0,
        'miss_fuzzy'    => 0,
        'miss_anchored' => 0,
    ];

    if (!is_dir($dir)) {
        fwrite(STDERR, "  SKIP: $name not fetched\n");

        return $row;
    }

    // Candidates are individual functions (wrapped in <?php so they tokenize) that
    // carry an int/float hint — the units where the type lever can actually fire.
    $candidates = [];

    foreach (bcb_files($dir, ['vendor', 'node_modules', 'tests', 'test', 'Tests']) as $file) {
        $code = (string) file_get_contents($file);

        foreach (bcb_extract_functions($code) as $fn) {
            $unit = "<?php\n" . $fn;

            // Keep only functions that carry an int/float hint AND are long enough
            // to form a clone, so a "miss" means the detector missed — not that the
            // unit was shorter than --min-tokens.
            if ($eligibleFn($unit) && bcb_token_count($unit) >= $minTokens) {
                $candidates[] = $unit;
            }

            if (count($candidates) >= $sample) {
                break 2;
            }
        }
    }

    foreach ($candidates as $code) {
        $variant = $injectFn($code);
        $type2   = bcb_op_type2($code);
        $row['pairs']++;

        // the ssdiff-family variant is NOT a clone → a detected clone is a false positive.
        if (bcb_pair_is_clone($code, $variant, $minTokens, ['fuzzy' => true])) {
            $row['fp_fuzzy']++;
        }

        if (bcb_pair_is_clone($code, $variant, $minTokens, ['typeAnchored' => true])) {
            $row['fp_anchored']++;
        }

        // type2 IS a clone → a missed pair is a false negative (recall loss).
        if (!bcb_pair_is_clone($code, $type2, $minTokens, ['fuzzy' => true])) {
            $row['miss_fuzzy']++;
        }

        if (!bcb_pair_is_clone($code, $type2, $minTokens, ['typeAnchored' => true])) {
            $row['miss_anchored']++;
        }
    }

    return $row;
}

$rows = [];

fwrite(STDERR, "operator: $operator\n");

foreach ([...$injection, $control] as $name) {
    fwrite(STDERR, "=== E2: $name ===\n");
    $rows[] = bcb_run_corpus($name, $corpusDir . '/' . $name, $sample, $minTokens, $injectFn, $eligibleFn);
}

// --- report ---

$pct = static fn (int $good, int $n): float => $n > 0 ? round($good / $n * 100, 1) : 100.0;

printf(
    "\n%-18s  %5s  %18s  %18s  %18s\n",
    'corpus',
    'pairs',
    'specificity(fuzzy)',
    'specificity(anchor)',
    'lift',
);
printf("%s\n", str_repeat('-', 86));

$tsv = ["corpus\tpairs\tspec_fuzzy\tspec_anchored\tlift\trecall_fuzzy\trecall_anchored"];

foreach ($rows as $r) {
    $specFuzzy  = $pct($r['pairs'] - $r['fp_fuzzy'], $r['pairs']);
    $specAnchor = $pct($r['pairs'] - $r['fp_anchored'], $r['pairs']);
    $recFuzzy   = $pct($r['pairs'] - $r['miss_fuzzy'], $r['pairs']);
    $recAnchor  = $pct($r['pairs'] - $r['miss_anchored'], $r['pairs']);
    $lift       = round($specAnchor - $specFuzzy, 1);

    printf(
        "%-18s  %5d  %16.1f%%  %16.1f%%  %+7.1f%%   (recall %.0f%%→%.0f%%)\n",
        $r['corpus'],
        $r['pairs'],
        $specFuzzy,
        $specAnchor,
        $lift,
        $recFuzzy,
        $recAnchor,
    );

    $tsv[] = implode("\t", [$r['corpus'], $r['pairs'], $specFuzzy, $specAnchor, $lift, $recFuzzy, $recAnchor]);
}

$outName = $operator === 'ssdiff' ? 'summary.tsv' : 'summary-' . $operator . '.tsv';
file_put_contents($resultsDir . '/' . $outName, implode("\n", $tsv) . "\n");

echo "\nspecificity = 1 − false-positive rate on ssdiff (same-shape-different-type non-clones).\n";
echo "recall      = true-positive rate on type2 (renamed true clones) — stays flat fuzzy→anchored.\n";
echo "lift        = specificity(--type-anchored) − specificity(--fuzzy). fuzzy is the 0% floor.\n";
echo "The lift measures type-anchoring on type-load-bearing functions; a corpus benefits in\n";
echo "proportion to its type-density (how many such functions it has — see measure-density).\n";
echo 'Results: ' . $resultsDir . '/' . $outName . "\n";
