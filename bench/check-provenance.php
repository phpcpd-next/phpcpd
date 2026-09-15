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
 * The ruling-S inventory, measured.
 *
 * Ruling S sets an endgame — a tree with no inherited surface left,
 * relicensable MIT in one commit, with the inventory as its evidence — and
 * docs/MODERNIZATION.md states that inventory in prose. Prose goes stale between
 * the commit that changes it and the person who remembers to. This script
 * makes the same statement a number, computed from the tree as it stands.
 *
 * ## What it measures, and what it deliberately does not
 *
 * The unit is **a file that still carries an upstream attribution in its
 * header**, which is exactly the unit that has to reach zero before the
 * licence can change. docs/MODERNIZATION.md's argument for that unit is repeated
 * here because the script has to be read as honestly as the document: a
 * header names ancestry, not surviving content, and several attributed files
 * have been rewritten in place while keeping the attribution. Measuring
 * *surviving upstream lines* would mean diffing against upstream, and ruling
 * S's replacement standard is that a file is rewritten **without opening the
 * inherited implementation during the rewrite**. A running line-level diff
 * would defeat the one discipline the standard enforces.
 *
 * So the line column below is `lines in attributed files`, never
 * `lines of upstream code`. The distinction is printed with the number so a
 * reader cannot take the weaker claim for the stronger one.
 *
 * ## The two licence groups
 *
 * The attributed files split by licence, and the split is not visible in the
 * headers — the ConQAT-derived suffix tree and the upstream PHPCPD files carry
 * the same header block. The Apache-2.0 scope is defined by `NOTICE`, so this
 * script reads it from `NOTICE` rather than restating it: any `src/` path
 * appearing in the NOTICE section that names the Apache licence is that
 * group's scope. If NOTICE and this script ever disagree, NOTICE wins, because
 * NOTICE is the document a licensee reads.
 *
 * ## Usage
 *
 *   php bench/check-provenance.php            report over src/
 *   php bench/check-provenance.php --json     the same as machine-readable JSON
 *   php bench/check-provenance.php self-test  the instrument against known trees
 *
 * The self-test exists because of the project's rule that an instrument is
 * trusted only after it has been shown to fail on demand: it builds trees
 * whose answer is known by construction — all-original, all-inherited, mixed,
 * an attribution below the header block that must NOT count, a file with no
 * header at all — and asserts the counts, then asserts that a wrong expected
 * count is actually reported as wrong.
 */

require __DIR__ . '/harness.php';


/**
 * The one name that makes a file this project's own work. A header naming any
 * other copyright holder is an inherited surface until it is replaced.
 */
const PROVENANCE_OWN = 'Luciano Federico Pereira';

/**
 * A file's provenance verdict.
 *
 * @phpstan-type ProvenanceRow array{path: string, lines: int, holders: list<string>, inherited: bool}
 */

/**
 * Every `.php` file under a directory, in a deterministic order.
 *
 * @return list<string>
 */
function prov_php_files(string $dir): array
{
    $out = [];
    $stack = [rtrim($dir, '/')];

    while ($stack !== []) {
        $current = array_pop($stack);
        $entries = scandir($current);

        if ($entries === false) {
            continue;
        }

        sort($entries);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $current . '/' . $entry;

            if (is_dir($path)) {
                $stack[] = $path;

                continue;
            }

            if (str_ends_with($entry, '.php')) {
                $out[] = $path;
            }
        }
    }

    sort($out);

    return $out;
}

/**
 * The copyright holders named in a file's *header* block.
 *
 * The header block is the first `/* ... *\/` comment in the file. Only that
 * block counts: a `(c)` line inside a docblock further down is documentation,
 * not attribution, and a script that counted it would report a file inherited
 * for quoting an attribution in a comment.
 *
 * @return list<string>
 */
function prov_holders(string $source): array
{
    $open = strpos($source, '/*');

    if ($open === false) {
        return [];
    }

    $close = strpos($source, '*/', $open);

    if ($close === false) {
        return [];
    }

    $header = substr($source, $open, $close - $open);

    $matches = [];
    preg_match_all('/\(c\)\s*(?:\d{4}(?:\s*-\s*\d{4})?\s*)?([^<\r\n]+?)\s*(?:<[^>]*>)?\s*$/m', $header, $matches);

    /** @var list<string> $holders */
    $holders = array_values(array_filter(
        array_map(static fn(string $h): string => trim($h), $matches[1]),
        static fn(string $h): bool => $h !== '',
    ));

    return $holders;
}

