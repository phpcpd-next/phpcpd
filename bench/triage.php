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
 * Stage 0's acceptance check — ruling T.
 *
 * The corpus ladder is the fixture, and Stage 0 pointed at its first rung must
 * approximately reproduce its fourth.
 *
 * ## The corpus is never named
 *
 * The dogfood root is an argument, never defaulted and never recorded, and only
 * aggregate numbers are ever copied into an audit packet.
 *
 * Usage:
 *   php bench/triage.php check --dogfood=<root> [--worksheet=<file>] [--key=<file>] [--sites=<file>]
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;
use LucianoPereira\PhpcpdNext\Orphans;
use LucianoPereira\PhpcpdNext\Presets;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Facts\RegionStructure;
use LucianoPereira\PhpcpdNext\Triage\Stage0;
use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * The corpus ladder, in files: every rung of ruling K's frozen definition, each
 * one the product's own machinery rather than a hand-written list.
 *
 * @return array{1: list<string>, 2: list<string>, 3: list<string>, 4: list<string>}
 */
function triage_ladder(string $root): array
{
    $finder = new FileFinder();
    $preset = Presets::get('laravel');

    if ($preset === null) {
        fwrite(STDERR, "the laravel preset is missing\n");

        exit(1);
    }

    $one   = $finder->find([$root], ['.php'], ['vendor', 'node_modules'], false);
    $two   = $finder->find([$root], ['.php'], [], true);
    $three = $finder->find([$root], $preset->suffixes, $preset->exclude, true);

    $whole = [];

    foreach (Orphans::detect($root, preset: 'laravel')->all() as $orphan) {
        if ($orphan->entireFileOrphaned) {
            $whole[$orphan->symbol->file] = true;
        }
    }

    $four = [];

    foreach ($three as $file) {
        if (!isset($whole[$file])) {
            $four[] = $file;
        }
    }

    return [1 => $one, 2 => $two, 3 => $three, 4 => $four];
}

$arguments = bcb_argv();
$command   = $arguments[1] ?? '';
$dogfood    = null;
$key        = '';
$worksheet  = '';
$sitesTable = '';

foreach (array_slice($arguments, 2) as $argument) {
    if (str_starts_with($argument, '--dogfood=')) {
        $dogfood = rtrim(substr($argument, 10), '/');
    } elseif (str_starts_with($argument, '--key=')) {
        $key = substr($argument, 6);
    } elseif (str_starts_with($argument, '--worksheet=')) {
        $worksheet = substr($argument, 12);
    } elseif (str_starts_with($argument, '--sites=')) {
        $sitesTable = substr($argument, 8);
    }
}

/**
 * The span tier, applied to the worksheet: which findings does
 * {@see RegionStructure} call a literal table repeating itself?
 *
 * A file-level tier is scored by asking whether a finding's files survive; a
 * span-level tier cannot be, because it removes no file. It is scored instead by
 * replaying the rule over the finding's own sites — the same rule object the
 * engine uses, never a second implementation of it — with each site's reported
 * line range mapped back to the token span the engine saw.
 *
 * @param  array<string, array{consensus: string, sites: list<array{0: string, 1: int, 2: int}>}> $findings
 * @return array<string, true> the findings the tier silences
 */
function triage_span_tier(string $dogfood, array $findings): array
{
    $encoder = new DefaultStrategy(new StrategyConfiguration(5, 70, Normalization::Raw, 1.0));

    /** @var array<string, ?RegionStructure> $structures */
    $structures = [];
    /** @var array<string, list<int>> $lines */
    $lines = [];

    $load = static function (string $relative) use ($dogfood, $encoder, &$structures, &$lines): bool {
        if (array_key_exists($relative, $structures)) {
            return $structures[$relative] !== null;
        }

        $source = @file_get_contents($dogfood . '/' . $relative);

        if ($source === false) {
            $structures[$relative] = null;

            return false;
        }

        $structures[$relative] = RegionStructure::fromSource($source);
        $lines[$relative]      = $encoder->tokenize($source)->tokenRealLines;

        return true;
    };

    // The worksheet records a site as a first line and a line count; the rule
    // asks about token indices. The span is every significant token whose real
    // line falls in the range, which is the same set the engine's own span
    // covered.
    /**
     * @param  list<int> $tokenLines
     * @return ?array{0: int, 1: int}
     */
    $span = static function (array $tokenLines, int $start, int $count): ?array {
        $last  = $start + $count - 1;
        $first = null;
        $end   = null;

        $index = 0;

        foreach ($tokenLines as $line) {
            if ($line >= $start && $line <= $last) {
                $first ??= $index;
                $end     = $index;
            }

            $index++;
        }

        return $first === null || $end === null ? null : [$first, $end - $first + 1];
    };

    $silenced = [];

    foreach ($findings as $id => $finding) {
        foreach ($finding['sites'] as $i => $a) {
            foreach ($finding['sites'] as $j => $b) {
                if ($j <= $i || $a[0] !== $b[0] || !$load($a[0])) {
                    continue;
                }

                $tokenLines = $lines[$a[0]] ?? [];
                $spanA      = $span($tokenLines, $a[1], $a[2]);
                $spanB      = $span($tokenLines, $b[1], $b[2]);
                $shape      = $structures[$a[0]];

                if ($spanA === null || $spanB === null || $shape === null) {
                    continue;
                }

                if ($shape->sameLiteralTable($spanA[0], $spanA[1], $spanB[0], $spanB[1])) {
                    $silenced[$id] = true;

                    break 2;
                }
            }
        }
    }

    return $silenced;
}

