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
 * Snapshot pinning — M3 close audit ruling P.
 *
 * A measurement that cannot be reproduced is not evidence. During M3 the private
 * corpus drifted between the rating pass and the close — the pooled finding count
 * moved from 74 to 1,030 — so numbers taken from it at two moments were not
 * numbers about the same thing, and neither could be checked afterwards. Ruling P
 * therefore requires any corpus figure destined for a packet or the paper to come
 * from a **pinned snapshot**: a frozen copy, or a recorded content-hash list, kept
 * outside every repository.
 *
 * This is the hash-list half, and it is the better half of that choice. A frozen
 * copy needs write access to somewhere and answers "what did it look like?"; a
 * hash list needs only reads and answers the question ruling P was actually
 * recorded for — "is this still the tree the number came from?" — which a copy
 * silently fails to ask. Drift is then *detected* rather than hidden.
 *
 * What is pinned is the tree **as the project wrote it**: every `.php` file with
 * no excludes applied, except that third-party dependency trees (`vendor/`,
 * `node_modules/`) are always pruned. Everything else stays — dumped caches,
 * build output, scratch and backup directories, generated tables — because those
 * are exactly what Stage 0 has to learn to triage, and pinning a tree with them
 * already removed would pin the answer along with the question. Ruling T's
 * acceptance is that Stage 0, pointed at this tree, approximately reproduces
 * ruling K's frozen definition from the first rung.
 *
 * Dependencies are the one exception, for two reasons that both point the same
 * way. They are not the project's text at all, so no triage question is being
 * begged by dropping them — nobody disputes the answer. And they churn on every
 * `composer install`, so pinning them would make `verify` fail for reasons that
 * have nothing to do with the corpus drifting, which is the exact signal this
 * tool exists to keep clean.
 *
 * ## The corpus is never named
 *
 * The same discipline `bench/audit-precision.php` applies. The corpus directory
 * is an argument, never defaulted and never recorded; the manifest lists paths
 * relative to it, so the tree's own name appears nowhere in the output; and the
 * manifest refuses to be written inside this repository, because a list of a
 * closed-source tree's file names is exactly as uncommittable as its source. A
 * packet cites the manifest **digest** — one hex string — and nothing else.
 *
 * Usage:
 *   php bench/pin-snapshot.php pin    <corpus> --out=<file-outside-this-repo>
 *   php bench/pin-snapshot.php verify <corpus> <manifest>
 *
 * `verify` exits 0 only when the tree still matches the manifest exactly.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * Refuse to write a manifest inside this repository.
 *
 * Lifted deliberately from {@see bcb_refuse_inside_repo()} in
 * `bench/audit-precision.php`, with its reasoning intact: a listing of a private
 * tree's paths is corpus data, and the guard belongs where the file is written
 * rather than in a reviewer's memory.
 */
function snapshot_refuse_inside_repo(string $path): void
{
    $repo = realpath(dirname(__DIR__));
    $dir  = realpath(dirname($path));

    if ($repo === false || $dir === false) {
        return;
    }

    if ($dir === $repo || str_starts_with($dir . DIRECTORY_SEPARATOR, $repo . DIRECTORY_SEPARATOR)) {
        fwrite(
            STDERR,
            sprintf(
                "Refusing to write the manifest inside the repository (%s).\n"
                . "It lists a private tree's paths, which must never be committed. Choose a path outside it.\n",
                $repo,
            ),
        );

        exit(1);
    }
}

/**
 * Dependency trees, never pinned and never counted. See the file header: they
 * are not the project's own text, and they churn on every dependency install.
 *
 * @var list<string>
 */
const SNAPSHOT_NEVER_PINNED = ['vendor', 'node_modules'];

/**
 * Every `.php` file under a root bar its dependency trees — the corpus ladder's
 * first rung, relative to the root and in a total order.
 *
 * The product's *default* excludes stay off here on purpose: caches, build
 * output and generated files are the material Stage 0 is measured on, so this
 * rung must still contain them.
 *
 * @return list<string>
 */