/**
 * Read the Apache-2.0 scope out of NOTICE rather than restating it here.
 *
 * @return list<string> `src/` path prefixes governed by the second licence
 */
function prov_apache_scope(string $noticePath): array
{
    if (!is_file($noticePath)) {
        return [];
    }

    $notice = (string) file_get_contents($noticePath);

    $matches = [];
    preg_match_all('#\bsrc/[A-Za-z0-9_/]*/#', $notice, $matches);

    /** @var list<string> $scope */
    $scope = array_values(array_unique($matches[0]));

    sort($scope);

    return $scope;
}

/**
 * Measure one tree.
 *
 * @param  list<string> $apacheScope
 * @return array{
 *   files: int, lines: int,
 *   own_files: int, own_lines: int,
 *   inherited: list<array{path: string, lines: int, holders: list<string>, group: string}>,
 * }
 */
function prov_measure(string $root, array $apacheScope = []): array
{
    $files = 0;
    $lines = 0;
    $ownFiles = 0;
    $ownLines = 0;
    $inherited = [];

    $rootPrefix = rtrim($root, '/') . '/';

    foreach (prov_php_files($root) as $path) {
        $source = (string) file_get_contents($path);
        $fileLines = substr_count($source, "\n") + (str_ends_with($source, "\n") ? 0 : 1);

        if ($source === '') {
            $fileLines = 0;
        }

        $files++;
        $lines += $fileLines;

        $holders = prov_holders($source);
        $foreign = array_values(array_filter(
            $holders,
            static fn(string $h): bool => $h !== PROVENANCE_OWN,
        ));

        if ($foreign === []) {
            $ownFiles++;
            $ownLines += $fileLines;

            continue;
        }

        $relative = str_starts_with($path, $rootPrefix)
            ? 'src/' . substr($path, strlen($rootPrefix))
            : $path;

        $group = 'upstream PHPCPD (BSD-3-Clause)';

        foreach ($apacheScope as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                $group = 'ConQAT-derived (Apache-2.0)';

                break;
            }
        }

        $inherited[] = [
            'path' => $relative,
            'lines' => $fileLines,
            'holders' => $foreign,
            'group' => $group,
        ];
    }

    return [
        'files' => $files,
        'lines' => $lines,
        'own_files' => $ownFiles,
        'own_lines' => $ownLines,
        'inherited' => $inherited,
    ];
}

/** A percentage that reads as 0 when there is nothing to divide by. */
function prov_pct(int $part, int $whole): float
{
    return $whole === 0 ? 0.0 : round($part * 100 / $whole, 1);
}

// ---------------------------------------------------------------------------
// self-test
// ---------------------------------------------------------------------------

/**
 * Write a fixture tree and return its root.
 *
 * @param array<string, string> $files path (relative) => contents
 */
function prov_fixture(string $name, array $files): string
{
    $root = sys_get_temp_dir() . '/phpcpd-prov-' . $name;

    foreach ($files as $relative => $contents) {
        $path = $root . '/' . $relative;
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }

        file_put_contents($path, $contents);
    }

    return $root;
}

function prov_header(string ...$holders): string
{
    $lines = '';

    foreach ($holders as $h) {
        $lines .= " * (c) " . $h . "\n";
    }

    return "<?php\n\ndeclare(strict_types=1);\n/*\n * This file is part of a fixture.\n *\n"
        . $lines
        . " *\n * For the full copyright and license information, please view the LICENSE\n"
        . " * file that was distributed with this source code.\n */\n\nfinal class Fixture\n{\n}\n";
}