/**
 * Read the per-site table `bench/relocate-worksheet.php --sites` writes.
 *
 * @return array<string, array{consensus: string, sites: list<array{0: string, 1: int, 2: int}>}>
 */
function triage_read_sites(string $path): array
{
    /** @var array<string, string> $consensus */
    $consensus = [];
    /** @var array<string, list<array{0: string, 1: int, 2: int}>> $sites */
    $sites = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $columns = explode("\t", $line);

        if (count($columns) < 6) {
            continue;
        }

        $consensus[$columns[0]] = $columns[1];
        $sites[$columns[0]][]   = [$columns[3], (int) $columns[4], (int) $columns[5]];
    }

    $findings = [];

    foreach ($consensus as $id => $verdict) {
        $findings[$id] = ['consensus' => $verdict, 'sites' => $sites[$id] ?? []];
    }

    return $findings;
}

/**
 * Ruling U's tracking metric: re-score the preserved 60-finding worksheet with a
 * tier applied, and report the precision that tier projects.
 *
 * Deliberately a *report* and never a gate. Ruling U's own wording is that "a
 * projection is never a substitute for the final two-rater pass; it is the
 * between-rounds instrument", and a projection wired into `bcb_check()` would be
 * a number this session could pass by changing the tier that produced it. The
 * two-rater pass remains the only thing that decides the precision bar.
 *
 * A finding is silenced when *any* file it spans is triaged out: a clone pair
 * needs both of its sides, so losing one side removes the finding rather than
 * shortening it.
 *
 * @param array<string, int> $kept Stage 0's kept set, keyed by absolute path
 */
