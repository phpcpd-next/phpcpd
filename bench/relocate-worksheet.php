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
 * Worksheet relocation — recovering the file behind a rated finding.
 *
 * `bench/audit-precision.php` digests every path behind a **per-run salt that is
 * never stored**, so a rated worksheet cannot be read back to files. That is the
 * right default — a worksheet is a document about a closed tree — but it blocks
 * two things the milestone needs: ruling T's acceptance ("no file containing a
 * consensus-Y finding is discarded") and ruling U's tracking metric, both of
 * which are claims about *files*.
 *
 * The worksheet is recoverable anyway, because it embeds source and source
 * locates itself. Each site's excerpt is a contiguous run of lines beginning at
 * the recorded start line, so a site is relocated by finding the file in which
 * that exact run occurs. This is **matching, never judgement**: the tool either
 * finds the block or reports that it did not, and no verdict is ever revised.
 *
 * ## Two resolution rules, in order, and why the second one is sound
 *
 *   1. **Block match.** The excerpt's lines are searched as an ordered run. A
 *      match at the recorded start line is `exact`; the same run found at some
 *      other offset is `moved` (the file drifted, the content did not).
 *   2. **Digest propagation.** The salt is fixed for the length of one run, so
 *      within one worksheet the digest is a pure function of the path: two sites
 *      carrying the same digest are two sites in the *same file*. A digest that
 *      any of its sites resolved therefore resolves all of them. The rule is
 *      only applied when every resolved site of that digest agrees on one file —
 *      a disagreement would mean a digest collision, and is reported rather than
 *      resolved.
 *
 * Rule 2 is what makes a stale excerpt recoverable: a data table whose rows have
 * since been edited no longer contains its own excerpt, but it is still named by
 * a sibling site in the same file whose rows did not move.
 *
 * ## The pinned tree, and nothing else
 *
 * Candidates come from ruling P's manifest, and a file is a candidate only when
 * it is still present **and its content still hashes to the pinned value**.
 * Relocating into a drifted tree would answer a question about today's corpus
 * with a worksheet rated against a different one.
 *
 * ## The corpus is never named
 *
 * Corpus root, manifest and worksheets are all arguments, never defaulted and
 * never recorded; the output holds paths relative to the root, and is refused if
 * it would be written inside this repository.
 *
 * Usage:
 *   php bench/relocate-worksheet.php --corpus=<root> --pin=<manifest> \
 *       --worksheet=<raterA.tsv> [--rater-b=<raterB.tsv>] --out=<file-outside-this-repo> \
 *       [--sites=<file-outside-this-repo>]
 *
 * `--out` carries one row per finding — the shape the Stage 0 acceptance and the
 * tracking metric read. `--sites` carries one row per *site*, with the file and
 * the line range, which is what a **span**-level tier needs: a file-level tier
 * asks whether a finding's files survive, a span-level tier asks what the span
 * is, and only the second needs to know where in the file it sits.
 */

require_once __DIR__ . '/lib.php';

/**
 * Refuse to write beside this source. A list of a closed tree's file names is
 * exactly as uncommittable as its source.
 */
function rw_refuse_inside_repo(string $out): void
{
    $repo = dirname(__DIR__);
    $dir  = realpath(dirname($out));

    if ($dir !== false && ($dir === $repo || str_starts_with($dir, $repo . DIRECTORY_SEPARATOR))) {
        fwrite(STDERR, "refusing to write inside this repository: " . $out . "\n");

        exit(1);
    }
}

/**
 * The pinned tree: manifest paths that are still present and still hash to the
 * pinned value.
 *
 * @return array{files: list<string>, missing: int, changed: int}
 */
function rw_pinned_tree(string $root, string $manifest): array
{
    $lines = file($manifest, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        fwrite(STDERR, "cannot read manifest: " . $manifest . "\n");

        exit(1);
    }

    $files   = [];
    $missing = 0;
    $changed = 0;

    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $parts = explode("\t", $line);

        if (count($parts) < 2) {
            continue;
        }

        [$path, $hash] = $parts;
        $full = $root . '/' . $path;

        if (!is_file($full)) {
            $missing++;

            continue;
        }

        if (hash_file('sha256', $full) !== $hash) {
            $changed++;

            continue;
        }

        $files[] = $path;
    }

    return ['files' => $files, 'missing' => $missing, 'changed' => $changed];
}

/**
 * Parse a rated worksheet into findings, their verdicts, and their sites.
 *
 * @return array<string, array{verdict: string, lines: int, sites: list<array{digest: string, line: int, excerpt: list<string>}>}>
 */