function prov_self_test(): int
{
    /** @var list<array{ok: bool, claim: string, detail: string}> $log */
    $log = [];

    // 1. An all-original tree is 100 % original, by files and by lines.
    $allOwn = prov_fixture('all-own', [
        'A.php' => prov_header('2026 ' . PROVENANCE_OWN),
        'sub/B.php' => prov_header('2026 ' . PROVENANCE_OWN),
    ]);
    $m = prov_measure($allOwn);
    bcb_check($log, $m['files'] === 2 && $m['own_files'] === 2, 'all-original tree: 2 of 2 files original', sprintf('%d/%d', $m['own_files'], $m['files']));
    bcb_check($log, prov_pct($m['own_files'], $m['files']) === 100.0, 'all-original tree: 100.0 %', (string) prov_pct($m['own_files'], $m['files']));
    bcb_check($log, $m['inherited'] === [], 'all-original tree: nothing inventoried', (string) count($m['inherited']));

    // 2. An all-inherited tree is 0 % original and every file is inventoried
    //    with the foreign holder named.
    $allUpstream = prov_fixture('all-upstream', [
        'A.php' => prov_header('Some Upstream Author <up@example.invalid>', '2026 ' . PROVENANCE_OWN),
    ]);
    $m = prov_measure($allUpstream);
    bcb_check($log, $m['own_files'] === 0 && count($m['inherited']) === 1, 'all-inherited tree: 0 of 1 files original', sprintf('%d/%d', $m['own_files'], $m['files']));
    bcb_check($log, ($m['inherited'][0]['holders'] ?? []) === ['Some Upstream Author'], 'all-inherited tree: the foreign holder is named, the owner is not', implode(', ', $m['inherited'][0]['holders'] ?? []));

    // 3. Mixed: the arithmetic, on both units, with different file sizes so a
    //    script that confused files for lines would be caught.
    $mixed = prov_fixture('mixed', [
        'Own.php' => prov_header('2026 ' . PROVENANCE_OWN),
        'Inherited.php' => prov_header('Some Upstream Author', '2026 ' . PROVENANCE_OWN) . str_repeat("// pad\n", 50),
    ]);
    $m = prov_measure($mixed);
    bcb_check($log, $m['files'] === 2 && $m['own_files'] === 1, 'mixed tree: 1 of 2 files original', sprintf('%d/%d', $m['own_files'], $m['files']));
    bcb_check($log, $m['own_lines'] < $m['lines'] - $m['own_lines'], 'mixed tree: the line unit is not the file unit — the bigger file is the inherited one', sprintf('own %d vs inherited %d lines', $m['own_lines'], $m['lines'] - $m['own_lines']));

    // 4. An attribution BELOW the header block is documentation, not ancestry.
    //    A script that grepped the whole file would call this inherited.
    $quoting = prov_fixture('quoting', [
        'A.php' => prov_header('2026 ' . PROVENANCE_OWN)
            . "\n/**\n * Historical note: the algorithm was published by (c) Some Upstream Author.\n */\nfinal class Note {}\n",
    ]);
    $m = prov_measure($quoting);
    bcb_check($log, $m['own_files'] === 1 && $m['inherited'] === [], 'an attribution below the header block does not make a file inherited', sprintf('%d inherited', count($m['inherited'])));

    // 5. A file with no header block at all is not inherited — it names no
    //    ancestor. (It is also not *good*, but that is a different rule.)
    $bare = prov_fixture('bare', ['A.php' => "<?php\n\nfinal class Bare {}\n"]);
    $m = prov_measure($bare);
    bcb_check($log, $m['own_files'] === 1 && $m['inherited'] === [], 'a file with no header names no ancestor', sprintf('%d inherited', count($m['inherited'])));

    // 6. The Apache scope comes from NOTICE, and it partitions the inventory.
    $scoped = prov_fixture('scoped', [
        'Plain.php' => prov_header('Some Upstream Author', '2026 ' . PROVENANCE_OWN),
        'Nested/Deep/Tree.php' => prov_header('Some Upstream Author', '2026 ' . PROVENANCE_OWN),
    ]);
    $noticePath = sys_get_temp_dir() . '/phpcpd-prov-notice.txt';
    file_put_contents($noticePath, "second licence covers src/Nested/Deep/ and nothing else\n");
    $scope = prov_apache_scope($noticePath);
    bcb_check($log, $scope === ['src/Nested/Deep/'], 'the second-licence scope is read from NOTICE, not restated in the script', implode(' ', $scope));
    $m = prov_measure($scoped, $scope);
    $groups = array_map(static fn(array $r): string => $r['group'], $m['inherited']);
    bcb_check(
        $log,
        count(array_filter($groups, static fn(string $g): bool => str_contains($g, 'Apache'))) === 1
        && count(array_filter($groups, static fn(string $g): bool => str_contains($g, 'BSD'))) === 1,
        'the NOTICE scope partitions the inventory into its two licence groups',
        implode(' | ', $groups),
    );
    @unlink($noticePath);

    // 7. THE INSTRUMENT MUST BE ABLE TO FAIL. Every check above is an
    //    assertion this script could satisfy by always answering "original".
    //    Ask it the question whose true answer is "inherited" and demand that
    //    a wrong expectation is reported as wrong.
    $m = prov_measure($allUpstream);
    $wrongExpectation = ($m['own_files'] === 1);
    bcb_check($log, $wrongExpectation === false, 'a false claim about the inventory is reported false, not passed over', 'claimed 1 original file in an all-inherited tree');

    // ...and the same in the other direction, so the instrument cannot pass by
    // always answering "inherited" either.
    $m = prov_measure($allOwn);
    $wrongExpectation = (count($m['inherited']) > 0);
    bcb_check($log, $wrongExpectation === false, 'and a false claim in the other direction likewise', 'claimed an inherited file in an all-original tree');

    return bcb_check_summary($log, 'provenance instrument self-test');
}