function triage_tracking_metric(array $kept, string $dogfood, string $worksheet, string $key, string $sites = ''): void
{
    // The span tier, when its per-site table is supplied: a second column in the
    // same report, because ruling U asks for the worksheet re-scored *after each
    // tier lands* and the two tiers are meant to be read together.
    $spanSilenced = $sites === '' ? [] : triage_span_tier($dogfood, triage_read_sites($sites));

    /** @var array<string, string> $engines */
    $engines = [];

    foreach (file($key, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $columns = explode("\t", $line);
        $engines[$columns[0]] = $columns[1] ?? '';
    }

    /** @var array<string, list<string>> $silencedIds */
    $silencedIds = ['Y' => [], 'N' => []];

    $counts = [
        'before'      => ['Y' => 0, 'N' => 0],
        'after'       => ['Y' => 0, 'N' => 0],
        'both'        => ['Y' => 0, 'N' => 0],
        'silenced'    => ['Y' => 0, 'N' => 0],
        'spanOnly'    => ['Y' => 0, 'N' => 0],
        'unscorable'  => 0,
        'considered'  => 0,
    ];
    /** @var array<string, list<string>> $spanIds */
    $spanIds = ['Y' => [], 'N' => []];

    foreach (file($worksheet, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $columns   = explode("\t", $line);
        $finding   = $columns[0];
        $consensus = $columns[2] ?? '';

        // Only unified's findings, and only the consensus-labelled ones: an
        // unresolved disagreement has no truth to score against.
        if (!str_contains($engines[$finding] ?? '', 'unified')) {
            continue;
        }

        if ($consensus !== 'Y' && $consensus !== 'N') {
            continue;
        }

        $counts['considered']++;
        $counts['before'][$consensus]++;

        $files = [];

        foreach (array_slice($columns, 3) as $relative) {
            if ($relative !== '') {
                $files[] = $relative;
            }
        }

        // Never relocated to a file, so no file-level tier can be shown to
        // silence it. Held as surviving — the conservative direction, because
        // assuming otherwise would inflate the projection.
        if ($files === []) {
            $counts['unscorable']++;
            $counts['after'][$consensus]++;
            $counts['both'][$consensus]++;

            continue;
        }

        $silenced = false;

        foreach ($files as $relative) {
            if (!isset($kept[$dogfood . '/' . $relative])) {
                $silenced = true;
            }
        }

        $counts[$silenced ? 'silenced' : 'after'][$consensus]++;

        if ($silenced) {
            $silencedIds[$consensus][] = $finding;
        }

        if (!$silenced && isset($spanSilenced[$finding])) {
            $counts['spanOnly'][$consensus]++;
            $spanIds[$consensus][] = $finding;
        } elseif (!$silenced) {
            $counts['both'][$consensus]++;
        }
    }

    $precision = static function (int $yes, int $no): string {
        $total = $yes + $no;

        return $total === 0 ? '  n/a' : sprintf('%.3f', $yes / $total);
    };

    printf("\nTracking metric (ruling U 2) — the worksheet re-scored with Stage 0 applied\n");
    printf("  a report, never a gate: the two-rater pass is what decides the bar\n\n");

    printf("  unified findings with a consensus label   %3d\n", $counts['considered']);
    printf("  of those, never relocated to a file       %3d   (held as surviving)\n\n", $counts['unscorable']);

    printf(
        "  before Stage 0    Y %3d   N %3d    precision %s\n",
        $counts['before']['Y'],
        $counts['before']['N'],
        $precision($counts['before']['Y'], $counts['before']['N']),
    );
    printf(
        "  after  Stage 0    Y %3d   N %3d    precision %s\n",
        $counts['after']['Y'],
        $counts['after']['N'],
        $precision($counts['after']['Y'], $counts['after']['N']),
    );
    if ($sites !== '') {
        printf(
            "  + span tier       Y %3d   N %3d    precision %s\n",
            $counts['both']['Y'],
            $counts['both']['N'],
            $precision($counts['both']['Y'], $counts['both']['N']),
        );
    }

    printf(
        "\n  silenced by Stage 0   %3d N  and  %3d Y%s\n",
        $counts['silenced']['N'],
        $counts['silenced']['Y'],
        $counts['silenced']['Y'] === 0 ? '   (no consensus Y lost)' : '   <-- a consensus Y was lost',
    );

    // Named, not just counted. Step 5's acceptance is stated per finding — every
    // consensus data-table N silenced, zero consensus Y lost — and a count
    // cannot be checked against that sentence.
    foreach (['N', 'Y'] as $consensus) {
        if ($silencedIds[$consensus] !== []) {
            printf("    %s silenced: %s\n", $consensus, implode(' ', $silencedIds[$consensus]));
        }
    }

    if ($sites === '') {
        return;
    }

    printf(
        "\n  silenced by the span tier, on top of Stage 0   %3d N  and  %3d Y%s\n",
        $counts['spanOnly']['N'],
        $counts['spanOnly']['Y'],
        $counts['spanOnly']['Y'] === 0 ? '   (no consensus Y lost)' : '   <-- a consensus Y was lost',
    );

    foreach (['N', 'Y'] as $consensus) {
        if ($spanIds[$consensus] !== []) {
            printf("    %s silenced: %s\n", $consensus, implode(' ', $spanIds[$consensus]));
        }
    }
}

if ($command === 'check') {
    if ($dogfood === null) {
        fwrite(STDERR, "check needs --dogfood=<root>\n");

        exit(1);
    }

    $ladder = triage_ladder($dogfood);

    // Ruling V: pointed at rung 1, believing only rung 2. Stage 0 still triages
    // the whole raw tree — ruling T's acceptance is that it reproduces rung 4
    // from rung 1 — but a file the product's own default excludes remove may not
    // be the evidence that some other file is alive.
    $result = (new Stage0())->triage(
        $ladder[1],
        ComposerManifest::locate([$dogfood]),
        witnesses: $ladder[2],
    );

    $kept = array_flip($result->kept);

    // The acceptance target: rung 4. It was restated at the M4 stop point
    // (ruling on request 2, (e)) as rung 4 MINUS a labelled dump mass, because on
    // that corpus rung 4 still held 1,033 files of dumped tool cache and the
    // unrestated test would have required Stage 0 to keep them. That carve-out
    // was a *training* argument (`--fishy-under`), and M4 §11 already recorded it
    // as no longer needed: the dot-directory product rule leaves such trees at
    // rung 2, so the ladder labels them itself. It goes with the trainer, and the
    // target is rung 4 again — which is what every run of this check since has
    // used, the argument having been passed by nobody.
    $target = [];

    foreach ($ladder[4] as $file) {
        $target[$file] = true;
    }

    $missing = array_keys(array_diff_key($target, $kept));
    $extra   = array_keys(array_diff_key($kept, $target));

    printf("Stage 0 acceptance — the ladder is the fixture\n\n");
    printf("  rung 1 (raw)                     %5d files\n", count($ladder[1]));
    printf("  rung 2 (may witness wiring)      %5d files\n", count($ladder[2]));
    printf("  rung 4 (frozen K)                %5d files   (the acceptance target)\n", count($ladder[4]));
    printf("  Stage 0, from rung 1             %5d files\n\n", count($result->kept));

    foreach ($result->counts() as $reason => $n) {
        printf("  discarded %-9s %5d\n", $reason, $n);
    }

    printf("\n  agreement with the target        %5d\n", count($target) - count($missing));
    printf("  dropped that the target keeps    %5d\n", count($missing));
    printf("  kept that the target drops       %5d   (the preset's policy excludes: blade, migrations, public)\n\n", count($extra));

    foreach (array_slice($missing, 0, 10) as $file) {
        printf("    DROPPED  %s\n", substr($file, strlen($dogfood) + 1));
    }

    // Ruling 4(b), granted by the auditor 2026-09-02: a gate states one corpus per
    // comparison, and **posture differences are corpus lines rather than pass/fail
    // bars**. The 95 % retention bar this check used to assert was the executor's
    // own operationalization of ruling T's "approximately reproduces", scored
    // against a posture that had not been chosen — evidence reported as a verdict.
    // It is retired and the comparison is printed instead.
    //
    // The two lines are now one. This table used to separate `discard` from
    // `demote` by the classifier rung — the only rung whose posture changed what
    // Stage 0 removed. With the estimator retired, every remaining rung proves its
    // case and removes under `discard`, and the postures that keep a file keep all
    // of them, so retention has exactly one value per posture and no curve.
    printf("\n  Retention against the target — reported, not a bar (4(b))\n");
    printf("    %-8s %6s %8s %9s\n", 'posture', 'kept', 'retained', 'coverage cost');

    foreach (['discard' => $kept, 'demote' => array_flip($ladder[1])] as $posture => $keptSet) {
        $dropped = count(array_diff_key($target, $keptSet));
        printf(
            "    %-8s %6d %7.1f%% %9d files\n",
            $posture,
            count($keptSet),
            count($target) === 0 ? 0.0 : 100 * (count($target) - $dropped) / count($target),
            $dropped,
        );
    }

    printf("\n");

    $log = [];

    // Ruling T's second acceptance: no file holding a consensus-Y finding is
    // discarded. The worksheet is the M3 one, relocated by excerpt search — see
    // the M4 packet — and lives outside every repository.
    if ($worksheet !== '') {
        $lost = [];

        foreach (file($worksheet, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $columns = explode("\t", $line);

            if (($columns[2] ?? '') !== 'Y') {
                continue;
            }

            foreach (array_slice($columns, 3) as $relative) {
                if ($relative !== '' && !isset($kept[$dogfood . '/' . $relative])) {
                    $lost[$columns[0] . ' ' . $relative] = true;
                }
            }
        }

        bcb_check(
            $log,
            $lost === [],
            'no file holding a consensus-Y finding is discarded',
            $lost === [] ? '' : implode(', ', array_slice(array_keys($lost), 0, 8)),
        );
    }

    if ($worksheet !== '' && $key !== '') {
        triage_tracking_metric($kept, $dogfood, $worksheet, $key, $sitesTable);
    }

    // Determinism: the same tree twice must give the same answer.
    $again = (new Stage0())->triage(
        $ladder[1],
        ComposerManifest::locate([$dogfood]),
        witnesses: $ladder[2],
    );

    bcb_check($log, $again->kept === $result->kept, 'two runs over one tree agree', '');

    exit(bcb_check_summary($log, 'stage 0 acceptance'));
}

fwrite(STDERR, "usage: php bench/triage.php check --dogfood=<root> [--worksheet=<file>] [--key=<file>] [--sites=<file>]\n");

exit(1);
