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
 * The recall oracles: the two independent measurements that decide whether a
 * pair sits inside the winnowing guarantee, and whether the engine's own
 * acceptance rule should have taken it.
 *
 * Separated from bench/run-recall.php so they can be called without running the
 * sweep. That mattered the first time a single missed pair had to be
 * reproduced: the oracles were reachable only by executing a script that
 * measures six corpora, so the smallest available reproduction of one pair was
 * the largest possible one.
 *
 * Neither function consults the engine. The question "should this pair have
 * been reported?" must not be answered by the component that decides whether to
 * report it, which is why the distance here is a textbook two-row Levenshtein
 * and not BandedAligner's banded one.
 */

require_once __DIR__ . '/lib.php';

use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;

/**
 * Width of one packed token in a signature string.
 *
 * Stated here rather than imported from the engine's own `TOKEN_BYTES`, and the
 * reason is the same one that keeps these two oracles off {@see BandedAligner}:
 * the question "should this pair have been reported?" must not be answered by
 * anything being scored. A format constant is a weaker coupling than an
 * algorithm, but it is still a coupling, and this file's whole value is that it
 * shares nothing with the component it judges.
 */
// The width is BCB_TOKEN_BYTES, checked against the product where it is
// defined. This file used to keep its own copy on the argument that it should
// share nothing with what it judges — but what it judges is the aligner, and a
// byte width is not an algorithm. The copy said 5 for as long as the product
// said 8, and an oracle reading every signature three bytes short answers
// "should this pair have been reported?" with noise. Independence that cannot
// be checked is not independence.

/**
 * Both sources tokenized once, as packed signatures with their token counts.
 *
 * The two oracles below opened with the same six lines — build a strategy,
 * tokenize each side, divide each length by the token width — which phpcpd-next
 * reported against itself when it was run over its own `bench/` tree. It was
 * right, so this is the extraction.
 *
 * @return array{string, string, int, int}
 */
function bcb_signature_pair(string $left, string $right, int $minTokensForTokenizer): array
{
    $strategy = new DefaultStrategy(bcb_config(['minTokens' => $minTokensForTokenizer]));
    $a        = $strategy->tokenize($left)->signature;
    $b        = $strategy->tokenize($right)->signature;

    return [$a, $b, intdiv(strlen($a), BCB_TOKEN_BYTES), intdiv(strlen($b), BCB_TOKEN_BYTES)];
}

/** The token at this index of a packed signature. */
function bcb_token_at(string $signature, int $index): string
{
    return substr($signature, $index * BCB_TOKEN_BYTES, BCB_TOKEN_BYTES);
}

/**
 * The longest run of tokens two sources share, by a direct walk over the packed
 * signatures. No hashing, no windows, no sampling, and no engine — this is the
 * number that decides whether a pair sits inside the winnowing guarantee, so it
 * must not come from anything being scored.
 */
function bcb_longest_shared_run(string $left, string $right, int $minTokensForTokenizer): int
{
    [$a, $b, $tokensA, $tokensB] = bcb_signature_pair($left, $right, $minTokensForTokenizer);

    if ($tokensA === 0 || $tokensB === 0) {
        return 0;
    }

    // Classical two-row longest-common-substring over tokens. The units are
    // functions, so this is small by construction.
    $previous = array_fill(0, $tokensB + 1, 0);
    $best     = 0;

    for ($i = 1; $i <= $tokensA; $i++) {
        $current = array_fill(0, $tokensB + 1, 0);
        $tokenA  = bcb_token_at($a, $i - 1);

        for ($j = 1; $j <= $tokensB; $j++) {
            if ($tokenA === bcb_token_at($b, $j - 1)) {
                $current[$j] = $previous[$j - 1] + 1;

                if ($current[$j] > $best) {
                    $best = $current[$j];
                }
            }
        }

        $previous = $current;
    }

    return $best;
}

/**
 * The token-level Levenshtein distance between two sources, and the similarity
 * the engine's acceptance rule reads from it: 1 − distance/longest.
 *
 * A plain textbook two-row dynamic program, deliberately not
 * {@see BandedAligner}: the question "should this pair have been reported?" must
 * not be answered by the component that decides whether to report it.
 *
 * @return array{distance: int, similarity: float, longest: int}
 */
function bcb_token_similarity(string $left, string $right, int $minTokensForTokenizer): array
{
    [$a, $b, $tokensA, $tokensB] = bcb_signature_pair($left, $right, $minTokensForTokenizer);

    $longest = max($tokensA, $tokensB);

    if ($longest === 0) {
        return ['distance' => 0, 'similarity' => 1.0, 'longest' => 0];
    }

    $previous = range(0, $tokensB);

    for ($i = 1; $i <= $tokensA; $i++) {
        $current = [$i];
        $tokenA  = bcb_token_at($a, $i - 1);

        for ($j = 1; $j <= $tokensB; $j++) {
            $current[$j] = min(
                $previous[$j] + 1,
                $current[$j - 1] + 1,
                $previous[$j - 1] + ($tokenA === bcb_token_at($b, $j - 1) ? 0 : 1),
            );
        }

        $previous = $current;
    }

    $distance = $previous[$tokensB];

    return [
        'distance'   => $distance,
        'similarity' => 1.0 - ($distance / $longest),
        'longest'    => $longest,
    ];
}
