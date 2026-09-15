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
 * Subsumption check: does the unified engine find everything Rabin-Karp finds?
 *
 * The unified engine is meant to replace three others, so the first thing it owes
 * is that nothing already detected stops being detected. Rabin-Karp is the right
 * baseline to start from because its answers are the least arguable — exact
 * contiguous duplication, no thresholds beyond the two public ones, and a report
 * the paper established matches the original phpcpd 6.0.3 phar to two decimals on
 * every pinned corpus.
 *
 * The comparison is made at the level of *locations and pairs*, not of whole
 * clone classes, because the two engines legitimately group the same duplication
 * differently. Five files sharing a block are one class of five to one engine and
 * several overlapping classes to the other; comparing classes would report that
 * regrouping as a lost clone. So:
 *
 *   1. every (file, line) the baseline reports must appear inside some clone the
 *      unified engine reports — this is the subsumption claim, and a miss here is
 *      a hard failure;
 *   2. for every pair of locations inside a baseline clone, the unified engine
 *      must report a clone covering both, of at least as many tokens.
 *
 * Where (2) disagrees, neither engine is believed. The number of tokens the two
 * locations actually share is recomputed here — a straight token-by-token walk
 * over both signatures, no hashing, no windows, no sampling — and that number
 * decides. This matters because Rabin-Karp really does over-report: it keys its
 * hash table on the *first* file to register a window, so a run of matching
 * windows that continues only because a third file matches further is still
 * attributed to that first file, at the run's full length. Such a baseline clone
 * names a length the pair does not share, and can be listed as such rather than
 * counted against the engine that declined to invent it.
 *
 * Usage:
 *   php bench/check-superset.php [<dir> ...] [--min-tokens=100] [--min-lines=5]
 *                                [--baseline=rabin-karp|default|tokenbag]
 *                                [--list-limit=N]
 *
 * Exit 0 only if every baseline location is reported and every length
 * disagreement is settled in the unified engine's favour by the source.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;

$dirs      = [];
$minTokens = 100;
$minLines  = 5;
$listLimit = 12;

/*
 * Which engine's findings the unified engine must subsume.
 *
 * Rabin-Karp is the default because its answers are the least arguable, and it
 * is what M1 through M3 measured against. `default` is the **merged pipeline**
 * — Rabin-Karp plus the token bag, which is what 1.4.0 actually ships — and it
 * is the project owner's release gate for 2.0.0: "detection better than 1.4"
 * means every location the 1.4 default reports is reported. That baseline is
 * strictly harder, because the token bag sees a class of duplication no seeded
 * method can (order-free displacement below the seed length), and a failure
 * there is a measurement of that gap rather than a defect in this check.
 */
$baselineAlgorithm = 'rabin-karp';

foreach (array_slice(bcb_argv(), 1) as $arg) {
    if (str_starts_with($arg, '--baseline=')) {
        $baselineAlgorithm = substr($arg, strlen('--baseline='));

        continue;
    }

    if (str_starts_with($arg, '--list-limit=')) {
        $listLimit = (int) substr($arg, strlen('--list-limit='));

        continue;
    }

    if (str_starts_with($arg, '--min-tokens=')) {
        $minTokens = (int) substr($arg, strlen('--min-tokens='));

        continue;
    }

    if (str_starts_with($arg, '--min-lines=')) {
        $minLines = (int) substr($arg, strlen('--min-lines='));

        continue;
    }

    $dirs[] = $arg;
}

if ($dirs === []) {
    $dirs = [dirname(__DIR__) . '/src'];
}

bcb_require_dirs($dirs);

$files = bcb_gate_files($dirs);

if ($files === []) {
    fwrite(STDERR, 'No PHP files found in: ' . implode(', ', $dirs) . "\n");

    exit(1);
}

/**
 * One clone flattened into the locations it names and the span each covers.
 *
 * A location is a (file, start line) pair — kept individually rather than merged
 * per file, because two copies inside one file are two locations and collapsing
 * them loses exactly the information a self-clone has to be judged on.
 *
 * @return list<array{locations: list<array{file: string, start: int, end: int, tokens: int}>, tokens: int, label: string}>
 */
function superset_rows(CodeCloneMap $map): array
{
    $rows = [];

    foreach ($map->clones() as $clone) {
        $locations = [];
        $label     = [];

        foreach ($clone->files() as $file) {
            $locations[] = [
                'file'   => $file->name,
                'start'  => $file->startLine,
                'end'    => $file->lastLine($clone->numberOfLines()),
                'tokens' => $file->tokens($clone->numberOfTokens()),
            ];
            $label[] = basename($file->name) . ':' . $file->startLine;
        }

        sort($label);

        $rows[] = [
            'locations' => $locations,
            'tokens'    => $clone->numberOfTokens(),
            'label'     => implode(' + ', $label),
        ];
    }

    return $rows;
}

