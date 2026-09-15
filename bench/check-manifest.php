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
 * BCB-PHP manifest checker.
 *
 * A recall number is a claim about mutations: "the detector found 40 of the 50
 * clones we injected". The claim is only as good as the evidence that those 50
 * mutations are the ones the manifest describes — and a manifest is just a file
 * someone can edit, regenerate against different code, or let drift while the
 * variants on disk stay where they were.
 *
 * So this checks, for every pair, that the manifest and the files agree, five
 * independent ways:
 *
 *   1. digest      — base and variant hash to the sha256 the manifest recorded,
 *                    so neither file changed after it was described;
 *   2. re-derivation— running the recorded operator on the recorded base
 *                    reproduces the variant byte-for-byte. This is the whole
 *                    claim: the file on disk *is* what that operator produces;
 *   3. edit records — re-derivation reports the same individual edits, so the
 *                    per-edit records are not decorative;
 *   4. offsets     — every recorded edit's before-bytes are at its offset in the
 *                    base and its after-bytes are at its offset in the variant;
 *   5. parseability— the variant is valid PHP, so a mutation that quietly cut a
 *                    statement in half cannot be measured as a missed clone.
 *
 * Nothing here re-implements an operator: it calls bcb_inject(), the same entry
 * point inject.php used. A checker with its own copy of the mutation logic would
 * only be testing that copy.
 *
 * Usage:
 *   php bench/check-manifest.php <dir> [<dir> ...]
 *
 * Each directory must contain a manifest.json written by bench/inject.php.
 * Exit 0 only if every check on every pair passes.
 */

require_once __DIR__ . '/injectors.php';
require_once __DIR__ . '/harness.php';

$dirs = array_slice(bcb_argv(), 1);

if ($dirs === []) {
    fwrite(STDERR, "Usage: php bench/check-manifest.php <dir> [<dir> ...]\n");

    exit(1);
}

/** @var list<array{ok: bool, claim: string, detail: string}> $log */
$log = [];

