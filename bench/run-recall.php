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
 * Recall curves for the density-parameterized injector families (M3).
 *
 * Two curves, both at *function* granularity, both against the M0 injectors:
 *
 *   1. **recall vs edits per 100 tokens** — the `gapped_{insert,delete,
 *      substitute}_d{1,2,3}` family. Every edit breaks the exact run a k-gram
 *      seed is drawn from, so the surviving runs shorten as the edit density
 *      rises and the clone eventually falls below the seed length. That is the
 *      structural weakness of any seed-and-extend method, and a curve is the
 *      only honest way to report it — a single number would hide the part that
 *      matters.
 *
 *   2. **recall vs permutation distance** — the `permute_{adjacent,distant}`
 *      family. This one carries a decision: it measures the sub-K displacement
 *      class that M2 audit ruling A put out of contract for seeded detection,
 *      and M4 revisits the removal of the TokenBag engine if that loss is
 *      material. So TokenBag is scored here beside the unified engine rather
 *      than assumed to be redundant.
 *
 * **The guaranteed region is a gate, not a curve point.** The winnowing
 * guarantee (Schleimer et al. 2003, as configured by {@see Winnower}) promises
 * that every exact common run of at least S = ⌈minTokens/2⌉ tokens is seeded.
 * A pair with at most one divergence whose longest surviving exact run reaches S
 * is therefore inside the guarantee, and recall there must be 100% — anything
 * less is a defect in the implementation, not a property of the design. The
 * longest surviving run is recomputed here by a direct token walk over the two
 * signatures, independently of any engine, so the region is decided by the
 * source rather than by the detector being scored.
 *
 * That region is reported **split in two**, because plan §1 contains two rules
 * that meet here and do not agree. Seeding is one: a run of S tokens is found.
 * *Acceptance* is the other: Stage D4 reports a candidate only at token-level
 * similarity ≥ 1 − RATIO = 0.85 and span ≥ minTokens. A single divergence can be
 * large enough to be seeded on both flanks and still take the pair under the
 * similarity threshold — deleting a 19-token statement from a 93-token function
 * leaves a 38-token exact run (seeded, well over S = 25) at similarity 0.796
 * (rejected, and correctly so under RATIO). Such a pair is inside the guarantee
 * as the gate's wording reads it and outside the engine's own contract for what
 * a clone *is*.
 *
 * So both numbers are reported, and the gate is placed on the first:
 *
 *   - **seeded and within the acceptance contract** — similarity and span
 *     recomputed here, independently — recall must be 100%;
 *   - **seeded but outside the acceptance contract** — a rejection here is the
 *     RATIO rule doing its job, and is counted and shown rather than folded into
 *     either number.
 *
 * The similarity used for the split is a plain textbook Levenshtein over the
 * token signatures, not {@see BandedAligner}'s banded one, so the engine is not
 * consulted about whether the engine should have found something.
 *
 * Usage:
 *   php bench/run-recall.php [<corpus> ...] [--sample=N] [--min-tokens=N]
 *                            [--sample-lines=N] [--sample-tokens=N]
 *
 * ## Comparing two thresholds, and why it needs `--sample-tokens`
 *
 * Selection carries a token floor so that a unit which cannot be reported at
 * all is not counted as a detector failure. That floor defaulted to
 * `--min-tokens`, which is right for one run and wrong for two: raising the
 * threshold then selects a *different set of functions*, and a recall figure
 * compared across thresholds reports the difference between two populations as
 * if it were the engine's. It is the same mistake the line floor was
 * introduced to fix, on the other axis.
 *
 * So the floor is separable. `--sample-tokens=N` pins it, and a comparison sets
 * it once — at or above the highest threshold compared, so every selected unit
 * stays reportable at every threshold — and then varies `--min-tokens` alone.
 * Left unset it is `--min-tokens`, which is exactly the previous behaviour, so
 * every recorded single-threshold number stands unchanged.
 *
 * With no corpus, every directory under bench/corpus is scored. Exit 0 only if
 * the guaranteed region is at 100% recall for the unified engine.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/injectors.php';

use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\BandedAligner;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\Winnower;

$sample       = 60;
$minTokens    = 50;
$sampleLines  = 9;
$sampleTokens = null;
$dirs      = [];

