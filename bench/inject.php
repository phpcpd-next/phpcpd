#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * BCB-PHP injection CLI.
 *
 * Generates labelled clone variants of a source file and a manifest for scoring.
 * The operators live in injectors.php and are shared with the E2 runner.
 *
 * Usage:
 *   php bench/inject.php <source.php> <output_dir> [--ops type1,type2,...|all]
 *
 * With no --ops, the five E2 operators run, exactly as before. `--ops all` adds
 * the density-parameterized families (gapped_{insert,delete,substitute}_d{1,2,3}
 * and permute_{adjacent,distant}); any subset can be named explicitly.
 *
 * The manifest lists each pair with is_clone — false only for the ssdiff
 * operators, which a well-typed detector should reject — and, for the
 * density-parameterized families, a record of every individual edit: its byte
 * offsets in both files and the exact bytes before and after. That is what lets
 * bench/check-manifest.php re-derive each variant and compare it byte-for-byte
 * against what is on disk, so a recall number is known to have been measured
 * against the mutations the manifest claims.
 */

require_once __DIR__ . '/injectors.php';
require_once __DIR__ . '/harness.php';

$arguments = bcb_argv();
$count     = count($arguments);

if ($count < 3) {
    fwrite(STDERR, "Usage: php bench/inject.php <source.php> <output_dir> [--ops op1,op2,...|all]\n");
    fwrite(STDERR, "Operators: " . implode(', ', bcb_all_operator_names()) . "\n");

    exit(1);
}

$source    = $arguments[1];
$outputDir = $arguments[2];
$ops       = array_keys(bcb_operators());

for ($i = 3; $i < $count; $i++) {
    if ($arguments[$i] === '--ops' && isset($arguments[$i + 1])) {
        $ops = $arguments[$i + 1] === 'all' ? bcb_all_operator_names() : explode(',', $arguments[$i + 1]);
    }
}

if (!is_file($source)) {
    fwrite(STDERR, "Source file not found: $source\n");

    exit(1);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0o755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create output dir: $outputDir\n");

    exit(1);
}

$code     = (string) file_get_contents($source);
$baseName = pathinfo($source, PATHINFO_FILENAME);
$baseFile = $outputDir . '/' . $baseName . '_base.php';
file_put_contents($baseFile, $code);

$known     = bcb_all_operator_names();
$manifest  = [];
$skipped   = 0;
$exitCode  = 0;

foreach ($ops as $op) {
    if (!in_array($op, $known, true)) {
        fwrite(STDERR, "Unknown operator: $op\n");
        $exitCode = 1;

        continue;
    }

    $result = bcb_inject($code, $op);

    // An operator with nowhere to apply is reported and dropped, never written
    // as a pair. Writing the unchanged base under a variant's name would put a
    // Type-1 identity pair in the manifest wearing a gapped operator's label,
    // and every recall number computed from it would be measuring nothing.
    if (!$result['eligible']) {
        printf("  %-22s skipped — %s\n", $op, $result['reason']);
        $skipped++;

        continue;
    }

    $outFile = $outputDir . '/' . $baseName . '_' . $op . '.php';
    file_put_contents($outFile, $result['code']);

    $entry = [
        'base'           => basename($baseFile),
        'variant'        => basename($outFile),
        'operator'       => $op,
        'is_clone'       => !str_starts_with($op, 'ssdiff'),
        'base_sha256'    => hash('sha256', $code),
        'variant_sha256' => hash('sha256', $result['code']),
        'injections'     => $result['injections'],
    ];

    if (str_starts_with($op, 'ssdiff')) {
        $entry['note'] = '--fuzzy reports this as a clone (false positive); --type-anchored rejects it';
    }

    $manifest[] = $entry;

    printf(
        "  %-22s → %s (%d recorded edit%s)\n",
        $op,
        basename($outFile),
        count($result['injections']),
        count($result['injections']) === 1 ? '' : 's',
    );
}

$manifestPath = $outputDir . '/manifest.json';
file_put_contents(
    $manifestPath,
    json_encode(
        ['version' => 2, 'source' => basename($source), 'pairs' => $manifest],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ) . "\n",
);

printf("  manifest → %s (%d pairs, %d skipped)\n", $manifestPath, count($manifest), $skipped);

exit($exitCode);