/**
 * How many *distinct* (file, line) locations a list of entries names.
 *
 * A location named by three baseline classes is three entries and one location.
 * Both numbers are reported: the entry count is what the 8 / 134 / 311 were
 * counted in and is the only basis on which those can be compared, and the
 * distinct count is what "a class of locations dies with the token bag" actually
 * means.
 *
 * @param list<array{label: string, location: array{file: string, start: int, end: int}}> $entries
 */
function superset_distinct_locations(array $entries): int
{
    $seen = [];

    foreach ($entries as $entry) {
        $seen[$entry['location']['file'] . ':' . $entry['location']['start']] = true;
    }

    return count($seen);
}

/**
 * A clone's label, kept readable. A token-bag class can name 84 sites, and an
 * 84-site label on an evidence line is a line nobody reads — the point of ruling
 * 6's listing is that a reader can check it.
 */
function superset_short_label(string $label): string
{
    $parts = explode(' + ', $label);

    if (count($parts) <= 3) {
        return $label;
    }

    return implode(' + ', array_slice($parts, 0, 3)) . sprintf(' + %d more sites', count($parts) - 3);
}

/**
 * How thin the thinnest coverage in this run was, as a phrase for the check line.
 *
 * Every location here passed. The number says over how much of its claim, which
 * is the only way a reader can tell a gate that is measuring something from one
 * that has stopped.
 *
 * @param list<array{label: string, location: array{file: string, start: int, end: int}, shared: int, span: int}> $partial
 */
function superset_thinnest(array $partial): string
{
    if ($partial === []) {
        return ' in full';
    }

    $thinnest = null;

    foreach ($partial as $entry) {
        if ($thinnest === null || $entry['shared'] / $entry['span'] < $thinnest['shared'] / $thinnest['span']) {
            $thinnest = $entry;
        }
    }

    return sprintf(
        '; %d entries at %d distinct locations over part of the span claimed, the thinnest %s:%d at %d of %d lines',
        count($partial),
        superset_distinct_locations($partial),
        basename($thinnest['location']['file']),
        $thinnest['location']['start'],
        $thinnest['shared'],
        $thinnest['span'],
    );
}

/**
 * One entry per location, the thinnest coverage that location was seen at.
 *
 * A token-bag class of 83 sites puts the same location in 80 rows, so listing
 * entries prints one location eighty times and buries the other seventy-nine.
 * The listing is for a reader checking the evidence, and what a reader needs is
 * each location once, at its worst.
 *
 * @param  list<array{label: string, location: array{file: string, start: int, end: int}, shared: int, span: int}> $partial
 * @return list<array{label: string, location: array{file: string, start: int, end: int}, shared: int, span: int}>
 */
function superset_thinnest_per_location(array $partial): array
{
    $worst = [];

    foreach ($partial as $entry) {
        $key = $entry['location']['file'] . ':' . $entry['location']['start'];

        if (!isset($worst[$key]) || $entry['shared'] / $entry['span'] < $worst[$key]['shared'] / $worst[$key]['span']) {
            $worst[$key] = $entry;
        }
    }

    return array_values($worst);
}

/**
 * The occurrence in this row that covers the given baseline location, or null.
 *
 * Covering means the two spans overlap. It used to mean the baseline
 * location's **first line** fell inside an occurrence, and that asked a
 * question neither engine answers.
 *
 * A baseline start line is not a claim about where duplication begins; it is
 * where the baseline's own window happened to open. The token bag opens on a
 * method signature, because at `--min-similarity` one differing token in four
 * hundred costs it nothing. This engine cannot open there and should not: with
 * normalization off — the default — `testCanBeRetry` and `testCanBeAfter` are
 * different tokens, and the signature line is not shared text.
 *
 * Under the old rule that difference was invisible for 53 of phpunit's 54
 * `MetadataTest` locations, because the previous method's clone happened to
 * spill a line past its end and land on the next signature — coverage by
 * tiling, not by detection. The one location it failed on was the file's edge
 * method, the only one with no neighbour to spill over it, and it failed
 * identically detected code. Being the last method in a file is not a
 * detection defect, and a gate that says so is measuring its own arithmetic.
 *
 * Overlap asks the question the baseline actually poses — *is there
 * duplication inside the span I claimed?* — and gives up nothing, because how
 * much is a separate question that §6.5's pairs half puts to every pair
 * independently. A location covered by a sliver is still charged there, at the
 * sliver's length, against what the source really shares.
 *
 * The occurrence returned is the one overlapping most, since that is the one
 * the pairs half should measure; ties go to the earlier start, so the result
 * does not depend on the order the rows were built in.
 *
 * @param array{locations: list<array{file: string, start: int, end: int, tokens: int}>, tokens: int, label: string} $row
 * @param array{file: string, start: int, end: int} $location
 * @return array{file: string, start: int, end: int, tokens: int}|null
 */