foreach (array_slice(bcb_argv(), 1) as $argument) {
    if (str_starts_with($argument, '--sample-tokens=')) {
        $sampleTokens = max(1, (int) substr($argument, strlen('--sample-tokens=')));

        continue;
    }

    if (str_starts_with($argument, '--sample-lines=')) {
        $sampleLines = max(1, (int) substr($argument, strlen('--sample-lines=')));

        continue;
    }

    if (str_starts_with($argument, '--sample=')) {
        $sample = max(1, (int) substr($argument, strlen('--sample=')));

        continue;
    }

    if (str_starts_with($argument, '--min-tokens=')) {
        $minTokens = (int) substr($argument, strlen('--min-tokens='));

        continue;
    }

    $dirs[] = $argument;
}

if ($dirs === []) {
    foreach (glob(__DIR__ . '/corpus/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $dirs[] = $dir;
    }
}

bcb_require_dirs($dirs);

if ($minTokens < Winnower::MINIMUM_MIN_TOKENS) {
    fwrite(STDERR, sprintf(
        "--min-tokens=%d is below the unified engine's floor of %d.\n",
        $minTokens,
        Winnower::MINIMUM_MIN_TOKENS,
    ));

    exit(1);
}

// Unset means "track --min-tokens", which is what every recorded
// single-threshold number was measured with.
$sampleTokens ??= $minTokens;

// A unit below the reporting threshold cannot be reported at all, so counting
// it as a miss would measure the sample rather than the detector — the very
// invariant the floor exists to hold. Refused rather than warned, because a
// recall number taken this way looks like a result.
if ($sampleTokens < $minTokens) {
    fwrite(STDERR, sprintf(
        "--sample-tokens=%d is below --min-tokens=%d: the sample would hold units\n"
        . "that cannot be reported at any threshold, and every one would count as a\n"
        . "miss. Pin it at or above the highest threshold being compared.\n",
        $sampleTokens,
        $minTokens,
    ));

    exit(1);
}

$guaranteeThreshold = Winnower::guaranteeThresholdFor($minTokens);

/** The engines scored. TokenBag is here because the permutation curve decides its fate. */
$engines = ['unified', 'rabin-karp', 'tokenbag'];

require_once __DIR__ . '/recall-oracles.php';

/** Does this engine call the two sources a clone? */
function bcb_detects(string $base, string $variant, string $algorithm, int $minTokens): bool
{
    $dir = sys_get_temp_dir() . '/bcb_recall_' . uniqid('', true);
    @mkdir($dir, 0777, true);

    $a = $dir . '/base.php';
    $b = $dir . '/variant.php';
    file_put_contents($a, $base);
    file_put_contents($b, $variant);

    try {
        $map = bcb_detect([$a, $b], ['algorithm' => $algorithm, 'minTokens' => $minTokens, 'minLines' => 1]);
        $found = $map->count() > 0;
    } catch (Throwable) {
        $found = false;
    }

    @unlink($a);
    @unlink($b);
    @rmdir($dir);

    return $found;
}

$operators = [...bcb_gapped_operator_names(), ...bcb_permutation_operator_names()];

/** @var array<string, array<string, array{detected:int, pairs:int}>> $tally operator -> engine -> counts */
$tally = [];
/** @var array<string, array{sum:float, n:int}> $density operator -> edits per 100 tokens */
$density = [];
/** @var array<string, array{detected:int, pairs:int}> $guaranteed engine -> counts, seeded AND within the acceptance contract */
$guaranteed = [];
/** @var array<string, array{detected:int, pairs:int}> $seededOnly engine -> counts, seeded but outside the acceptance contract */
$seededOnly = [];
/** @var list<array{corpus:string, operator:string, run:int, tokens:int, similarity:float}> $guaranteedMisses */
$guaranteedMisses = [];

foreach ($operators as $operator) {
    foreach ($engines as $engine) {
        $tally[$operator][$engine] = ['detected' => 0, 'pairs' => 0];
    }

    $density[$operator] = ['sum' => 0.0, 'n' => 0];
}

foreach ($engines as $engine) {
    $guaranteed[$engine] = ['detected' => 0, 'pairs' => 0];
    $seededOnly[$engine] = ['detected' => 0, 'pairs' => 0];
}

printf(
    "Recall curves — sample %d functions per corpus of >= %d lines and >= %d tokens,\n"
    . "--min-tokens=%d (S=%d, K=%d)\n\n",
    $sample,
    $sampleLines,
    $sampleTokens,
    $minTokens,
    $guaranteeThreshold,
    Winnower::SEED_LENGTH,
);

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        fwrite(STDERR, "  SKIP: " . basename($dir) . " not fetched\n");

        continue;
    }

    // Units are whole functions, long enough that a miss means the detector
    // missed rather than the unit being under the threshold.
    $units = [];

    foreach (bcb_files($dir) as $file) {
        $code = (string) file_get_contents($file);

        foreach (bcb_extract_functions($code) as $function) {
            $unit = "<?php\n" . $function;

            // Both conditions, because they answer different questions and
            // dropping either one breaks the experiment.
            //
            // The token floor is the invariant the comment above states: a unit
            // below the reporting threshold cannot be reported at all, so a
            // "miss" on one measures the sample, not the detector. Selecting by
            // lines alone admitted units of thirty tokens against a floor of
            // fifty and counted every one of them as an engine failure.
            //
            // The count is frozen rather than the encoder's, so that the
            // population stays put when the encoder changes and a before-and-
            // after comparison is of one sample rather than two.
            if (
                bcb_line_count($unit) >= $sampleLines
                && bcb_frozen_token_count($unit) >= $sampleTokens
                && bcb_parses($unit)
            ) {
                $units[] = $unit;
            }

            if (count($units) >= $sample) {
                break 2;
            }
        }
    }

    printf("  %-16s %d functions sampled\n", basename($dir), count($units));

    foreach ($units as $unit) {
        $baseTokens = bcb_token_count($unit);

        foreach ($operators as $operator) {
            $injected = bcb_inject($unit, $operator);

            if (!$injected['eligible'] || $injected['code'] === $unit) {
                continue;
            }

            $variant = $injected['code'];

            if (!bcb_parses($variant)) {
                continue;
            }

            $spec  = bcb_operator_spec($operator);
            $edits = $spec['edits'] ?? 1;

            $density[$operator] = [
                'sum' => $density[$operator]['sum'] + $edits / max(1, $baseTokens) * 100,
                'n'   => $density[$operator]['n'] + 1,
            ];

            $run = bcb_longest_shared_run($unit, $variant, $minTokens);

            // Seeded: at most one divergence, and a surviving exact run at least
            // as long as the winnowing threshold.
            $seeded = ($spec['family'] ?? '') === 'gapped' && $edits === 1 && $run >= $guaranteeThreshold;

            // Within the engine's own acceptance contract, recomputed here: a
            // span of at least minTokens at similarity at least 1 − RATIO. A
            // seeded pair that fails this is one the design refuses on purpose.
            $measured   = $seeded ? bcb_token_similarity($unit, $variant, $minTokens) : null;
            $acceptable = $measured !== null
                && $measured['longest'] >= $minTokens
                && $measured['similarity'] >= 1.0 - BandedAligner::RATIO;

            foreach ($engines as $engine) {
                $found = bcb_detects($unit, $variant, $engine, $minTokens);

                $tally[$operator][$engine] = [
                    'detected' => $tally[$operator][$engine]['detected'] + ($found ? 1 : 0),
                    'pairs'    => $tally[$operator][$engine]['pairs'] + 1,
                ];

                if (!$seeded) {
                    continue;
                }

                if ($acceptable) {
                    $guaranteed[$engine] = [
                        'detected' => $guaranteed[$engine]['detected'] + ($found ? 1 : 0),
                        'pairs'    => $guaranteed[$engine]['pairs'] + 1,
                    ];
                } else {
                    $seededOnly[$engine] = [
                        'detected' => $seededOnly[$engine]['detected'] + ($found ? 1 : 0),
                        'pairs'    => $seededOnly[$engine]['pairs'] + 1,
                    ];
                }

                if ($found) {
                    continue;
                }

                if ($engine === 'unified' && $acceptable) {
                    $guaranteedMisses[] = [
                        'corpus'     => basename($dir),
                        'operator'   => $operator,
                        'run'        => $run,
                        'tokens'     => $baseTokens,
                        'similarity' => $measured['similarity'],
                    ];
                }
            }
        }
    }
}