function rw_parse_worksheet(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        fwrite(STDERR, "cannot read worksheet: " . $path . "\n");

        exit(1);
    }

    /** @var array<string, string> $verdicts */
    $verdicts = [];
    /** @var array<string, int> $extent the finding's reported line count, which every site of it spans */
    $extent = [];
    /** @var array<string, list<array{digest: string, line: int, excerpt: list<string>}>> $sites */
    $sites   = [];
    $finding = '';
    /** @var array{digest: string, line: int, excerpt: list<string>}|null $site */
    $site = null;

    foreach ($lines as $line) {
        $startsSite = preg_match('/^#   site \\d+ — ([0-9a-f]+) line (\\d+)$/', $line, $m) === 1;
        $isExcerpt  = $site !== null && str_starts_with($line, '#     | ');
        $closes     = str_starts_with($line, 'FINDING') || $startsSite || (!$isExcerpt && str_starts_with($line, '#   '));

        if ($closes && $site !== null && $finding !== '') {
            $sites[$finding][] = $site;
            $site              = null;
        }

        if (str_starts_with($line, 'FINDING')) {
            $cols               = explode("\t", $line);
            $finding            = $cols[1] ?? '';
            $verdicts[$finding] = trim($cols[2] ?? '');
            $extent[$finding]   = (int) ($cols[3] ?? '0');
            $sites[$finding]  ??= [];

            continue;
        }

        if ($startsSite) {
            $site = ['digest' => $m[1], 'line' => (int) $m[2], 'excerpt' => []];

            continue;
        }

        if ($isExcerpt && $site !== null) {
            $text = substr($line, 8);

            // The elision marker is the tool's own prose, not source.
            if (preg_match('/^… \\d+ more lines of this site not shown$/u', $text) === 1) {
                continue;
            }

            $site['excerpt'][] = $text;
        }
    }

    if ($site !== null && $finding !== '') {
        $sites[$finding][] = $site;
    }

    $findings = [];

    foreach ($verdicts as $id => $verdict) {
        $findings[$id] = ['verdict' => $verdict, 'lines' => $extent[$id] ?? 0, 'sites' => $sites[$id] ?? []];
    }

    return $findings;
}

/**
 * Find every file in which an excerpt occurs as an ordered run of lines, with
 * the offsets at which it starts.
 *
 * @param list<string>                $excerpt
 * @param array<string, list<string>> $corpus  path => lines
 *
 * @return array<string, list<int>>            path => 1-based start lines
 */
function rw_block_matches(array $excerpt, array $corpus): array
{
    // Anchor on the excerpt's most distinctive line — the rarest one across the
    // corpus — so a run of blank or brace lines does not drive the scan.
    $hits = [];

    foreach ($corpus as $path => $lines) {
        $index = [];

        foreach ($lines as $i => $text) {
            $index[$text][] = $i;
        }

        foreach ($index[$excerpt[0]] ?? [] as $start) {
            $ok = true;

            foreach ($excerpt as $offset => $text) {
                if (($lines[$start + $offset] ?? null) !== $text) {
                    $ok = false;

                    break;
                }
            }

            if ($ok) {
                $hits[$path][] = $start + 1;
            }
        }
    }

    return $hits;
}

$options = getopt('', ['corpus:', 'pin:', 'worksheet:', 'rater-b:', 'out:', 'sites:']);

foreach (['corpus', 'pin', 'worksheet', 'out'] as $required) {
    if (!isset($options[$required]) || !is_string($options[$required])) {
        fwrite(STDERR, "usage: php bench/relocate-worksheet.php --corpus=<root> --pin=<manifest> --worksheet=<file> [--rater-b=<file>] --out=<file>\n");

        exit(1);
    }
}

$root = rtrim((string) $options['corpus'], '/');
$out  = (string) $options['out'];
rw_refuse_inside_repo($out);

$pin = rw_pinned_tree($root, (string) $options['pin']);
printf("pinned tree: %d files usable (%d missing, %d changed — excluded)\n", count($pin['files']), $pin['missing'], $pin['changed']);

$corpus = [];

foreach ($pin['files'] as $path) {
    $text = file_get_contents($root . '/' . $path);

    if ($text === false) {
        continue;
    }

    $corpus[$path] = explode("\n", str_replace("\r\n", "\n", $text));
}

$raterA = rw_parse_worksheet((string) $options['worksheet']);
$raterB = isset($options['rater-b']) && is_string($options['rater-b'])
    ? rw_parse_worksheet($options['rater-b'])
    : [];

// Pass 1 — block match, per site.
/** @var array<string, list<array{file: ?string, how: string, candidates: int}>> $resolved */
$resolved = [];
/** @var array<string, array<string, true>> $byDigest */
$byDigest = [];
$counts   = ['exact' => 0, 'moved' => 0, 'several' => 0, 'unmatched' => 0];

foreach ($raterA as $id => $finding) {
    foreach ($finding['sites'] as $siteIndex => $site) {
        if ($site['excerpt'] === []) {
            $resolved[$id][$siteIndex] = ['file' => null, 'how' => 'unmatched', 'candidates' => 0];
            $counts['unmatched']++;

            continue;
        }

        $hits = rw_block_matches($site['excerpt'], $corpus);

        if ($hits === []) {
            $resolved[$id][$siteIndex] = ['file' => null, 'how' => 'unmatched', 'candidates' => 0];
            $counts['unmatched']++;

            continue;
        }

        // A match at the recorded start line settles it outright.
        $exact = [];

        foreach ($hits as $path => $starts) {
            if (in_array($site['line'], $starts, true)) {
                $exact[] = $path;
            }
        }

        $candidates = $exact !== [] ? $exact : array_keys($hits);

        if (count($candidates) === 1) {
            $how = $exact !== [] ? 'exact' : 'moved';
            $resolved[$id][$siteIndex] = ['file' => $candidates[0], 'how' => $how, 'candidates' => 1];
            $counts[$how]++;
            $byDigest[$site['digest']][$candidates[0]] = true;

            continue;
        }

        $resolved[$id][$siteIndex] = ['file' => null, 'how' => 'several', 'candidates' => count($candidates)];
        $counts['several']++;
    }
}

