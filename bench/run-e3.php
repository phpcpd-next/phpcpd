#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * BCB-PHP E3 — ecological inconsistent-clone mining, in-process.
 *
 * The previous shell version ran a full-repo suffix-tree scan once PER changed
 * file PER commit (hundreds of process spawns) and parsed text output that never
 * matched. This version scans the repo ONCE through the real engine and reads
 * the gapped (inconsistent / Type-3) clones straight off the CodeCloneMap — the
 * exact output of the R4 isGapped() capability the paper is about.
 *
 * For each inconsistent clone pair it then asks git: were the two copies last
 * touched in DIFFERENT commits? If so the pair is a "diverged copy" candidate —
 * one sibling was maintained, the other left behind, which is precisely where
 * inconsistent clones breed latent bugs.
 *
 * Usage:
 *   php bench/run-e3.php [repo_dir] [--min-tokens N] [--edit-distance N] [--max-files N] [--out file.json]
 *   (default repo_dir = bench/corpus/firefly-iii)
 */

require_once __DIR__ . '/lib.php';

$opts        = getopt('', ['min-tokens:', 'edit-distance:', 'max-files:', 'out:'], $restIdx);
$positional  = array_slice($argv, $restIdx);
$repo        = $positional[0] ?? (__DIR__ . '/corpus/firefly-iii');
$minTokens   = (int) ($opts['min-tokens'] ?? 50);
$editDist    = (int) ($opts['edit-distance'] ?? 3);
$maxFiles    = (int) ($opts['max-files'] ?? 200);
$outFile     = $opts['out'] ?? null;

if (!is_dir($repo . '/.git')) {
    fwrite(STDERR, "Usage: php bench/run-e3.php <git_repo_dir> [...]\n  repo_dir must be a git repository.\n");
    exit(1);
}

$repo  = (string) realpath($repo);
$files = bcb_files($repo, ['vendor', 'node_modules', 'storage', 'bootstrap/cache', 'database/migrations']);

// Sort by most-recently-modified so --max-files keeps the most active code.
usort($files, static fn(string $a, string $b) => filemtime($b) <=> filemtime($a));

if ($maxFiles > 0) {
    $files = array_slice($files, 0, $maxFiles);
}

fwrite(STDERR, sprintf("Scanning %s (%d files, suffixtree ed=%d, min-tokens=%d)...\n", $repo, count($files), $editDist, $minTokens));

$map = bcb_detect($files, [
    'algorithm'    => 'suffixtree',
    'editDistance' => $editDist,
    'minTokens'    => $minTokens,
]);

/**
 * git: [sha, unixTime] of the commit that last touched a path (relative to repo),
 * or null. One call returns both so the staleness gap is cheap.
 *
 * @return array{0:string, 1:int}|null
 */
function bcb_last_commit(string $repo, string $relPath): ?array
{
    $cmd = 'git -C ' . escapeshellarg($repo) . ' log -1 --format=%H%x09%ct -- ' . escapeshellarg($relPath) . ' 2>/dev/null';
    $out = trim((string) shell_exec($cmd));

    if ($out === '' || !str_contains($out, "\t")) {
        return null;
    }

    [$sha, $ts] = explode("\t", $out, 2);

    return [$sha, (int) $ts];
}

$findings = [];
$gapped   = 0;
$seen     = [];

foreach ($map->clones() as $clone) {
    if (!$clone->isGapped()) {
        continue;
    }

    $gapped++;
    $cloneFiles = array_values($clone->files());

    if (count($cloneFiles) < 2) {
        continue;
    }

    $relA = str_replace($repo . '/', '', $cloneFiles[0]->name());
    $relB = str_replace($repo . '/', '', $cloneFiles[1]->name());

    // Dedup by unordered file pair — the same two controllers clone in many spots.
    $key = $relA < $relB ? "$relA\x00$relB" : "$relB\x00$relA";

    if (isset($seen[$key])) {
        continue;
    }

    $seen[$key] = true;

    $commitA = bcb_last_commit($repo, $relA);
    $commitB = bcb_last_commit($repo, $relB);

    if ($commitA === null || $commitB === null || $commitA[0] === $commitB[0]) {
        continue; // same commit (maintained together) or unknown → not a divergence
    }

    // Staleness: how long one copy sat untouched after its sibling was last patched.
    $gapDays = (int) round(abs($commitA[1] - $commitB[1]) / 86400);

    $findings[] = [
        'file_a'        => $relA,
        'line_a'        => $cloneFiles[0]->startLine(),
        'last_commit_a' => substr($commitA[0], 0, 12),
        'file_b'        => $relB,
        'line_b'        => $cloneFiles[1]->startLine(),
        'last_commit_b' => substr($commitB[0], 0, 12),
        'lines'         => $clone->numberOfLines(),
        'staleness_days' => $gapDays,
        'note'          => 'Inconsistent (gapped) clone; copies last touched ' . $gapDays . ' days apart',
    ];
}

// Rank by staleness gap (most-diverged first), then by clone size.
usort($findings, static fn (array $a, array $b): int
    => [$b['staleness_days'], $b['lines']] <=> [$a['staleness_days'], $a['lines']]);

fwrite(STDERR, sprintf("Inconsistent clones: %d; distinct diverged-copy pairs: %d\n", $gapped, count($findings)));

$json = json_encode(['gapped_clones' => $gapped, 'findings' => $findings], JSON_PRETTY_PRINT) . "\n";

if ($outFile !== null) {
    file_put_contents($outFile, $json);
    fwrite(STDERR, "Results: $outFile\n");
}

// Console summary (top candidates, most-diverged first).
printf("\n%-3s  %-40s  %-40s  %5s  %9s\n", '#', 'copy A', 'copy B', 'lines', 'stale(d)');
printf("%s\n", str_repeat('-', 108));

foreach (array_slice($findings, 0, 20) as $i => $f) {
    printf(
        "%-3d  %-40s  %-40s  %5d  %9d\n",
        $i + 1,
        substr($f['file_a'], -40),
        substr($f['file_b'], -40),
        $f['lines'],
        $f['staleness_days'],
    );
}

if ($findings === []) {
    echo "  (no diverged-copy candidates at these thresholds)\n";
}

echo "\nstale(d) = days between the two copies' last commits — a high gap means one copy was\n";
echo "maintained long after its clone sibling, the canonical inconsistent-clone risk (R4).\n";