function superset_location_covering(array $row, array $location): ?array
{
    $best    = null;
    $overlap = 0;

    foreach ($row['locations'] as $occurrence) {
        if ($occurrence['file'] !== $location['file']) {
            continue;
        }

        $shared = min($occurrence['end'], $location['end'])
            - max($occurrence['start'], $location['start'])
            + 1;

        if ($shared <= 0) {
            continue;
        }

        if ($shared > $overlap || ($shared === $overlap && $best !== null && $occurrence['start'] < $best['start'])) {
            $best    = $occurrence;
            $overlap = $shared;
        }
    }

    return $best;
}

/**
 * Does this row report duplication covering the given (file, line)?
 *
 * @param array{locations: list<array{file: string, start: int, end: int, tokens: int}>, tokens: int, label: string} $row
 * @param array{file: string, start: int, end: int} $location
 */
function superset_covers_location(array $row, array $location): bool
{
    return superset_location_covering($row, $location) !== null;
}

/**
 * How much *these two* occurrences share, as this row reports it.
 *
 * A clone class carries one token count, measured on the site it was led by,
 * and a class merged from several near-identical files understates every longer
 * member with it. The question here is pairwise — how long is the duplication
 * between these two sites — and the answer is bounded by each occurrence's own
 * extent, so it is the smaller of the two. On phpunit's `TestRunner/Issue`
 * family that is the difference between the 137 tokens the two files share and
 * the 129 the class's lead spans.
 *
 * @param array{locations: list<array{file: string, start: int, end: int, tokens: int}>, tokens: int, label: string} $row
 * @param array{file: string, start: int, end: int} $locationA
 * @param array{file: string, start: int, end: int} $locationB
 */
function superset_pair_tokens(array $row, array $locationA, array $locationB): int
{
    $a = superset_location_covering($row, $locationA);
    $b = superset_location_covering($row, $locationB);

    if ($a === null || $b === null) {
        return 0;
    }

    return min($a['tokens'], $b['tokens']);
}

/**
 * The independent **order-free** adjudicator, deferred into ruling R by the M4
 * packet §3.3 and due now that the complement pass has landed.
 *
 * `superset_true_match_length()` recomputes a *contiguous* run, which is the
 * wrong instrument for a token-bag finding: a bag makes no claim about order, so
 * measuring how much of it happens to be in order says nothing about whether the
 * claim is true. Until now such pairs were reported `inapplicable` — counted,
 * never folded into a pass — which was honest but left the baseline's claim
 * unadjudicated in either direction.
 *
 * This is the textbook recompute the ruling asks for, and it is written here
 * rather than called from the engine for exactly the reason the contiguous walk
 * is: an adjudicator that shares code with one of the parties is not an
 * adjudicator. Shingles of three tokens, multiset intersection matched one
 * occurrence to one occurrence, divided by the larger bag — bijective coverage,
 * the same quantity ruling R's constraint 3 verifies against, computed
 * independently from the two files' own tokens.
 *
 * @return ?float null when either span cannot be located
 */
function superset_true_bag_overlap(
    string $fileA,
    int $lineA,
    string $fileB,
    int $lineB,
    int $tokens,
    StrategyConfiguration $config,
): ?float {
    /** @var array<string, FileTokens> $cache */
    static $cache = [];

    foreach ([$fileA, $fileB] as $path) {
        if (!isset($cache[$path])) {
            $buffer = file_get_contents($path);

            if ($buffer === false) {
                return null;
            }

            $cache[$path] = (new DefaultStrategy($config))->tokenize($buffer);
        }
    }

    $startsA = superset_tokens_on_line($cache[$fileA]->tokenRealLines, $lineA);
    $startsB = superset_tokens_on_line($cache[$fileB]->tokenRealLines, $lineB);

    if ($startsA === [] || $startsB === []) {
        return null;
    }

    $bag = static function (string $signature, int $start, int $length): array {
        $shingles = [];
        $limit    = min($length, intdiv(strlen($signature), BCB_TOKEN_BYTES) - $start) - 2;

        for ($i = 0; $i < $limit; $i++) {
            $shingles[] = crc32(substr($signature, ($start + $i) * BCB_TOKEN_BYTES, 3 * BCB_TOKEN_BYTES));
        }

        return $shingles;
    };

    $left  = $bag($cache[$fileA]->signature, $startsA[0], $tokens);
    $right = $bag($cache[$fileB]->signature, $startsB[0], $tokens);
    $size  = max(count($left), count($right));

    if ($size === 0) {
        return null;
    }

    $counts = [];

    foreach ($left as $hash) {
        $counts[$hash] = ($counts[$hash] ?? 0) + 1;
    }

    $matched = 0;

    foreach ($right as $hash) {
        if (($counts[$hash] ?? 0) > 0) {
            $counts[$hash]--;
            $matched++;
        }
    }

    return $matched / $size;
}

/**
 * The number of tokens two locations actually share — computed here, trusting
 * neither engine.
 *
 * Every token on each reported start line is tried, because a clone need not
 * begin at the first token of its line: a line can carry several statements, and
 * a generated parser table carries hundreds of tokens. Anchoring at the first
 * token would compare the wrong positions and report zero for code that plainly
 * matches.
 */