foreach ($dirs as $dir) {
    $manifestPath = rtrim($dir, '/') . '/manifest.json';

    printf("\n%s\n", $manifestPath);

    if (!is_file($manifestPath)) {
        bcb_check($log, false, 'manifest exists', $manifestPath);

        continue;
    }

    $decoded = json_decode((string) file_get_contents($manifestPath), true);

    if (!is_array($decoded) || !isset($decoded['pairs']) || !is_array($decoded['pairs'])) {
        bcb_check($log, false, 'manifest is JSON with a pairs list', $manifestPath);

        continue;
    }

    if ($decoded['pairs'] === []) {
        // An empty manifest passes every loop below without asserting anything.
        // Silence is not evidence, so say so and fail.
        bcb_check($log, false, 'manifest describes at least one pair', $manifestPath);

        continue;
    }

    foreach ($decoded['pairs'] as $pair) {
        // Everything below came out of json_decode, so nothing is known about
        // its type until asked. A manifest with a number where a filename
        // belongs is a broken manifest and should be reported as one, not
        // coerced into something that happens to run.
        if (
            !is_array($pair)
            || !is_string($pair['base'] ?? null)
            || !is_string($pair['variant'] ?? null)
            || !is_string($pair['operator'] ?? null)
        ) {
            bcb_check($log, false, 'pair records base, variant and operator as strings', (string) json_encode($pair));

            continue;
        }

        $operator    = $pair['operator'];
        $label       = basename($dir) . '/' . $operator;
        $basePath    = rtrim($dir, '/') . '/' . $pair['base'];
        $variantPath = rtrim($dir, '/') . '/' . $pair['variant'];

        if (!is_file($basePath) || !is_file($variantPath)) {
            bcb_check($log, false, $label . ': both files exist', $basePath . ' / ' . $variantPath);

            continue;
        }

        $baseCode    = (string) file_get_contents($basePath);
        $variantCode = (string) file_get_contents($variantPath);

        // 1. Digest — neither file moved under the manifest's feet.
        foreach ([['base', $baseCode], ['variant', $variantCode]] as [$side, $code]) {
            $recordedDigest = $pair[$side . '_sha256'] ?? null;

            if ($recordedDigest === null) {
                continue; // a version-1 manifest, from before digests were recorded
            }

            $actual = hash('sha256', $code);
            bcb_check(
                $log,
                $actual === $recordedDigest,
                sprintf('%s: %s file matches its recorded sha256', $label, $side),
                $actual === $recordedDigest
                    ? ''
                    : 'recorded ' . var_export($recordedDigest, true) . ', found ' . $actual,
            );
        }

        // 2. Re-derivation — the load-bearing check.
        $rederived = bcb_inject($baseCode, $operator);

        bcb_check(
            $log,
            $rederived['eligible'],
            $label . ': the operator still applies to this base',
            $rederived['reason'],
        );

        $identical = $rederived['code'] === $variantCode;
        bcb_check(
            $log,
            $identical,
            $label . ': re-deriving the operator reproduces the variant byte-for-byte',
            $identical ? strlen($variantCode) . ' bytes' : sprintf(
                're-derived %d bytes, on disk %d bytes, first difference at offset %s',
                strlen($rederived['code']),
                strlen($variantCode),
                var_export(bcb_first_difference($rederived['code'], $variantCode), true),
            ),
        );

        // 3. Edit records — the same edits, not merely the same bytes.
        $recorded = is_array($pair['injections'] ?? null) ? $pair['injections'] : [];

        bcb_check(
            $log,
            count($recorded) === count($rederived['injections']),
            $label . ': the manifest records every edit the operator makes',
            sprintf('manifest %d, re-derived %d', count($recorded), count($rederived['injections'])),
        );

        // 4. Offsets — each record points at the bytes it claims, in both files.
        foreach ($recorded as $index => $injection) {
            if (
                !is_array($injection)
                || !is_int($injection['base_offset'] ?? null)
                || !is_string($injection['base_text'] ?? null)
                || !is_int($injection['variant_offset'] ?? null)
                || !is_string($injection['variant_text'] ?? null)
            ) {
                bcb_check($log, false, sprintf('%s: edit %s is fully recorded', $label, (string) $index));

                continue;
            }

            $inBase = substr($baseCode, $injection['base_offset'], strlen($injection['base_text']));
            bcb_check(
                $log,
                $inBase === $injection['base_text'],
                sprintf('%s: edit %s replaces the bytes it says it does in the base', $label, (string) $index),
                $inBase === $injection['base_text'] ? '' : 'found ' . var_export($inBase, true),
            );

            $inVariant = substr($variantCode, $injection['variant_offset'], strlen($injection['variant_text']));
            bcb_check(
                $log,
                $inVariant === $injection['variant_text'],
                sprintf('%s: edit %s landed where it says it did in the variant', $label, (string) $index),
                $inVariant === $injection['variant_text'] ? '' : 'found ' . var_export($inVariant, true),
            );
        }

        // 5. Parseability — a mutation that broke the file is not a clone that
        //    went missing, and must never be scored as one.
        bcb_check($log, bcb_parses($variantCode), $label . ': the variant is parseable PHP');

        // The label a scorer will trust.
        $expectedClone = !str_starts_with($operator, 'ssdiff');
        bcb_check(
            $log,
            ($pair['is_clone'] ?? null) === $expectedClone,
            $label . ': is_clone is labelled correctly for the operator',
            'is_clone=' . var_export($pair['is_clone'] ?? null, true),
        );
    }
}

/** Offset of the first differing byte, or null when the strings are equal. */
function bcb_first_difference(string $a, string $b): ?int
{
    $limit = min(strlen($a), strlen($b));

    for ($i = 0; $i < $limit; $i++) {
        if ($a[$i] !== $b[$i]) {
            return $i;
        }
    }

    return strlen($a) === strlen($b) ? null : $limit;
}

exit(bcb_check_summary($log, 'manifest check'));
