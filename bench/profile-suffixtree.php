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
 * Suffix-tree profiler — answers "where does the time go?" before optimizing.
 *
 * It separates the three phases and, crucially, isolates the approximate-matching
 * edit-distance DP by sweeping --edit-distance over {0, 2, 5}: at ed=0 the DP is
 * trivial (exact match), so findClones(ed=5) − findClones(ed=0) IS the edit-distance
 * cost. If that delta dominates, banding the DP (O(L^2) -> O(L*maxErrors)) is the
 * worthwhile optimization; if construction dominates instead, it is not.
 *
 * Usage: php bench/profile-suffixtree.php [--corpus name] [--max-files N] [--min-tokens N]
 */

require_once __DIR__ . '/lib.php';

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree\ApproximateCloneDetectingSuffixTree;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree\Sentinel;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTreeStrategy;

$opts      = getopt('', ['corpus:', 'max-files:', 'min-tokens:']);
$corpus    = is_string($opts['corpus'] ?? null) ? $opts['corpus'] : 'firefly-iii';
$maxFiles  = (int) (is_string($opts['max-files'] ?? null) ? $opts['max-files'] : '150');
$minTokens = (int) (is_string($opts['min-tokens'] ?? null) ? $opts['min-tokens'] : '50');

$dir = __DIR__ . '/corpus/' . $corpus;

if (!is_dir($dir)) {
    fwrite(STDERR, "Corpus not found: $dir (run bench/fetch.sh)\n");

    exit(1);
}

$files = bcb_files($dir);
sort($files);
$files = array_slice($files, 0, $maxFiles);

fwrite(STDERR, sprintf("Profiling %s: %d files, min-tokens %d\n", $corpus, count($files), $minTokens));

$ms = static fn (float $startNs): float => (hrtime(true) - $startNs) / 1e6;

// --- Phase 1: tokenize (build the token word exactly as production does) ---
$config   = bcb_config(['minTokens' => $minTokens, 'editDistance' => 5, 'headEquality' => 10]);
$strategy = new SuffixTreeStrategy($config);
$result   = new CodeCloneMap();

$t = hrtime(true);
foreach ($files as $file) {
    $strategy->processFile($file, $result);
}
$tokenizeMs = $ms($t);

/** @var list<object> $word */
$word   = (new ReflectionProperty(SuffixTreeStrategy::class, 'word'))->getValue($strategy);
$word[] = new Sentinel();
$n      = count($word);

// --- Phase 2: construct (Ukkonen, O(n)) ---
$t        = hrtime(true);
$tree     = new ApproximateCloneDetectingSuffixTree($word);
$buildMs  = $ms($t);
unset($tree); // a fresh tree per ed run below, so each findClones starts cold

// --- Phase 3: findClones, sweeping edit distance to isolate the DP cost ---
printf("\n%-22s %12s\n", 'phase', 'ms');
printf("%s\n", str_repeat('-', 36));
printf("%-22s %12.1f\n", 'tokenize', $tokenizeMs);
printf("%-22s %12.1f   (n = %d tokens)\n", 'construct (Ukkonen)', $buildMs, $n);

$findMs = [];

foreach ([0, 2, 5] as $ed) {
    $tree = new ApproximateCloneDetectingSuffixTree($word);
    $t    = hrtime(true);
    $clones = $tree->findClones($minTokens, $ed, 10);
    $findMs[$ed] = $ms($t);
    printf("%-22s %12.1f   (%d clones)\n", "findClones ed=$ed", $findMs[$ed], count($clones));
    unset($tree);
}

$dpCost = $findMs[5] - $findMs[0];
$total  = $tokenizeMs + $buildMs + $findMs[5];

printf("\nedit-distance DP cost (findClones ed5 - ed0): %.1f ms\n", $dpCost);
printf("share of a full ed=5 run (tokenize+construct+find): %.0f%%\n", $total > 0 ? $dpCost / $total * 100 : 0.0);
echo "\nIf the DP cost dominates, a banded DP (width 2*maxErrors+1) turns the per-clone\n";
echo "O(L^2) into O(L*maxErrors) with identical results. If construction dominates, it does not.\n";