function superset_true_match_length(
    string $fileA,
    int $lineA,
    string $fileB,
    int $lineB,
    StrategyConfiguration $config,
): ?int {
    /** @var array<string, FileTokens> $cache */
    static $cache = [];

    foreach ([$fileA, $fileB] as $path) {
        if (!isset($cache[$path])) {
            $buffer = file_get_contents($path);

            if ($buffer === false) {
                return null;
            }

            $cache[$path] = (new DefaultStrategy($config))->tokenize($buffer);
        }
    }

    $a       = $cache[$fileA];
    $b       = $cache[$fileB];
    $startsA = superset_tokens_on_line($a->tokenRealLines, $lineA);
    $startsB = superset_tokens_on_line($b->tokenRealLines, $lineB);

    if ($startsA === [] || $startsB === []) {
        return null;
    }

    $tokensA = count($a->tokenRealLines);
    $tokensB = count($b->tokenRealLines);
    $best    = 0;

    foreach ($startsA as $startA) {
        foreach ($startsB as $startB) {
            // Two copies in one file must not be compared against themselves.
            if ($fileA === $fileB && $startA === $startB) {
                continue;
            }

            $limit = min($tokensA - $startA, $tokensB - $startB);

            // Nor may they grow into each other. Refusing only the identical
            // position is not enough: on periodic code — a run of one-line
            // accessors, a flat list of constants, anything that normalizes to
            // one repeating sequence — a run matches itself shifted by a period,
            // and this adjudicator certified 154 tokens "actually shared" for a
            // pair whose two copies sit two tokens apart. It was agreeing with
            // the engine it exists to check, on exactly the pairs where that
            // engine was wrong.
            if ($fileA === $fileB) {
                $limit = min($limit, abs($startB - $startA));
            }
            $matched = 0;

            while (
                $matched < $limit
                && substr($a->signature, ($startA + $matched) * BCB_TOKEN_BYTES, BCB_TOKEN_BYTES)
                === substr($b->signature, ($startB + $matched) * BCB_TOKEN_BYTES, BCB_TOKEN_BYTES)
            ) {
                $matched++;
            }

            $best = max($best, $matched);
        }
    }

    return $best;
}

/**
 * Token indices on a given line, or on the first line after it carrying tokens.
 *
 * @param  list<int> $realLines
 * @return list<int>
 */
function superset_tokens_on_line(array $realLines, int $line): array
{
    $target = null;

    foreach ($realLines as $realLine) {
        if ($realLine >= $line && ($target === null || $realLine < $target)) {
            $target = $realLine;
        }
    }

    if ($target === null) {
        return [];
    }

    $indices = [];

    foreach ($realLines as $index => $realLine) {
        if ($realLine === $target) {
            $indices[] = $index;
        }
    }

    return $indices;
}

/** @var list<array{ok: bool, claim: string, detail: string}> $log */
$log    = [];
$config = bcb_config(['minTokens' => $minTokens, 'minLines' => $minLines]);

printf(
    "Subsumption check — %d files, --min-tokens=%d --min-lines=%d\n\n",
    count($files),
    $minTokens,
    $minLines,
);

$baselineRun = bcb_time(static fn(): CodeCloneMap => (new Engine(
    $config,
    // The merged pipeline is what the Engine builds when given no algorithm at
    // all; naming it explicitly would select one half of it.
    $baselineAlgorithm === 'default' ? null : $baselineAlgorithm,
))->detect($files));
$unifiedRun  = bcb_time(static fn(): CodeCloneMap => (new Engine($config, 'unified'))->detect($files));

$baselineName = $baselineAlgorithm === 'default' ? 'default (rk+tokenbag)' : $baselineAlgorithm;

foreach ([[$baselineName, $baselineRun], ['unified', $unifiedRun]] as [$name, $run]) {
    if ($run['outcome'] !== BCB_OK) {
        bcb_check($log, false, $name . ': the engine runs at all', (string) $run['error']);
    }
}

if ($log !== []) {
    exit(bcb_check_summary($log, 'subsumption check'));
}

$baseline = $baselineRun['value'];
$unified  = $unifiedRun['value'];
assert($baseline instanceof CodeCloneMap);
assert($unified instanceof CodeCloneMap);

$baselineRows = superset_rows($baseline);
$unifiedRows  = superset_rows($unified);

printf(
    "  %-21s %5d clones, %7d duplicated lines, %.3fs\n",
    $baselineName,
    count($baselineRows),
    $baseline->numberOfDuplicatedLines(),
    $baselineRun['seconds'],
);
printf(
    "  %-21s %5d clones, %7d duplicated lines, %.3fs\n\n",
    'unified',
    count($unifiedRows),
    $unified->numberOfDuplicatedLines(),
    $unifiedRun['seconds'],
);