// ---------------------------------------------------------------------------
// main
// ---------------------------------------------------------------------------

$argvLocal = $argv ?? [];
$mode = $argvLocal[1] ?? '';

if ($mode === 'self-test') {
    exit(prov_self_test());
}

$repo = dirname(__DIR__);
$scope = prov_apache_scope($repo . '/NOTICE');
$m = prov_measure($repo . '/src', $scope);

$inheritedFiles = count($m['inherited']);
$inheritedLines = $m['lines'] - $m['own_lines'];

if ($mode === '--json') {
    echo json_encode([
        'files' => $m['files'],
        'lines' => $m['lines'],
        'own_files' => $m['own_files'],
        'own_lines' => $m['own_lines'],
        'inherited_files' => $inheritedFiles,
        'inherited_lines' => $inheritedLines,
        'original_pct_files' => prov_pct($m['own_files'], $m['files']),
        'original_pct_lines' => prov_pct($m['own_lines'], $m['lines']),
        'apache_scope' => $scope,
        'inventory' => $m['inherited'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";

    exit($inheritedFiles === 0 ? 0 : 1);
}

printf("Provenance of src/ — %d files, %d lines\n\n", $m['files'], $m['lines']);
printf("  %-46s %6s %8s %9s\n", '', 'files', 'lines', 'share');
printf("  %-46s %6d %8d %8.1f %%\n", 'Original to this project', $m['own_files'], $m['own_lines'], prov_pct($m['own_files'], $m['files']));
printf("  %-46s %6d %8d %8.1f %%\n", 'Carrying an upstream attribution', $inheritedFiles, $inheritedLines, prov_pct($inheritedFiles, $m['files']));

echo "\n";
printf("ORIGINAL-WORK SHARE: %.1f %% of files, %.1f %% of lines\n", prov_pct($m['own_files'], $m['files']), prov_pct($m['own_lines'], $m['lines']));
echo "\n";
echo "The line column is `lines in attributed files`, NOT `lines of upstream code`.\n";
echo "A header names ancestry, not surviving content, and several attributed files\n";
echo "have been rewritten in place while keeping the attribution. Measuring surviving\n";
echo "upstream lines would mean diffing against upstream, which is exactly the\n";
echo "discipline ruling S's replacement standard forbids. The file column is the unit\n";
echo "that has to reach zero before the licence can change.\n";

if ($m['inherited'] !== []) {
    /** @var array<string, list<array{path: string, lines: int, holders: list<string>, group: string}>> $byGroup */
    $byGroup = [];

    foreach ($m['inherited'] as $row) {
        $byGroup[$row['group']][] = $row;
    }

    ksort($byGroup);

    foreach ($byGroup as $group => $rows) {
        usort($rows, static fn(array $a, array $b): int => $b['lines'] <=> $a['lines'] ?: strcmp($a['path'], $b['path']));
        $groupLines = array_sum(array_map(static fn(array $r): int => $r['lines'], $rows));

        printf("\n%s — %d files, %d lines\n", $group, count($rows), $groupLines);

        foreach ($rows as $row) {
            printf("  %-62s %5d\n", $row['path'], $row['lines']);
        }
    }
}

echo "\n";
// The gate changed jobs when the inventory reached zero. It used to count how
// far there was to go; the licence has since changed on the strength of that
// count, so what it now does is refuse to let it go back up. A file arriving
// with an inherited attribution under an MIT `LICENSE` is a contradiction the
// build should not carry.
printf("%s\n", $inheritedFiles === 0
    ? 'INVENTORY AT ZERO — no file in src/ carries an inherited attribution, which is what LICENSE (MIT) asserts.'
    : sprintf(
        "%d file%s carr%s an inherited attribution, and LICENSE says MIT.\n"
        . "Either the attribution is wrong, or the licence is.",
        $inheritedFiles,
        $inheritedFiles === 1 ? '' : 's',
        $inheritedFiles === 1 ? 'ies' : 'y',
    ));

exit($inheritedFiles === 0 ? 0 : 1);
