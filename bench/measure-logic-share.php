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
 * Logic share per reported clone — the discriminator M2 refuted, re-opened.
 *
 * The one confirmed false-positive shape is normalized data-table matching:
 * under identifier normalization every string literal folds to one token, so an
 * array of string pairs matches an array of string pairs and no duplicated
 * logic exists. M2 measured three candidate discriminators against it and
 * refuted all three. The third, logic share, separated perfectly on
 * symfony-string and died on php-parser, whose flagship true positive —
 * `Php7.php` against `Php8.php` — scored *below* ordinary code, so any
 * threshold that removed the data tables removed it too.
 *
 * Those two files are generated, carry a banner, and since the file finder
 * learned to read it they are not scanned at all by default. The corpus that
 * refuted the discriminator no longer reaches the engine, which is a reason to
 * measure again rather than a reason to assume.
 *
 * This script reports the statistic; it does not apply it. Nothing here changes
 * detection. A threshold is a semantic change and needs the recall study in
 * docs/research/deferred-engine-work.md, not one corpus and an eyeball.
 *
 * Usage:
 *   php bench/measure-logic-share.php [<dir> ...]
 *
 * Directories default to every corpus under bench/corpus. Output is one row per
 * reported clone, ascending, so a gap in the distribution is visible as a gap.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Facts\FileFacts;
use LucianoPereira\PhpcpdNext\Util\FileFinder;

/*
 * What counts as a literal is `Facts\FileStatements`'s to say, and this script
 * no longer keeps a second list.
 *
 * The one it kept said in its own docblock that brackets were deliberately not
 * excluded, and then excluded them — along with every other single-character
 * token — by skipping everything `token_get_all()` does not return as an array.
 * A definition that contradicts its implementation is worse than no definition,
 * because the number still prints.
 */

/**
 * The fraction of an occurrence's significant tokens that are not literals.
 *
 * Asked of the product's own facts rather than of a second tokenizer here,
 * which is what this was and which measured something else twice over. It
 * counted only the tokens `token_get_all()` returns as arrays, dropping every
 * single-character one — `; { } ( ) , = + - * /` and every operator, about half
 * the program text and very nearly all of it non-literal — so the statistic it
 * printed was "how literal is this span, ignoring most of what makes a span not
 * literal". And it took a line range, which a match does not have: a clone
 * beginning partway through a line covered every token on it.
 *
 * On a synthetic table against synthetic logic, counting every token moves the
 * separation from 0.449 to 0.290. The published table was not the statistic it
 * named.
 */
function bcb_logic_share(CodeClone $clone, CodeCloneFile $site): ?float
{
    $facts = FileFacts::read($site->name);

    if ($facts === null) {
        return null;
    }

    $span = $facts->occurrence($clone, $site);

    if ($span === null || $span[1] <= 0) {
        return null;
    }

    return 1.0 - ($facts->statements->literals($span[0], $span[1]) / $span[1]);
}

$root = dirname(__DIR__);
$dirs = [];
$subsumption = false;

foreach (array_slice(bcb_argv(), 1) as $arg) {
    if ($arg === '--subsumption') {
        $subsumption = true;

        continue;
    }

    $dirs[] = $arg;
}

/*
 * A run over named directories reports; only a full sweep writes.
 *
 * The first version wrote its summary on every run, so measuring one corpus to
 * check something replaced a six-corpus result file with a one-row one — and
 * the document that inserts it went stale on the next commit. A partial
 * measurement is a fine thing to look at and never a thing to publish.
 */
$sweep = $dirs === [];

if ($sweep) {
    $dirs = glob($root . '/bench/corpus/*', GLOB_ONLYDIR) ?: [];
}

bcb_require_dirs($dirs);

$config = bcb_config(['minTokens' => 70, 'minLines' => 5]);

/**
 * The band a score falls in. 0.15 is where the two corpora that HAVE a low
 * population separate it from the rest; it is a reporting boundary here, not a
 * threshold the engine applies.
 */
const LOGIC_SHARE_BANDS = [0.15, 0.40];

/** @var list<array<string, string>> $summary */
$summary = [];