/*
 * Attribution, for the M4 ruling on the pair-length half.
 *
 * `superset_true_match_length()` recomputes the *contiguous* tokens two
 * locations share, and that is the right adjudicator for exactly one kind of
 * claim: "these two places hold the same run of code". It is not the claim an
 * order-free engine makes. The token bag reports two spans holding the same
 * multiset of tokens, which is a real finding and is deliberately not
 * contiguous — on php-parser it names `NodeTraverser.php:93 ↔ :181` at 140
 * tokens where the walk finds 2 shared in a row.
 *
 * Failing such a pair would be a category error, and silently dropping it would
 * be gate-weakening, so the M4 audit ruled the third path: a pair whose baseline
 * attribution is the token bag is reported **inapplicable** — counted, listed,
 * and never folded into a pass line. The location half stays binding for every
 * engine, and it is the half the owner's release gate is written in.
 *
 * Attribution is per *pair* rather than per clone, because that is the unit the
 * check judges: a pair is Rabin-Karp's if some Rabin-Karp clone covers both of
 * its locations, and the token bag's otherwise. Rabin-Karp is re-run here purely
 * as that reference; it is never compared against.
 *
 * Ruling R's complement pass carries the deferred condition that closes this:
 * when it lands, the pairs half becomes applicable to order-free findings again
 * through a bag-appropriate independent adjudicator — a textbook bijective
 * coverage recompute over the two spans, written independently of the engine's
 * own code, exactly as the location walk is independent today.
 */
$attributionRows = [];
$attributionByFile = [];

if ($baselineAlgorithm !== 'rabin-karp') {
    $attributionRows = superset_rows((new Engine($config, 'rabin-karp'))->detect($files));

    foreach ($attributionRows as $index => $row) {
        foreach ($row['locations'] as $location) {
            $attributionByFile[$location['file']][$index] = true;
        }
    }
}

/**
 * Is this pair one Rabin-Karp also reports — i.e. a contiguous claim the
 * adjudicator can arbitrate?
 *
 * @param array{file: string, start: int, end: int} $a
 * @param array{file: string, start: int, end: int} $b
 * @param list<array{locations: list<array{file: string, start: int, end: int, tokens: int}>, tokens: int, label: string}> $rows
 * @param array<string, array<int, bool>> $index
 */
