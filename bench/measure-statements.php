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
 * The statement-length distribution — ruling R's derivation instrument for k.
 *
 * Ruling R constraint 1: *"Bags of small-k raw shingles, not token unigrams. k
 * sits below statement length so a statement swap barely perturbs the multiset
 * while unrelated same-vocabulary functions share almost nothing. k needs a
 * derivation: measured statement-length distribution across the corpora
 * (fixture r1's swapped statements are 7 tokens; the permutation families are
 * the instrument), recorded in the packet."*
 *
 * ## What "below statement length" has to mean, to be derivable
 *
 * A shingle of k tokens either lies wholly inside one statement or straddles a
 * boundary between two. Only the straddling ones change when two statements are
 * swapped, so the perturbation a swap causes is bounded by how many shingles
 * straddle — and a statement of L tokens contributes L − k + 1 interior shingles
 * and k − 1 straddling ones. Two consequences fix k without any taste:
 *
 *   - **k ≤ L for every statement that must survive a swap**, or that statement
 *     contributes *no* interior shingle at all and the swap perturbs 100 % of
 *     its shingles. This is a floor on what the permutation families can detect
 *     and it is why r1's 7-token statements are the known hard case.
 *   - **k as large as that allows**, because a shorter shingle is shared by more
 *     unrelated code: a bag of 2-grams over PHP is close to a bag of tokens.
 *
 * So the derivation is a percentile of the measured distribution, and this tool
 * reports the distribution rather than a chosen number.
 *
 * Statements come from `bcb_function_statements()` — the same segmentation the
 * permutation injectors edit, so the measurement is of the units the families
 * actually move, not of a second definition of "statement".
 *
 * Usage:
 *   php bench/measure-statements.php [<dir> ...] [--max-files=N]
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/injectors.php';

use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;

/**
 * Significant-token length of every top-level statement of every function body.
 *
 * The engine's own encoder counts the tokens, so a "7-token statement" here is
 * seven tokens to the fingerprinter as well — the two would otherwise differ on
 * whitespace, comments and single-character punctuation.
 *
 * @return list<int>
 */
function ms_statement_lengths(string $code, DefaultStrategy $encoder): array
{
    $lengths = [];

    foreach (bcb_function_statements($code) as $function) {
        foreach ($function['statements'] as $statement) {
            $text = substr($code, $statement['start'], $statement['end'] - $statement['start']);
            // The encoder needs a complete unit to tokenize; a bare statement is
            // one once it is given an opening tag.
            $tokens = $encoder->tokenize('<?php ' . $text);
            $length = intdiv(strlen($tokens->signature), BCB_TOKEN_BYTES);

            if ($length > 0) {
                $lengths[] = $length;
            }
        }
    }

    return $lengths;
}

/**
 * @param  list<int> $sorted
 */
function ms_percentile(array $sorted, float $p): int
{
    if ($sorted === []) {
        return 0;
    }

    $index = (int) floor($p * (count($sorted) - 1));

    return $sorted[$index];
}

$arguments = bcb_argv();
$maxFiles  = 400;
$roots     = [];

foreach (array_slice($arguments, 1) as $argument) {
    if (str_starts_with($argument, '--max-files=')) {
        $maxFiles = max(1, (int) substr($argument, 12));
    } else {
        $roots[] = $argument;
    }
}

if ($roots === []) {
    $roots = [
        __DIR__ . '/corpus/php-parser',
        __DIR__ . '/corpus/symfony-string',
        __DIR__ . '/corpus/phpunit',
        __DIR__ . '/corpus/firefly-iii',
    ];
}

$encoder = new DefaultStrategy(new StrategyConfiguration(5, 70, Normalization::Raw, 1.0));

printf("Statement lengths, in significant tokens — ruling R's derivation of k\n\n");
printf("  %-16s %8s %6s %6s %6s %6s %6s %6s\n", 'corpus', 'stmts', 'p1', 'p5', 'p10', 'p25', 'p50', 'p90');

$all = [];

foreach ($roots as $root) {
    $lengths = [];
    $files   = bcb_files($root);
    $files   = array_slice($files, 0, $maxFiles);

    foreach ($files as $file) {
        $code = file_get_contents($file);

        if ($code === false) {
            continue;
        }

        foreach (ms_statement_lengths($code, $encoder) as $length) {
            $lengths[] = $length;
            $all[]     = $length;
        }
    }

    sort($lengths);

    printf(
        "  %-16s %8d %6d %6d %6d %6d %6d %6d\n",
        basename($root),
        count($lengths),
        ms_percentile($lengths, 0.01),
        ms_percentile($lengths, 0.05),
        ms_percentile($lengths, 0.10),
        ms_percentile($lengths, 0.25),
        ms_percentile($lengths, 0.50),
        ms_percentile($lengths, 0.90),
    );
}

sort($all);

printf(
    "  %-16s %8d %6d %6d %6d %6d %6d %6d\n\n",
    'ALL',
    count($all),
    ms_percentile($all, 0.01),
    ms_percentile($all, 0.05),
    ms_percentile($all, 0.10),
    ms_percentile($all, 0.25),
    ms_percentile($all, 0.50),
    ms_percentile($all, 0.90),
);

// The permutation families move whole statements, so what a candidate k has to
// survive is the *shortest* statement they move. Reported per k as the share of
// statements that contribute at least one interior shingle.
printf("  k   statements with an interior shingle (length >= k)\n");

foreach ([3, 4, 5, 6, 7, 8, 10, 12] as $k) {
    $ok = 0;

    foreach ($all as $length) {
        if ($length >= $k) {
            $ok++;
        }
    }

    printf("  %2d   %6.2f%%\n", $k, $all === [] ? 0 : 100 * $ok / count($all));
}
