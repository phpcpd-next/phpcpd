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
 * Incremental-equivalence check.
 *
 * An incremental index is only worth having if it is invisible: the answer after
 * reusing a cache must be the answer a cold run would have given, not an
 * approximation of it that happens to be cheaper. That is a proposition about
 * purity — a file's encoding and its winnowed fingerprints are functions of its
 * bytes and the configuration alone — and this checks the proposition rather than
 * assuming it.
 *
 * Four runs over a scratch copy of a corpus:
 *
 *   1. cold      — empty cache; everything computed, nothing reused;
 *   2. warm      — nothing touched; everything reused, nothing computed, and the
 *                  report identical to the cold one;
 *   3. warm+edit — one file changed; exactly one file recomputed, the rest reused;
 *   4. cold+edit — the edited corpus from an empty cache, which the run above must
 *                  match byte for byte.
 *
 * Run 4 is the one that makes the check mean something. Without it, runs 1-3 only
 * show the cache being used, not that using it was harmless.
 *
 * Usage:
 *   php bench/check-incremental.php [<dir>] [--algorithm=unified]
 *
 * Exit 0 only if every check passes.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\Cache\IncrementalIndex;
use LucianoPereira\PhpcpdNext\Cache\IndexResult;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Engine;

$dirs      = [];
$algorithm = 'unified';

foreach (array_slice(bcb_argv(), 1) as $arg) {
    if (str_starts_with($arg, '--algorithm=')) {
        $algorithm = substr($arg, strlen('--algorithm='));

        continue;
    }

    $dirs[] = $arg;
}

if ($dirs === []) {
    $dirs = [dirname(__DIR__) . '/src'];
}

bcb_require_dirs($dirs);

const INCREMENTAL_OPTS = ['minTokens' => 70, 'minLines' => 5];

$config = bcb_config(INCREMENTAL_OPTS);
$root   = sys_get_temp_dir() . '/bcb-incremental-' . getmypid();
$work   = $root . '/corpus';

/** Recursively copy a directory's PHP files into the scratch tree. */
function incremental_stage(string $from, string $to): int
{
    $files = bcb_gate_files([$from]);
    $count = 0;

    foreach ($files as $file) {
        $target = $to . '/' . str_replace(['/', '\\'], '_', substr($file, strlen($from)));

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0o755, true);
        }

        copy($file, $target);
        $count++;
    }

    return $count;
}

function incremental_report(CodeCloneMap $map): string
{
    return implode("\n", array_map(
        static fn($clone): string => (string) json_encode($clone->toArray()),
        $map->clones(),
    ));
}

@mkdir($work, 0o755, true);
$staged = 0;

foreach ($dirs as $dir) {
    $staged += incremental_stage($dir, $work);
}

$files = bcb_gate_files([$work]);

printf("Incremental check — %s, %d files staged in a scratch copy\n\n", $algorithm, count($files));

/** @var list<array{ok: bool, claim: string, detail: string}> $log */
$log = [];

$run = static function (string $cacheDir) use ($files, $config, $algorithm): IndexResult {
    return (new IncrementalIndex($cacheDir, 'check', $config, $algorithm))->detect($files);
};

// 1. Cold.
$cold = $run($root . '/cache-a');
printf("  cold        %d reused, %d computed, %d clones\n", $cold->reused, $cold->scanned, $cold->clones->count());

bcb_check(
    $log,
    $cold->reused === 0 && $cold->scanned === count($files),
    'a cold run computes every file and reuses nothing',
    sprintf('%d reused, %d computed, %d files', $cold->reused, $cold->scanned, count($files)),
);

bcb_check(
    $log,
    $cold->clones->count() > 0,
    'the corpus contains clones, so the comparisons below mean something',
    $cold->clones->count() . ' clones',
);

// The index must not change the answer at all, so compare it against the engine
// running with no cache in the picture.
$direct = (new Engine($config, $algorithm))->detect($files);

bcb_check(
    $log,
    incremental_report($cold->clones) === incremental_report($direct),
    'the cold indexed run reports exactly what the plain engine reports',
    sprintf('%d vs %d clones', $cold->clones->count(), $direct->count()),
);

// 2. Warm, nothing touched.
$warm = $run($root . '/cache-a');
printf("  warm        %d reused, %d computed, %d clones\n", $warm->reused, $warm->scanned, $warm->clones->count());

bcb_check(
    $log,
    $warm->reused === count($files) && $warm->scanned === 0,
    'an untouched warm run recomputes nothing at all',
    sprintf('%d reused, %d computed', $warm->reused, $warm->scanned),
);

bcb_check(
    $log,
    incremental_report($warm->clones) === incremental_report($cold->clones),
    'the warm run reports byte-identically to the cold run',
);

// 3. Touch exactly one file. Edited rather than appended-to, so the change is
//    real code the engine has to look at again, not a comment it would drop.
$touched = $files[intdiv(count($files), 2)];
$before  = (string) file_get_contents($touched);
file_put_contents($touched, $before . "\n" . 'function bcb_incremental_probe(): int { return 1; }' . "\n");

$edited = $run($root . '/cache-a');
printf("  warm+edit   %d reused, %d computed, %d clones   (%s)\n", $edited->reused, $edited->scanned, $edited->clones->count(), basename($touched));

bcb_check(
    $log,
    $edited->scanned === 1,
    'touching one file recomputes exactly one file — encoding and fingerprints',
    sprintf('%d computed, %d reused', $edited->scanned, $edited->reused),
);

bcb_check(
    $log,
    $edited->reused === count($files) - 1,
    'every other file is still served from the index',
    sprintf('%d reused of %d others', $edited->reused, count($files) - 1),
);

// 4. The same edited corpus, from scratch. This is the equivalence claim.
$coldAgain = $run($root . '/cache-b');
printf("  cold+edit   %d reused, %d computed, %d clones\n\n", $coldAgain->reused, $coldAgain->scanned, $coldAgain->clones->count());

bcb_check(
    $log,
    incremental_report($edited->clones) === incremental_report($coldAgain->clones),
    'after an edit, the warm run reports byte-identically to a cold run on the same corpus',
    sprintf('%d vs %d clones', $edited->clones->count(), $coldAgain->clones->count()),
);

// Tidy the scratch tree.
foreach ((array) glob($work . '/*') as $file) {
    @unlink((string) $file);
}

foreach (['cache-a', 'cache-b'] as $cache) {
    foreach ((array) glob($root . '/' . $cache . '/*') as $file) {
        @unlink((string) $file);
    }

    @rmdir($root . '/' . $cache);
}

@rmdir($work);
@rmdir($root);

exit(bcb_check_summary($log, 'incremental check'));