function superset_pair_is_contiguous_claim(array $a, array $b, array $rows, array $index): bool
{
    foreach (array_keys($index[$a['file']] ?? []) as $i) {
        if (
            superset_covers_location($rows[$i], $a)
            && superset_covers_location($rows[$i], $b)
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Ruling 6's adjudication of one uncovered baseline **location**.
 *
 * The pairs half already refuses to charge this engine for a claim an
 * independent measure does not support (§6.5). Ruling 6 extends that principle
 * to the location half, which is the half the owner's release gate is written
 * in, and which had been binding without ever asking whether the locations it
 * bound were real.
 *
 * The question asked is the baseline's own: *is this location within
 * `--min-similarity` of any other site of the class that named it?* Coverage is
 * the same textbook bijective recompute {@see superset_true_bag_overlap()}
 * performs — three-token shingles, one occurrence matched to one occurrence,
 * over the larger bag — written independently of both engines.
 *
 * Three deliberate conservatisms, all pointing the same way (toward calling a
 * location a genuine miss, which is the direction that costs this engine):
 *
 *   1. **The best pair wins.** A class of N sites is adjudicated at its
 *      strongest supporting pair, not its weakest and not its average.
 *   2. **Unlocatable is a miss.** A span the recompute cannot place returns
 *      `null` from the overlap function and the location stays a miss.
 *   3. **A contiguous claim is adjudicated by a contiguous measure.** A shingle
 *      bag has no standing over an exact claim, and for a while that was turned
 *      into no adjudication at all: a Rabin-Karp baseline location was
 *      unfalsifiable by construction, so a location it named could not be
 *      questioned however little the source supported it. The instrument for an
 *      exact claim already exists and §6.5's pairs half already trusts it —
 *      {@see superset_true_match_length()}, the longest run the two locations
 *      really share, read from the source. A location is set aside only when
 *      that recompute finds the claim shares **nothing at all** at its own
 *      coordinates, which is the strictest reading available and still leaves
 *      every supported claim binding.
 *
 * @param  array{file: string, start: int, end: int} $location
 * @param  array{locations: list<array{file: string, start: int, end: int, tokens: int}>, tokens: int, label: string} $row
 * @param  list<array{locations: list<array{file: string, start: int, end: int, tokens: int}>, tokens: int, label: string}> $attributionRows
 * @param  array<string, array<int, bool>> $attributionByFile
 * @return ?array{coverage: float, measure: 'run'|'bag'} the recomputed
 *                coverage, and the instrument that decided it, when the
 *                location is a baseline over-report; `null` when it is a
 *                genuine miss
 */
function superset_location_verdict(
    array $location,
    array $row,
    string $baselineAlgorithm,
    array $attributionRows,
    array $attributionByFile,
    StrategyConfiguration $config,
): ?array {
    // Rabin-Karp makes contiguous claims only, so a bag measure has no standing
    // over them — but "the wrong instrument" is not a reason to use none. The
    // right one is the pairs half's own: what the two locations really share,
    // measured from the source at the coordinates the baseline named.
    if ($baselineAlgorithm === 'rabin-karp') {
        $shared = null;

        foreach ($row['locations'] as $other) {
            if ($other['file'] === $location['file'] && $other['start'] === $location['start']) {
                continue;
            }

            $true = superset_true_match_length(
                $location['file'],
                $location['start'],
                $other['file'],
                $other['start'],
                $config,
            );

            // Unlocatable stays a miss, exactly as it does for the bag measure.
            if ($true === null) {
                return null;
            }

            if ($shared === null || $true > $shared) {
                $shared = $true;
            }
        }

        // The best supporting pair shares nothing at the coordinates claimed:
        // the location is a baseline over-report, listed rather than charged.
        return $shared !== null && $shared <= 0
            ? ['coverage' => 0.0, 'measure' => 'run']
            : null;
    }

    $best = null;
    $contiguousOverreport = false;

    foreach ($row['locations'] as $other) {
        if ($other['file'] === $location['file'] && $other['start'] === $location['start']) {
            continue;
        }

        // A contiguous claim, inside a mixed baseline. A bag measure has no
        // standing over it — ruling 6 — but "the wrong instrument" is not a
        // reason to use none, which is what returning here amounted to. The
        // right instrument is the one the `rabin-karp` branch above already
        // uses, and the pairs half already trusts: what the two locations
        // really share, read from the source at the coordinates claimed.
        //
        // Without this the two halves of this gate reached opposite verdicts on
        // one pair. On php-parser the merged baseline claims
        // `Class_.php:96 + Trait_.php:29`, which begins ten lines above the
        // duplication — line 29 is `$this->name = $name;` and the shared body
        // starts at 39. The pairs half set it aside as an over-report sharing
        // nothing; this half counted it a miss, and failed the engine for
        // reporting the same code at the line it actually begins on.
        if (superset_pair_is_contiguous_claim($location, $other, $attributionRows, $attributionByFile)) {
            $true = superset_true_match_length(
                $location['file'],
                $location['start'],
                $other['file'],
                $other['start'],
                $config,
            );

            if ($true === null || $true > 0) {
                // Unlocatable, or genuinely shared and genuinely not reported.
                return null;
            }

            $contiguousOverreport = true;

            continue;
        }

        $overlap = superset_true_bag_overlap(
            $location['file'],
            $location['start'],
            $other['file'],
            $other['start'],
            $row['tokens'],
            $config,
        );

        if ($overlap !== null && ($best === null || $overlap > $best)) {
            $best = $overlap;
        }
    }

    // The best supporting pair wins, and a bag pair that clears theta supports
    // the location however little a contiguous pair of the same class shares.
    if ($best !== null) {
        return $best < $config->minSimilarity
            ? ['coverage' => $best, 'measure' => 'bag']
            : null;
    }

    // No pair had bag standing, and every pair that could be judged
    // contiguously shares nothing at all at the coordinates named.
    return $contiguousOverreport
        ? ['coverage' => 0.0, 'measure' => 'run']
        : null;
}

// Index the unified report by every file it mentions.
$byFile = [];

foreach ($unifiedRows as $index => $row) {
    foreach ($row['locations'] as $location) {
        $byFile[$location['file']][$index] = true;
    }
}

$missingLocations    = [];
$locationOverreports = [];
$partialCoverage     = [];
$pairsChecked     = 0;
$pairsSame        = 0;
$pairsLonger      = 0;
$overreports      = [];
$unexplained      = [];
$inapplicable     = [];
$usedRows         = [];

foreach ($baselineRows as $row) {
    // 1. Every location the baseline named must be reported somewhere.
    foreach ($row['locations'] as $location) {
        // How much of the claim is covered, not merely whether some part of it
        // is. Coverage is a membership test and stays one — a location is
        // covered or it is not — but a gate that only ever prints "covered"
        // cannot be checked by a reader, and the thinnest coverage in a run is
        // the one number that says how much slack the test is running on. So
        // the best overlap is measured across every row rather than stopping at
        // the first that overlaps, and reported below.
        $span    = $location['end'] - $location['start'] + 1;
        $best    = 0;
        $bestRow = null;

        foreach (array_keys($byFile[$location['file']] ?? []) as $index) {
            $occurrence = superset_location_covering($unifiedRows[$index], $location);

            if ($occurrence === null) {
                continue;
            }

            $shared = min($occurrence['end'], $location['end'])
                - max($occurrence['start'], $location['start'])
                + 1;

            if ($shared > $best) {
                $best    = $shared;
                $bestRow = $index;
            }
        }

        if ($bestRow !== null) {
            $usedRows[$bestRow] = true;

            if ($best < $span) {
                $partialCoverage[] = [
                    'label'    => $row['label'],
                    'location' => $location,
                    'shared'   => $best,
                    'span'     => $span,
                ];
            }

            continue;
        }

        // Ruling 6: adjudicate before claiming a miss. A baseline location is a
        // *miss* only when the baseline's own claim about it survives an
        // independent recompute; a token-bag location whose bijective coverage
        // falls below the baseline's own `--min-similarity` is a **baseline
        // over-report**, counted and listed rather than charged to this engine.
        //
        // M1's RK-length precedent, applied one level up: neither engine is
        // believed and the source decides. No new constant — theta is read from
        // the run's configuration, which is the same number the baseline used to
        // make the claim.
        $verdict = superset_location_verdict(
            $location,
            $row,
            $baselineAlgorithm,
            $attributionRows,
            $attributionByFile,
            $config,
        );

        if ($verdict === null) {
            $missingLocations[] = ['label' => $row['label'], 'location' => $location];

            continue;
        }

        // Which instrument decided it comes from the adjudicator, not from the
        // baseline's name: a merged baseline holds claims of both kinds, and
        // printing one under the other's name would misdescribe the evidence.
        $locationOverreports[] = [
            'label'    => $row['label'],
            'location' => $location,
            'coverage' => $verdict['coverage'],
            'measure'  => $verdict['measure'],
        ];
    }

    // 2. Every pair inside the baseline clone must be reported together, at a
    //    length the source agrees with.
    $count = count($row['locations']);

    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $a = $row['locations'][$i];
            $b = $row['locations'][$j];

            if ($a['file'] === $b['file'] && $a['start'] === $b['start']) {
                continue;
            }

            $pairsChecked++;

            // Attribution first: a token-bag pair gets no verdict from this
            // adjudicator, in either direction.
            $orderFreeClaim = $baselineAlgorithm !== 'rabin-karp'
                && !superset_pair_is_contiguous_claim($a, $b, $attributionRows, $attributionByFile);

            $reported = 0;

            foreach (array_keys($byFile[$a['file']] ?? []) as $index) {
                $candidate = $unifiedRows[$index];
                $shared    = superset_pair_tokens($candidate, $a, $b);

                if ($shared > $reported) {
                    $reported         = $shared;
                    $usedRows[$index] = true;
                }
            }

            if ($reported > $row['tokens']) {
                $pairsLonger++;

                continue;
            }

            if ($reported === $row['tokens']) {
                $pairsSame++;

                continue;
            }

            // An order-free claim is settled by an order-free recompute (ruling R,
            // discharging §3.3's deferred condition). The baseline is vindicated
            // when the two spans really do hold the same material at θ; it is an
            // over-report when they do not, and the pair is only unexplained when
            // the material is there and unified missed it.
            if ($orderFreeClaim) {
                $overlap = superset_true_bag_overlap(
                    $a['file'],
                    $a['start'],
                    $b['file'],
                    $b['start'],
                    $row['tokens'],
                    $config,
                );

                if ($overlap === null) {
                    $inapplicable[] = sprintf(
                        '%s:%d ↔ %s:%d',
                        basename($a['file']),
                        $a['start'],
                        basename($b['file']),
                        $b['start'],
                    );

                    continue;
                }

                $entry = [
                    'label'    => sprintf(
                        '%s:%d ↔ %s:%d',
                        basename($a['file']),
                        $a['start'],
                        basename($b['file']),
                        $b['start'],
                    ),
                    'baseline' => $row['tokens'],
                    'unified'  => $reported,
                    'true'     => sprintf('%.2f bag coverage', $overlap),
                ];

                // The baseline's own threshold, not the engine's: the claim
                // being adjudicated is the token bag's, and it made it at
                // `--min-similarity`. Reading it from the configuration also
                // keeps the adjudicator free of any engine constant.
                if ($overlap < $config->minSimilarity) {
                    $overreports[] = $entry;
                } else {
                    $unexplained[] = $entry;
                }

                continue;
            }

            $true  = superset_true_match_length($a['file'], $a['start'], $b['file'], $b['start'], $config);
            $entry = [
                'label'    => sprintf(
                    '%s:%d ↔ %s:%d',
                    basename($a['file']),
                    $a['start'],
                    basename($b['file']),
                    $b['start'],
                ),
                'baseline' => $row['tokens'],
                'unified'  => $reported,
                'true'     => $true,
            ];

            // The source settles it. The unified engine is vindicated when what
            // the two locations really share is no more than what it reported.
            if ($true !== null && $true <= $reported) {
                $overreports[] = $entry;
            } else {
                $unexplained[] = $entry;
            }
        }
    }
}

bcb_check(
    $log,
    $baselineRows !== [],
    'the baseline found clones, so subsumption is being checked against something',
    count($baselineRows) . ' ' . $baselineName . ' clones',
);

bcb_check(
    $log,
    $missingLocations === [],
    'every location the baseline reports, and an independent recompute supports, is reported',
    $missingLocations === []
        ? 'all surviving locations covered' . superset_thinnest($partialCoverage)
            . ($locationOverreports === []
                ? ''
                : sprintf(
                    '; %d over-report entries at %d distinct locations set aside (ruling 6)',
                    count($locationOverreports),
                    superset_distinct_locations($locationOverreports),
                ))
        : sprintf(
            '%d surviving entries at %d distinct locations NOT reported; %d over-report entries at %d distinct locations set aside (ruling 6)',
            count($missingLocations),
            superset_distinct_locations($missingLocations),
            count($locationOverreports),
            superset_distinct_locations($locationOverreports),
        ),
);

// The thinnest coverage in the run, listed rather than summarised away. These
// are passes, not failures: the baseline's claim is reported, over less of its
// span than the baseline drew it. That is usually the baseline's own window
// opening early — a token bag scores a whole function at `--min-similarity`, so
// its span starts at a signature this engine cannot match token for token — and
// occasionally it is this engine stopping short. The reader gets the numbers
// either way, because "covered" on its own is a claim nobody can check.
$partialLocations = superset_thinnest_per_location($partialCoverage);

usort(
    $partialLocations,
    static fn(array $a, array $b): int => [$a['shared'] / $a['span'], $a['location']['file'], $a['location']['start']]
        <=> [$b['shared'] / $b['span'], $b['location']['file'], $b['location']['start']],
);

foreach (array_slice($partialLocations, 0, $listLimit) as $entry) {
    printf(
        "    PARTIAL   %s:%d  (from baseline clone %s) — covered over %d of the %d lines claimed\n",
        basename($entry['location']['file']),
        $entry['location']['start'],
        superset_short_label($entry['label']),
        $entry['shared'],
        $entry['span'],
    );
}

if (count($partialLocations) > $listLimit) {
    printf("    … and %d more partly covered locations\n", count($partialLocations) - $listLimit);
}

foreach (array_slice($missingLocations, 0, $listLimit) as $miss) {
    printf(
        "    SURVIVOR  %s:%d  (from baseline clone %s) — not reported by the unified engine\n",
        basename($miss['location']['file']),
        $miss['location']['start'],
        superset_short_label($miss['label']),
    );
}

if (count($missingLocations) > $listLimit) {
    printf("    … and %d more surviving locations\n", count($missingLocations) - $listLimit);
}

// Listed, not merely counted: ruling 6 puts these in the release evidence, and a
// count the reader cannot check is not evidence.
foreach (array_slice($locationOverreports, 0, $listLimit) as $entry) {
    printf(
        $entry['measure'] === 'run'
            ? "    OVER-REPORT  %s:%d  (from baseline clone %s) — the source shares no run at these coordinates%.0s%.0s\n"
            : "    OVER-REPORT  %s:%d  (from baseline clone %s) — bijective coverage %.2f < %.2f\n",
        basename($entry['location']['file']),
        $entry['location']['start'],
        superset_short_label($entry['label']),
        $entry['coverage'],
        $config->minSimilarity,
    );
}

if (count($locationOverreports) > $listLimit) {
    printf(
        "    … and %d more baseline over-reported locations\n",
        count($locationOverreports) - $listLimit,
    );
}

bcb_check(
    $log,
    $unexplained === [],
    'every pair the baseline reports is reported at a length the source agrees with',
    sprintf(
        '%d pairs — %d same length, %d longer, %d shorter (%d of those over-reported by the baseline), %d unexplained%s',
        $pairsChecked,
        $pairsSame,
        $pairsLonger,
        count($overreports) + count($unexplained),
        count($overreports),
        count($unexplained),
        $inapplicable === []
            ? ''
            : sprintf(
                '; %d INAPPLICABLE (order-free baseline findings — this adjudicator recomputes a contiguous run and cannot arbitrate them; not counted either way)',
                count($inapplicable),
            ),
    ),
);

foreach (array_slice($inapplicable, 0, $listLimit) as $label) {
    printf("    INAPPLICABLE  %s — token-bag attribution; adjudicator measures contiguity\n", $label);
}

foreach (array_slice($unexplained, 0, $listLimit) as $entry) {
    printf(
        "    UNEXPLAINED  %s — baseline %d, unified %d, actually shared %s\n",
        $entry['label'],
        $entry['baseline'],
        $entry['unified'],
        $entry['true'] === null ? 'unknown' : (string) $entry['true'],
    );
}

foreach (array_slice($overreports, 0, $listLimit) as $entry) {
    printf(
        "    OVER-REPORT  %s — baseline %d, unified %d, actually shared %s\n",
        $entry['label'],
        $entry['baseline'],
        $entry['unified'],
        $entry['true'] === null ? 'unknown' : (string) $entry['true'],
    );
}

if (count($overreports) > $listLimit) {
    printf("    … and %d more baseline over-reports\n", count($overreports) - $listLimit);
}

printf(
    "\n  %d of %d unified clones were used to cover the baseline; %d report duplication the baseline did not.\n",
    count($usedRows),
    count($unifiedRows),
    count($unifiedRows) - count($usedRows),
);

exit(bcb_check_summary($log, 'subsumption check'));
