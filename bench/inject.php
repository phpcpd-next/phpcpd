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
 *   php bench/inject.php <source.php> <output_dir> [--ops type1,type2,type3,ssdiff]
 *
 * Manifest (output_dir/manifest.json) lists each pair with is_clone — false only
 * for ssdiff, which a well-typed detector should reject.
 */

require_once __DIR__ . '/injectors.php';

if ($argc < 3) {
    fwrite(STDERR, "Usage: php bench/inject.php <source.php> <output_dir> [--ops op1,op2,...]\n");
    exit(1);
}

$source    = $argv[1];
$outputDir = $argv[2];
$ops       = array_keys(bcb_operators());

for ($i = 3; $i < $argc; $i++) {
    if ($argv[$i] === '--ops' && isset($argv[$i + 1])) {
        $ops = explode(',', $argv[$i + 1]);
    }
}

if (!is_file($source)) {
    fwrite(STDERR, "Source file not found: $source\n");
    exit(1);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Could not create output dir: $outputDir\n");
    exit(1);
}

$code     = (string) file_get_contents($source);
$baseName = pathinfo($source, PATHINFO_FILENAME);
$baseFile = $outputDir . '/' . $baseName . '_base.php';
file_put_contents($baseFile, $code);

$operators = bcb_operators();
$manifest  = [];

foreach ($ops as $op) {
    if (!isset($operators[$op])) {
        fwrite(STDERR, "Unknown operator: $op\n");
        continue;
    }

    $variant = $operators[$op]($code);
    $outFile = $outputDir . '/' . $baseName . '_' . $op . '.php';
    file_put_contents($outFile, $variant);

    $entry = [
        'base'     => basename($baseFile),
        'variant'  => basename($outFile),
        'operator' => $op,
        'is_clone' => $op !== 'ssdiff',
    ];

    if ($op === 'ssdiff') {
        $entry['note'] = '--fuzzy reports this as a clone (false positive); --type-anchored rejects it';
    }

    $manifest[] = $entry;
    echo "  $op → " . basename($outFile) . "\n";
}

$manifestPath = $outputDir . '/manifest.json';
file_put_contents($manifestPath, json_encode(['pairs' => $manifest], JSON_PRETTY_PRINT) . "\n");
echo "  manifest → $manifestPath\n";