$percent = static fn(int $good, int $n): string => $n > 0
    ? sprintf('%5.1f%%', $good / $n * 100)
    : '    — ';

echo "\n  Curve 1 — recall vs edit density (gapped families)\n\n";
printf("    %-24s %-10s %-7s", 'operator', 'edits/100t', 'pairs');

foreach ($engines as $engine) {
    printf(" %-11s", $engine);
}

echo "\n";

foreach (bcb_gapped_operator_names() as $operator) {
    $n = $density[$operator]['n'];

    printf(
        "    %-24s %-10s %-7d",
        $operator,
        $n > 0 ? sprintf('%.2f', $density[$operator]['sum'] / $n) : '—',
        $tally[$operator][$engines[0]]['pairs'],
    );

    foreach ($engines as $engine) {
        printf(" %-11s", $percent($tally[$operator][$engine]['detected'], $tally[$operator][$engine]['pairs']));
    }

    echo "\n";
}

echo "\n  Curve 2 — recall vs permutation distance\n\n";
printf("    %-24s %-10s %-7s", 'operator', 'distance', 'pairs');

foreach ($engines as $engine) {
    printf(" %-11s", $engine);
}

echo "\n";

foreach (bcb_permutation_operator_names() as $operator) {
    printf(
        "    %-24s %-10s %-7d",
        $operator,
        str_contains($operator, 'adjacent') ? 'adjacent' : 'distant',
        $tally[$operator][$engines[0]]['pairs'],
    );

    foreach ($engines as $engine) {
        printf(" %-11s", $percent($tally[$operator][$engine]['detected'], $tally[$operator][$engine]['pairs']));
    }

    echo "\n";
}

