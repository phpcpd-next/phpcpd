#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * BCB-PHP §7.4 — type-density × clone-density per corpus.
 *
 * Type-density (signature-scoped) = typed declaration positions / all declaration
 *   positions, where a "declaration position" is a function/method PARAMETER or a
 *   RETURN type. This is parsed from token context (not leaky string heuristics):
 *   each parameter and each return slot is counted once, and counted as "typed"
 *   only when a type token precedes the variable / follows the return colon.
 *   Scope is deliberately the function-signature surface — well-defined and
 *   tokenizable — rather than guessing property declarations.
 *
 * Clone-density = duplicated_lines / total_lines, from the real Rabin–Karp engine
 *   (bcb_detect), not a scrape of CLI text.
 *
 * Usage:
 *   php bench/measure-density.php [--csv] [corpus_dir ...]
 *   (no dirs → every subdirectory of bench/corpus/)
 */

require_once __DIR__ . '/lib.php';

$csv   = in_array('--csv', $argv, true);
$roots = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $a): bool => $a !== '--csv',
));

if ($roots === []) {
    $base = __DIR__ . '/corpus';

    if (!is_dir($base)) {
        fwrite(STDERR, "No corpus_dir given and bench/corpus/ not found. Run bench/fetch.sh first.\n");
        exit(1);
    }

    $roots = array_values(array_filter((array) glob($base . '/*'), 'is_dir'));
}

/**
 * Count typed vs. total signature declaration positions (parameters + returns).
 *
 * @return array{typed:int, total:int}
 */
function bcb_type_density_file(string $file): array
{
    $code = @file_get_contents($file);

    if ($code === false) {
        return ['typed' => 0, 'total' => 0];
    }

    $tokens = token_get_all($code);
    $n      = count($tokens);
    $typed  = 0;
    $total  = 0;

    // Modifiers that may precede a (promoted) parameter type but are not types.
    $modifiers = [T_PUBLIC => true, T_PROTECTED => true, T_PRIVATE => true, T_READONLY => true];

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
            continue;
        }

        // Advance to the opening '(' of the parameter list.
        $j = $i + 1;

        while ($j < $n && $tokens[$j] !== '(') {
            $j++;
        }

        if ($j >= $n) {
            continue;
        }

        // Split the parameter list into top-level params (depth tracks nested ()).
        $depth  = 0;
        $params = [];
        $cur    = [];
        $close  = $j;

        for ($k = $j; $k < $n; $k++) {
            $t = $tokens[$k];

            if ($t === '(') {
                $depth++;

                if ($depth === 1) {
                    continue;
                }
            } elseif ($t === ')') {
                $depth--;

                if ($depth === 0) {
                    if ($cur !== []) {
                        $params[] = $cur;
                    }

                    $close = $k;
                    break;
                }
            }

            if ($depth === 1 && $t === ',') {
                $params[] = $cur;
                $cur      = [];

                continue;
            }

            $cur[] = $t;
        }

        foreach ($params as $param) {
            if (!bcb_param_has_variable($param)) {
                continue; // empty / not a real parameter
            }

            $total++;

            if (bcb_param_is_typed($param, $modifiers)) {
                $typed++;
            }
        }

        // Return type: the first ':' after ')' and before the body '{' or ';'.
        for ($r = $close + 1; $r < $n; $r++) {
            $t = $tokens[$r];

            if ($t === '{' || $t === ';') {
                $total++; // untyped return slot
                break;
            }

            if ($t === ':') {
                $total++;
                $typed += bcb_return_is_typed($tokens, $n, $r) ? 1 : 0;
                break;
            }
        }
    }

    return ['typed' => $typed, 'total' => $total];
}

/** @param list<array{0:int,1:string,2:int}|string> $param */
function bcb_param_has_variable(array $param): bool
{
    foreach ($param as $t) {
        if (is_array($t) && $t[0] === T_VARIABLE) {
            return true;
        }
    }

    return false;
}

/**
 * A parameter is typed if a type token appears before its variable, skipping
 * visibility/readonly modifiers (constructor promotion).
 *
 * @param list<array{0:int,1:string,2:int}|string> $param
 * @param array<int, true> $modifiers
 */
