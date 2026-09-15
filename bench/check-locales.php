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
 * Every translation, measured against English. Three ways a locale file can
 * differ, two of which are always wrong:
 *
 *   - a key English does not have — dead weight, or a typo that silently means
 *     the real key is missing: FAILS;
 *   - a `:placeholder` renamed or dropped — the call site passes a parameter
 *     that lands nowhere: FAILS;
 *   - a missing key — legal, falls back to English per key: REPORTED.
 *
 * And one a per-file check cannot see at all: a locale that is another locale
 * under a different name. `hr.php` was `ro.php`, all 161 keys byte for byte —
 * every key known, every placeholder intact, and Croatian readers served
 * Romanian. Two locales agreeing on a key is ordinary; agreeing on every key is
 * a copied file.
 *
 * ## What the structural checks still could not see
 *
 * All four ask about *shape*. None of them reads English. So when the coverage
 * sentence stopped saying "lie inside at least one clone" and started saying
 * "are duplicated code" — because comments inside a clone are no longer counted
 * — the key kept its name and both its placeholders, and twenty-seven
 * translations went on asserting the old meaning with the gate fully green.
 * A translation of a sentence that no longer exists is worse than no
 * translation, because the fallback would have printed something true.
 *
 * So the English text is pinned, by content hash per key, in the file
 * {@see PIN} names. When an English string changes, every translation still
 * carrying that key is stale by construction, and this says so and fails.
 * The fix is to re-translate the key — or to drop it, and let that locale fall
 * back to English until somebody does — and then `--pin` to record the new
 * English as the baseline.
 *
 * This is `bench/pin-snapshot.php`'s bargain, at the scale of a sentence: a
 * hash list answers "is this still the thing the translation was made from?",
 * which is the question, and drift is detected rather than hidden.
 */

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\Strings\Catalogue;

/**
 * @return array<string, string>
 */
function bcl_flatten(mixed $tree, string $prefix = ''): array
{
    if (!is_array($tree)) {
        // A locale file that does not return an array is broken in a way the
        // catalogue itself refuses; say so here rather than fold it silently.
        return [$prefix === '' ? '<root>' : $prefix => '<non-string>'];
    }

    $flat = [];

    /** @var array<array-key, mixed> $tree */
    foreach ($tree as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

        if (is_array($value)) {
            $flat = [...$flat, ...bcl_flatten($value, $path)];

            continue;
        }

        $flat[$path] = is_string($value) ? $value : '<non-string>';
    }

    return $flat;
}

/** @return list<string> the `:name` placeholders a sentence interpolates, sorted */
function bcl_placeholders(string $sentence): array
{
    preg_match_all('/:([a-zA-Z][a-zA-Z0-9_]*)/', $sentence, $matches);

    $names = $matches[1];
    sort($names);

    return $names;
}

/** Where the English baseline is recorded. Not shipped: it is a gate's memory. */
const PIN = __DIR__ . '/locale-english.pin.json';

/**
 * @param  array<string, string> $english
 * @return array<string, string> key => a short content hash of its English text
 */
function bcl_fingerprint(array $english): array
{
    $pinned = [];

    foreach ($english as $key => $sentence) {
        $pinned[$key] = substr(hash('xxh3', $sentence), 0, 8);
    }

    return $pinned;
}

$directory = dirname(__DIR__) . '/locale';
$english   = bcl_flatten(require $directory . '/' . Catalogue::FALLBACK . '.php');
$total     = count($english);

$fingerprint = bcl_fingerprint($english);