printf(
    "\n  The guaranteed region — one divergence, surviving run >= S = %d,\n"
    . "  and within the acceptance contract (span >= %d at similarity >= %.2f)\n\n",
    $guaranteeThreshold,
    $minTokens,
    1.0 - BandedAligner::RATIO,
);

foreach ($engines as $engine) {
    printf(
        "    %-12s %s  (%d of %d)\n",
        $engine,
        $percent($guaranteed[$engine]['detected'], $guaranteed[$engine]['pairs']),
        $guaranteed[$engine]['detected'],
        $guaranteed[$engine]['pairs'],
    );
}

printf(
    "\n  Seeded but outside the acceptance contract — the divergence is large\n"
    . "  enough to take the pair under similarity %.2f. A rejection here is the\n"
    . "  RATIO rule working, not a recall loss; shown, not folded into the gate.\n\n",
    1.0 - BandedAligner::RATIO,
);

foreach ($engines as $engine) {
    printf(
        "    %-12s reported %s  (%d of %d)\n",
        $engine,
        $percent($seededOnly[$engine]['detected'], $seededOnly[$engine]['pairs']),
        $seededOnly[$engine]['detected'],
        $seededOnly[$engine]['pairs'],
    );
}

if ($guaranteedMisses !== []) {
    echo "\n    unified missed inside the guaranteed region:\n";

    foreach (array_slice($guaranteedMisses, 0, 20) as $miss) {
        printf(
            "      %-16s %-24s longest run %d, base %d tokens, similarity %.3f\n",
            $miss['corpus'],
            $miss['operator'],
            $miss['run'],
            $miss['tokens'],
            $miss['similarity'],
        );
    }
}

echo "\n";

$log = [];

bcb_check(
    $log,
    $guaranteed['unified']['pairs'] > 0,
    'the guaranteed region has members, so the gate below means something',
    sprintf('%d pairs inside the guarantee', $guaranteed['unified']['pairs']),
);

bcb_check(
    $log,
    $guaranteed['unified']['detected'] === $guaranteed['unified']['pairs'],
    'the unified engine recalls the whole guaranteed region',
    sprintf('%d of %d', $guaranteed['unified']['detected'], $guaranteed['unified']['pairs']),
);

exit(bcb_check_summary($log, 'recall check'));