function bcb_param_is_typed(array $param, array $modifiers): bool
{
    foreach ($param as $t) {
        if (is_array($t)) {
            if ($t[0] === T_VARIABLE) {
                return false; // reached the variable with no type seen
            }

            if ($t[0] === T_WHITESPACE || isset($modifiers[$t[0]])) {
                continue;
            }

            return true; // any other leading token is part of a type
        }

        if ($t === '?' || $t === '|' || $t === '&') {
            return true; // nullable / union / intersection marker
        }
    }

    return false;
}

/** @param list<array{0:int,1:string,2:int}|string> $tokens */
function bcb_return_is_typed(array $tokens, int $n, int $colon): bool
{
    for ($s = $colon + 1; $s < $n; $s++) {
        if (is_array($tokens[$s]) && $tokens[$s][0] === T_WHITESPACE) {
            continue;
        }

        return true; // a type follows the colon
    }

    return false;
}

/** @return array{name:string, files:int, type_density:float, clone_density:float} */
function bcb_measure_corpus(string $dir): array
{
    $files = bcb_files($dir);

    if ($files === []) {
        return ['name' => basename($dir), 'files' => 0, 'type_density' => 0.0, 'clone_density' => 0.0];
    }

    $typed = 0;
    $total = 0;

    foreach ($files as $f) {
        $d      = bcb_type_density_file($f);
        $typed += $d['typed'];
        $total += $d['total'];
    }

    $map          = bcb_detect($files, ['minTokens' => 50]);
    $cloneDensity = $map->numberOfLines() > 0
        ? round($map->numberOfDuplicatedLines() / $map->numberOfLines(), 4)
        : 0.0;

    return [
        'name'          => basename($dir),
        'files'         => count($files),
        'type_density'  => $total > 0 ? round($typed / $total, 4) : 0.0,
        'clone_density' => $cloneDensity,
    ];
}

// --- main ---

$results = [];

foreach ($roots as $root) {
    if (!is_dir($root)) {
        fwrite(STDERR, "Skipping (not a dir): $root\n");

        continue;
    }

    fwrite(STDERR, 'Measuring ' . basename($root) . "...\n");
    $results[] = bcb_measure_corpus($root);
}

if ($csv) {
    echo "name,files,type_density,clone_density\n";

    foreach ($results as $r) {
        echo implode(',', [$r['name'], $r['files'], $r['type_density'], $r['clone_density']]) . "\n";
    }

    exit(0);
}

printf("\n%-25s  %12s  %13s  %6s\n", 'corpus', 'type-density', 'clone-density', 'files');
printf("%-25s  %12s  %13s  %6s\n", str_repeat('-', 25), str_repeat('-', 12), str_repeat('-', 13), str_repeat('-', 6));

foreach ($results as $r) {
    printf(
        "%-25s  %11.1f%%  %12.1f%%  %6d\n",
        $r['name'],
        $r['type_density'] * 100,
        $r['clone_density'] * 100,
        $r['files'],
    );
}

// ASCII scatter: type-density (x) × clone-density (y). Unique A,B,C… labels so two
// corpora sharing a first letter (php-parser/phpunit) don't collide on the plot.
$w    = 50;
$h    = 18;
$grid = array_fill(0, $h, array_fill(0, $w, '.'));

foreach ($results as $i => $r) {
    $x = max(0, min($w - 1, (int) round($r['type_density'] * ($w - 1))));
    $y = max(0, min($h - 1, $h - 1 - (int) round($r['clone_density'] * ($h - 1))));

    $grid[$y][$x] = chr(65 + ($i % 26));
}

echo "\n  clone-density (y) vs type-density (x)\n";
echo '  1.0|' . implode('', $grid[0]) . "\n";

for ($y = 1; $y < $h - 1; $y++) {
    echo '     |' . implode('', $grid[$y]) . "\n";
}

echo '  0.0|' . implode('', $grid[$h - 1]) . "\n";
echo '      ' . str_repeat('-', $w) . "\n";
echo '      0.0' . str_repeat(' ', $w - 7) . "1.0\n";
echo "      type-density →\n\n  Key:\n";

foreach ($results as $i => $r) {
    printf("    %s = %s\n", chr(65 + ($i % 26)), $r['name']);
}