if (in_array('--pin', array_slice(bcb_argv(), 1), true)) {
    file_put_contents(PIN, json_encode($fingerprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    printf('pinned %d English strings into %s%s', count($fingerprint), basename(PIN), PHP_EOL);

    exit(0);
}

/** @var array<string, string> $pinned */
$pinned = is_file(PIN) ? (array) json_decode((string) file_get_contents(PIN), true) : [];

// A key whose English has moved since it was pinned. A key English has only just
// gained is not stale — nobody could have translated it yet — so it is only
// drift when the pin holds a different hash for a key it already knew.
$moved = [];

foreach ($fingerprint as $key => $hash) {
    if (isset($pinned[$key]) && $pinned[$key] !== $hash) {
        $moved[$key] = true;
    }
}

printf("%s: %d keys%s", Catalogue::FALLBACK, $total, PHP_EOL . PHP_EOL);

$failures = 0;
$rows     = [];

foreach (Catalogue::available() as $language) {
    if ($language === Catalogue::FALLBACK) {
        continue;
    }

    $strings = bcl_flatten(require $directory . '/' . $language . '.php');

    $missing   = array_keys(array_diff_key($english, $strings));
    $unknown   = array_keys(array_diff_key($strings, $english));
    $nonString = array_keys(array_filter($strings, static fn (string $v): bool => $v === '<non-string>'));
    $drifted   = [];
    $stale     = array_keys(array_intersect_key($strings, $moved));

    foreach ($english as $key => $sentence) {
        if (!isset($strings[$key])) {
            continue;
        }

        if (bcl_placeholders($sentence) !== bcl_placeholders($strings[$key])) {
            $drifted[] = $key;
        }
    }

    $fatal = count($unknown) + count($nonString) + count($drifted) + count($stale);
    $failures += $fatal > 0 ? 1 : 0;

    $rows[] = [$language, $total - count($missing), count($missing), $unknown, $nonString, $drifted, $stale];
}

foreach ($rows as [$language, $translated, $missing, $unknown, $nonString, $drifted, $stale]) {
    printf(
        "  %-6s %3d/%d  %5.1f%%%s%s",
        $language,
        $translated,
        $total,
        $total === 0 ? 0.0 : $translated / $total * 100,
        $missing > 0 ? sprintf('   (%d not yet translated — falls back to English)', $missing) : '',
        PHP_EOL,
    );

    foreach ($unknown as $key) {
        printf("      UNKNOWN KEY      %s — English has no such key%s", $key, PHP_EOL);
    }

    foreach ($nonString as $key) {
        printf("      NON-STRING LEAF  %s%s", $key, PHP_EOL);
    }

    foreach ($drifted as $key) {
        printf("      PLACEHOLDER DRIFT %s%s", $key, PHP_EOL);
    }

    foreach ($stale as $key) {
        printf("      STALE ENGLISH    %s — the English it translates has changed%s", $key, PHP_EOL);
    }
}

print PHP_EOL;

// A locale that is another locale. Compared on the keys both actually hold, so
// an unfinished translation is not accused of copying the one it falls back to.
$copies = [];

foreach ($rows as [$language]) {
    $mine = bcl_flatten(require $directory . '/' . $language . '.php');

    foreach ($rows as [$other]) {
        if ($other === $language) {
            continue;
        }

        $theirs = bcl_flatten(require $directory . '/' . $other . '.php');
        $shared = array_intersect_key($mine, $theirs);

        if (count($shared) < 20) {
            continue;
        }

        $same = 0;

        foreach ($shared as $key => $value) {
            $same += $theirs[$key] === $value ? 1 : 0;
        }

        if ($same === count($shared)) {
            $pair = $language < $other ? $language . ' / ' . $other : $other . ' / ' . $language;
            $copies[$pair] = count($shared);
        }
    }
}

foreach ($copies as $pair => $keys) {
    printf('  COPY  %s are the same translation under two names — %d of %d keys identical%s', $pair, $keys, $keys, PHP_EOL);
    $failures++;
}

if ($copies !== []) {
    print PHP_EOL;
}

if ($failures > 0) {
    printf(
        'locales: %d of %d fail — a key English does not have, a non-string leaf, placeholder drift, a translation of English that has since changed, or a copied translation.%s',
        $failures,
        count($rows),
        PHP_EOL,
    );

    exit(1);
}

printf('locales: %d translations, every key known to English, every placeholder intact.%s', count($rows), PHP_EOL);