// Pass 2 — digest propagation, only where the digest's resolved sites agree.
$propagated = 0;
$collisions = [];

foreach ($byDigest as $digest => $files) {
    if (count($files) > 1) {
        $collisions[$digest] = array_keys($files);
    }
}

foreach ($raterA as $id => $finding) {
    foreach ($finding['sites'] as $siteIndex => $site) {
        if ($resolved[$id][$siteIndex]['file'] !== null) {
            continue;
        }

        $digest = $site['digest'];

        if (!isset($byDigest[$digest]) || count($byDigest[$digest]) !== 1) {
            continue;
        }

        $resolved[$id][$siteIndex] = [
            'file'       => array_key_first($byDigest[$digest]),
            'how'        => 'digest',
            'candidates' => 1,
        ];
        $propagated++;
    }
}

printf(
    "sites %d — exact %d, moved %d, several %d, unmatched %d; digest-propagated %d\n",
    array_sum($counts) ,
    $counts['exact'],
    $counts['moved'],
    $counts['several'],
    $counts['unmatched'],
    $propagated,
);

$unresolved = [];

foreach ($raterA as $id => $finding) {
    foreach ($finding['sites'] as $siteIndex => $site) {
        if ($resolved[$id][$siteIndex]['file'] === null) {
            $unresolved[] = sprintf('%s site %d (%s line %d, %s)', $id, $siteIndex + 1, $site['digest'], $site['line'], $resolved[$id][$siteIndex]['how']);
        }
    }
}

if ($unresolved !== []) {
    printf("sites still unresolved: %d\n", count($unresolved));

    foreach ($unresolved as $line) {
        printf("  %s\n", $line);
    }
}

if ($collisions !== []) {
    printf("DIGEST COLLISIONS (not propagated): %d\n", count($collisions));

    foreach ($collisions as $digest => $files) {
        printf("  %s -> %s\n", $digest, implode(', ', $files));
    }
}

// Emit.
$rows       = [];
$relocated  = 0;
$consensusY = 0;
$consensusN = 0;

foreach ($raterA as $id => $finding) {
    $files = [];

    foreach ($finding['sites'] as $siteIndex => $site) {
        $file = $resolved[$id][$siteIndex]['file'];

        if ($file !== null) {
            $files[$file] = true;
        }
    }

    $a = $finding['verdict'];
    $b = $raterB[$id]['verdict'] ?? '';
    $c = ($a !== '' && $a === $b) ? $a : '';

    if ($c === 'Y') {
        $consensusY++;
    } elseif ($c === 'N') {
        $consensusN++;
    }

    if ($files !== []) {
        $relocated++;
    }

    // The column shape the existing consumers read: verdict, consensus, files.
    // Rater B's column is not written — consensus already carries the agreement,
    // and a second verdict column would be a second thing to keep in step.
    $rows[] = implode("\t", array_merge([$id, $a, $c], array_keys($files)));
}

$header = "# finding\traterA\tconsensus\tfiles (relative to the corpus root)\n";
file_put_contents($out, $header . implode("\n", $rows) . "\n");
chmod($out, 0600);

if (isset($options['sites']) && is_string($options['sites'])) {
    $sitesOut = (string) $options['sites'];
    rw_refuse_inside_repo($sitesOut);

    $siteRows = [];

    foreach ($raterA as $id => $finding) {
        $a = $finding['verdict'];
        $b = $raterB[$id]['verdict'] ?? '';
        $c = ($a !== '' && $a === $b) ? $a : '';

        foreach ($finding['sites'] as $siteIndex => $site) {
            $file = $resolved[$id][$siteIndex]['file'];

            if ($file === null) {
                continue;
            }

            $siteRows[] = implode("\t", [
                $id,
                $c,
                (string) ($siteIndex + 1),
                $file,
                (string) $site['line'],
                (string) $finding['lines'],
                $resolved[$id][$siteIndex]['how'],
            ]);
        }
    }

    file_put_contents(
        $sitesOut,
        "# finding\tconsensus\tsite\tfile\tstartLine\tlines\thow\n" . implode("\n", $siteRows) . "\n",
    );
    chmod($sitesOut, 0600);
    printf("wrote %s (%d sites)\n", $sitesOut, count($siteRows));
}

printf("findings relocated: %d of %d\n", $relocated, count($raterA));
printf("consensus: Y %d, N %d\n", $consensusY, $consensusN);
printf("wrote %s\n", $out);