function snapshot_raw_files(string $root): array
{
    $files = (new FileFinder())->find([$root], ['.php'], SNAPSHOT_NEVER_PINNED, false);
    $base  = rtrim((string) (realpath($root) ?: $root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $relative = [];

    foreach ($files as $file) {
        $real       = realpath($file) ?: $file;
        $relative[] = str_starts_with($real, $base) ? substr($real, strlen($base)) : $real;
    }

    sort($relative);

    return $relative;
}

/**
 * The manifest body: one `path<TAB>sha256<TAB>bytes` line per file.
 *
 * SHA-256 rather than the `xxh3` the engine uses internally. The engine hashes
 * to bucket things and can absorb a collision; a manifest hashes to *attest*
 * that a file is the file a number came from, and an attestation wants a
 * collision-resistant function.
 *
 * @param list<string> $relative
 */
function snapshot_body(string $root, array $relative): string
{
    $base  = rtrim((string) (realpath($root) ?: $root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $lines = [];

    foreach ($relative as $path) {
        $absolute = $base . $path;
        $hash     = hash_file('sha256', $absolute);

        if ($hash === false) {
            fwrite(STDERR, 'Cannot read: ' . $path . "\n");

            exit(1);
        }

        $lines[] = $path . "\t" . $hash . "\t" . (string) (filesize($absolute) ?: 0);
    }

    return implode("\n", $lines) . "\n";
}

/**
 * Parse a manifest body into path => hash.
 *
 * @return array<string, string>
 */
function snapshot_parse(string $manifest): array
{
    $entries = [];

    foreach (explode("\n", $manifest) as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode("\t", $line);

        if (count($parts) < 2) {
            continue;
        }

        $entries[$parts[0]] = $parts[1];
    }

    return $entries;
}

$argv0 = bcb_argv();
$mode  = $argv0[1] ?? '';

if ($mode !== 'pin' && $mode !== 'verify') {
    fwrite(STDERR, "Usage:\n  php bench/pin-snapshot.php pin    <corpus> --out=<file-outside-this-repo>\n"
        . "  php bench/pin-snapshot.php verify <corpus> <manifest>\n");

    exit(1);
}

$root = $argv0[2] ?? '';

if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Give the corpus directory as the second argument.\n");

    exit(1);
}

if ($mode === 'pin') {
    $out = '';

    foreach (array_slice($argv0, 3) as $arg) {
        if (str_starts_with($arg, '--out=')) {
            $out = substr($arg, strlen('--out='));
        }
    }

    if ($out === '') {
        fwrite(STDERR, "Give --out=<file>, outside this repository.\n");

        exit(1);
    }

    snapshot_refuse_inside_repo($out);

    $relative = snapshot_raw_files($root);
    $body     = snapshot_body($root, $relative);
    $digest   = hash('sha256', $body);

    // The header names the snapshot and nothing else. No root path, no tree
    // name: a packet cites the digest, and the digest is a function of the
    // relative paths and contents alone, so the same tree at another location
    // pins identically.
    $header = "# phpcpd-next corpus snapshot (ruling P)\n"
        . "# files\t" . count($relative) . "\n"
        . "# digest\t" . $digest . "\n"
        . "#\n";

    if (file_put_contents($out, $header . $body) === false) {
        fwrite(STDERR, 'Cannot write: ' . $out . "\n");

        exit(1);
    }

    chmod($out, 0o600);

    printf("pinned  %d files\ndigest  %s\n", count($relative), $digest);

    exit(0);
}

$manifestPath = $argv0[3] ?? '';

if ($manifestPath === '' || !is_file($manifestPath)) {
    fwrite(STDERR, "Give the manifest as the third argument.\n");

    exit(1);
}

$manifest = (string) file_get_contents($manifestPath);
$pinned   = snapshot_parse($manifest);
$relative = snapshot_raw_files($root);
$current  = snapshot_parse(snapshot_body($root, $relative));

$added   = array_diff_key($current, $pinned);
$removed = array_diff_key($pinned, $current);
$changed = [];

foreach ($current as $path => $hash) {
    if (isset($pinned[$path]) && $pinned[$path] !== $hash) {
        $changed[$path] = true;
    }
}

/** @var list<array{ok: bool, claim: string, detail: string}> $log */
$log = [];

printf("Snapshot verify — %d files pinned, %d present\n\n", count($pinned), count($current));

bcb_check($log, $added === [], 'no files have appeared since the snapshot', count($added) . ' added');
bcb_check($log, $removed === [], 'no files have disappeared since the snapshot', count($removed) . ' removed');
bcb_check($log, $changed === [], 'no pinned file has changed content', count($changed) . ' changed');

exit(bcb_check_summary($log, 'snapshot verify'));