foreach ($dirs as $dir) {
    $files = (new FileFinder())->find([$dir], ['.php'], []);

    if ($files === []) {
        continue;
    }

    /*
     * Rabin-Karp reports exact contiguous runs only. So for a span of literals,
     * "does the baseline also report this?" answers the question a density
     * statistic cannot: whether the other side is the SAME table, byte for
     * byte, or merely another table of the same shape once identifiers fold.
     * The first is duplication a reader can act on; the second is a coincidence
     * of form.
     */
    $exact = [];

    if ($subsumption) {
        foreach ((new Engine($config, 'rabin-karp'))->detect($files)->clones() as $clone) {
            foreach ($clone->files() as $file) {
                $exact[$file->name][] = [$file->startLine, $file->lastLine($clone->numberOfLines())];
            }
        }
    }

    $map = (new Engine($config, 'unified'))->detect($files);

    /** @var list<array{float, int, string}> $rows */
    $rows = [];

    foreach ($map->clones() as $clone) {
        /** @var list<float> $shares */
        $shares = [];

        /** @var list<string> $label */
        $label = [];

        foreach ($clone->files() as $file) {
            // The lowest-scoring site decides: a clone is a data-table match if
            // EITHER side is a table, not only if both are.
            $share = bcb_logic_share($clone, $file);

            if ($share === null) {
                continue;
            }

            $shares[] = $share;
            $label[]  = basename($file->name) . ':' . $file->startLine;
        }

        if ($shares === []) {
            continue;
        }

        $covered = false;

        if ($subsumption) {
            foreach ($clone->files() as $file) {
                $from = $file->startLine;
                $to   = $file->lastLine($clone->numberOfLines());

                foreach ($exact[$file->name] ?? [] as [$a, $b]) {
                    if ($from <= $b && $a <= $to) {
                        $covered = true;

                        break 2;
                    }
                }
            }
        }

        $rows[] = [min($shares), $clone->numberOfLines(), ($subsumption ? ($covered ? '[exact] ' : '[  -  ] ') : '') . implode(' <-> ', $label)];
    }

    usort($rows, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

    $low = $mid = $high = 0;

    foreach ($rows as [$share]) {
        if ($share < LOGIC_SHARE_BANDS[0]) {
            $low++;
        } elseif ($share < LOGIC_SHARE_BANDS[1]) {
            $mid++;
        } else {
            $high++;
        }
    }

    $summary[] = [
        'corpus' => basename($dir),
        'low'    => (string) $low,
        'mid'    => (string) $mid,
        'high'   => (string) $high,
        'total'  => (string) count($rows),
    ];

    printf("\n%s — %d files, %d clones\n", basename($dir), count($files), count($rows));
    printf("  %7s %6s  %s\n", 'share', 'lines', 'clone');

    /** @var float|null $previous */
    $previous = null;

    foreach ($rows as [$share, $lines, $label]) {
        // Mark the widest step in the distribution: a discriminator is only
        // usable where the two populations do not touch.
        $gap = $previous !== null && $share - $previous > 0.15 ? '  <- gap' : '';

        printf("  %7.3f %6d  %s%s\n", $share, $lines, substr($label, 0, 66), $gap);

        $previous = $share;
    }
}

/*
 * The summary as a document inserts it. Written here rather than retyped, for
 * the same reason as every other measured table in this tree: the totals column
 * is a set of clone counts, and bench/sigil.php will refuse a document that
 * states them in prose.
 */
if ($sweep && $summary !== []) {
    $tsvLines = [implode("\t", array_keys($summary[0]))];
    $txtLines = [sprintf('    %-18s %7s %11s %8s %8s', 'corpus', '<0.15', '0.15-0.40', '>=0.40', 'total')];

    foreach ($summary as $row) {
        $tsvLines[] = implode("\t", array_values($row));
        $txtLines[] = sprintf('    %-18s %7s %11s %8s %8s', $row['corpus'], $row['low'], $row['mid'], $row['high'], $row['total']);
    }

    @mkdir($root . '/bench/results', 0o775, true);
    file_put_contents($root . '/bench/results/logic-share.tsv', implode("\n", $tsvLines) . "\n");
    file_put_contents($root . '/bench/results/logic-share-table.txt', implode("\n", $txtLines) . "\n");

    printf("\nwrote bench/results/logic-share.tsv and logic-share-table.txt (%d corpora)\n", count($summary));
}

if (!$sweep) {
    printf("\n(partial run over %d named directory/ies — results not written)\n", count($dirs));
}

exit(0);
