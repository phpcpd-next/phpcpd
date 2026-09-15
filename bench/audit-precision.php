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
 * Precision audit — pool, sample, and score (M3).
 *
 * Precision cannot be read off a gate, because "is this really duplicated logic?"
 * is a judgement rather than a measurement. Plan §2 M3 therefore asks for an
 * exhaustive **two-rater** audit over the locations every engine reports, with a
 * written rubric, Cohen's κ as the agreement statistic, and Wilson intervals on
 * the resulting proportions. This script does the mechanical half: it pools the
 * findings, samples them reproducibly, and writes a worksheet the raters fill
 * in; then, given two filled worksheets, it scores them.
 *
 * ## Three modes
 *
 *   pool   <corpus> --out=<file>          write a blank worksheet
 *   score  <rater-a.tsv> <rater-b.tsv>    κ, precision per engine, Wilson intervals
 *   score  <rater-a.tsv>                  one rater only — precision, no κ
 *
 * ## The rubric, so both raters answer the same question
 *
 * For each finding, judging the *named regions themselves* and not the engine:
 *
 *   Y — duplicated logic. The regions carry the same behaviour, and a change to
 *       one would plausibly have to be made to the other. Renamed identifiers,
 *       reordered independent statements, and a diverged copy all still count:
 *       the question is whether a maintainer is looking at one thing written
 *       twice.
 *   N — not duplicated logic. The regions match on shape while carrying no
 *       shared behaviour — two unrelated literal tables that normalize to the
 *       same token sequence, an import-and-class-declaration preamble, a run of
 *       accessors that merely look alike. Nothing would have to change together.
 *   ? — cannot tell from the excerpt. Counted, reported, and excluded from the
 *       precision proportions rather than silently resolved either way.
 *
 * A rater sees the excerpts and the sizes. A rater does **not** see the other
 * rater's answers, does **not** see which engines reported the finding, and —
 * from M5 — does **not** see which **stratum** the finding is in. All three go to
 * a companion `.key.tsv` that `score` reads, because a rater who can tell that
 * only the engine under test found something, or that the tool has already
 * decided not to assert something, has stopped judging the code, which is the one
 * thing the rubric asks of them.
 *
 * ## The strata (M5)
 *
 * The M5 charter restates ruling U's bar as a **stratified** one: the 0.80
 * two-rater bar applies to the ASSERTED stratum, and the DEMOTED stratum is rated
 * in the same pass with its precision published beside it, always. The guards
 * that keep that a lens rather than a hiding place are that the strata are
 * defined mechanically and **pre-registered before the pool is drawn** (M5 packet
 * §1), that both strata are rated, and that nothing is suppressed.
 *
 * This script computes the stratum of each pooled finding through the shipped
 * `Presentation\Strata` — not a copy — and writes it to the key. `score` then
 * reports each stratum's precision and Wilson interval for every rater, side by
 * side, and evaluates the pre-registered flip criterion without restating it.
 *
 * ## The corpus is never named
 *
 * The dogfood corpus is closed source. Its name, its paths and its source text
 * must not reach this repository, so:
 *
 *   - the corpus directory is an argument and is never defaulted or recorded;
 *   - the worksheet — which necessarily quotes source — refuses to be written
 *     inside this repository, and the path is chosen by the operator;
 *   - findings carry an opaque id, and paths appear as a per-run salted digest
 *     rather than as names, so two findings in one file can still be seen to be
 *     in one file without that file being identifiable;
 *   - only the aggregate numbers are ever copied into the audit packet.
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\Facts\FileFactsIndex;
use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;
use LucianoPereira\PhpcpdNext\Presentation\Strata;
use LucianoPereira\PhpcpdNext\Orphans;
use LucianoPereira\PhpcpdNext\PresetDetection;
use LucianoPereira\PhpcpdNext\Presets;
use LucianoPereira\PhpcpdNext\Triage\Stage0;

$arguments = array_slice(bcb_argv(), 1);
$mode      = $arguments[0] ?? '';

if ($mode === 'pool') {
    exit(bcb_precision_pool(array_slice($arguments, 1)));
}

if ($mode === 'score') {
    exit(bcb_precision_score(array_slice($arguments, 1)));
}

fwrite(STDERR, <<<USAGE
Usage:
  php bench/audit-precision.php pool  <corpus-dir> --out=<worksheet.tsv> [--sample=60] [--min-tokens=100]
                                      [--triage] [--wired-only] [--preset=<name>] [--max-files=N]
                                      [--engines=a,b,c] [--exclude=a,b]

  --triage  build the corpus with Stage 0 (ruling T's one definition, shipped
            posture) instead of the M3-era ladder; --wired-only is then redundant
            and is ignored.
  php bench/audit-precision.php score <rater-a.tsv> [<rater-b.tsv>]

USAGE);

exit(1);

/**
 * Refuse to write anything quoting corpus source into this repository.
 *
 * The standing rule is that the dogfood corpus's source strings never reach the
 * repo. A worksheet is nothing but corpus source, so the guard is here rather
 * than in the operator's memory.
 */
function bcb_refuse_inside_repo(string $path): void
{
    $repo = realpath(dirname(__DIR__));
    $dir  = realpath(dirname($path));

    if ($repo === false || $dir === false) {
        return;
    }

    if ($dir === $repo || str_starts_with($dir . DIRECTORY_SEPARATOR, $repo . DIRECTORY_SEPARATOR)) {
        fwrite(STDERR, sprintf(
            "Refusing to write the worksheet inside the repository (%s).\n"
            . "It quotes corpus source, which must never be committed. Choose a path outside it.\n",
            $dir,
        ));

        exit(1);
    }
}

/** @param list<string> $arguments */
function bcb_precision_pool(array $arguments): int
{
    $corpus    = null;
    $out       = null;
    $sample    = 60;
    $minTokens = 100;
    $maxFiles  = 0;
    $engines   = ['unified', 'rabin-karp', 'tokenbag'];
    $preset    = null;
    $triage    = false;
    $wiredOnly = false;
    /** @var list<non-empty-string> $exclude */
    $exclude = [];

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--out=')) {
            $out = substr($argument, strlen('--out='));

            continue;
        }

        if (str_starts_with($argument, '--sample=')) {
            $sample = max(1, (int) substr($argument, strlen('--sample=')));

            continue;
        }

        if (str_starts_with($argument, '--min-tokens=')) {
            $minTokens = (int) substr($argument, strlen('--min-tokens='));

            continue;
        }

        // Scan only the first N files of the corpus, in sorted order.
        //
        // Not a convenience. The unified engine cannot currently complete this
        // corpus at all: seed-pair enumeration is quadratic in a fingerprint's
        // posting count, and `FingerprintIndex::POSTINGS_CAP` bounds the list
        // rather than the pairs it yields, so one capped fingerprint emits
        // C(1000, 2) = 499,500 seed pairs. Measured on the dogfood corpus, the
        // normalized view reaches 14.7 million seed pairs at 600 files and
        // exhausts 3 GB. Until that is ruled on, an audit of this corpus has to
        // name the slice it managed to scan rather than claim the whole of it.
        if (str_starts_with($argument, '--max-files=')) {
            $maxFiles = max(1, (int) substr($argument, strlen('--max-files=')));

            continue;
        }

        // Which engines contribute to the pool.
        //
        // The default is every engine, because the plan asks for the locations
        // *any* engine reports. It stays an option because an audit that names
        // the engines it pooled is honest and one that silently waits forever is
        // not — the lesson of the removed suffix tree, which needed 199 s for 50
        // files of the dogfood corpus against the unified engine's 0.63 s and did
        // not finish 100 in ten minutes.
        if (str_starts_with($argument, '--engines=')) {
            $engines = array_values(array_filter(array_map('trim', explode(',', substr($argument, strlen('--engines='))))));

            continue;
        }

        if (str_starts_with($argument, '--preset=')) {
            $preset = substr($argument, strlen('--preset='));

            continue;
        }

        if (str_starts_with($argument, '--exclude=')) {
            foreach (explode(',', substr($argument, strlen('--exclude='))) as $pattern) {
                $pattern = trim($pattern);

                if ($pattern !== '') {
                    $exclude[] = $pattern;
                }
            }

            continue;
        }

        // Rate only code that is actually part of the program.
        //
        // Reading every .php file on disk and calling all of it "the corpus" is
        // naive, and this repository already knows better: `--orphans` exists to
        // say which files nothing references. A scratch directory of saved
        // near-duplicates, a generated cache, a dead class kept "just in case" —
        // duplication among those is a different question from duplication in
        // live code, and mixing them makes a precision number that answers
        // neither. With this flag the corpus is first put through
        // {@see Orphans::detect()} and every entire-file orphan is dropped, so
        // the audit measures the program rather than the disk.
        if ($argument === '--triage') {
            $triage = true;

            continue;
        }

        if ($argument === '--wired-only') {
            $wiredOnly = true;

            continue;
        }

        $corpus ??= $argument;
    }

    if ($corpus === null || !is_dir($corpus) || $out === null) {
        fwrite(STDERR, "pool needs a corpus directory and --out=<file>\n");

        return 1;
    }

    bcb_refuse_inside_repo($out);

    // The corpus is program text, not every .php file on the disk.
    //
    // `bcb_files()` prunes four directory names, which is not nearly enough on a
    // real application: measured here, an even-strided 400-file slice was 110
    // files of one dumped static-analysis cache living under a scratch
    // directory. A `var_export` dump is a `.php` file and is not program text,
    // and `--wired-only` cannot catch it either, because a file that declares no
    // symbol gives the orphan detector nothing to mark.
    //
    // So the preset's own exclude list is applied to the *scan* corpus and not
    // only to the orphan pass. That list is this project's existing answer to
    // "which files are a framework's predictable noise" (generated caches,
    // vendored trees, scaffolding), and reusing it keeps the audit from growing
    // a private, ad-hoc idea of what counts as code. `--exclude=` adds anything
    // a particular corpus needs on top.
    $presetExcludes = [];

    if ($preset !== null && ($resolved = Presets::get($preset)) !== null) {
        $presetExcludes = $resolved->exclude;
    }

    // A pool built over a file set the product would never scan measures the
    // wrong thing, and it did: rated on firefly-iii without `--preset=laravel`,
    // the sample carried a family of Laravel migration findings that no user of
    // this tool can see. The preset is auto-detected in an ordinary run and
    // excludes `database/migrations` by name — "up()/down() boilerplate is
    // duplicate by design" — so those findings were rated against a
    // configuration nobody runs.
    //
    // Not applied silently. A rated pool is a document about a corpus, and
    // quietly changing which corpus would be worse than the gap: this says what
    // it found and names the flag, the way every other default in this project
    // that changes a scan announces itself.
    $detected = PresetDetection::detect([$corpus]);

    if ($preset === null && $detected !== null) {
        printf(
            "NOTE: %s detected in this corpus and no --preset given.\n"
            . "      An ordinary run applies it, so the pool below is drawn over a file set\n"
            . "      the product would not scan. Re-run with --preset=%s to rate what a user sees.\n\n",
            PresetDetection::label($detected->name),
            $detected->name,
        );
    }

    $files = bcb_files($corpus, ['vendor', 'node_modules', 'storage', 'bootstrap/cache', ...$presetExcludes, ...$exclude]);
    sort($files);

    // Ruling T's requirement that there be exactly one definition of "the
    // corpus", shared by the engine, the bench walker and this pool. Everything
    // above is the M3-era ladder, kept so M3's numbers can still be reproduced;
    // `--triage` replaces its wiring half with Stage 0 itself, in the shipped
    // posture. A pool built on a different definition than the engine measures a
    // corpus nobody runs.
    if ($triage) {
        $before = count($files);
        $result = (new Stage0())->triage(
            $files,
            ComposerManifest::locate([$corpus]),
            witnesses: $files,
        );

        $removed = [];

        foreach ($result->discarded as $decision) {
            $removed[$decision->file] = true;
        }

        $files = array_values(array_filter($files, static fn(string $file): bool => !isset($removed[$file])));

        fwrite(STDERR, sprintf(
            "triage (Stage 0, shipped posture): %d of %d files removed, %d left\n",
            $before - count($files),
            $before,
            count($files),
        ));
    }

    if ($wiredOnly && !$triage) {
        $orphaned = [];

        foreach (Orphans::detect(paths: $corpus, preset: $preset)->all() as $orphan) {
            if ($orphan->entireFileOrphaned) {
                $orphaned[realpath($orphan->symbol->file) ?: $orphan->symbol->file] = true;
            }
        }

        $before = count($files);
        $files  = array_values(array_filter(
            $files,
            static fn(string $file): bool => !isset($orphaned[realpath($file) ?: $file]),
        ));

        fwrite(STDERR, sprintf(
            "wired-only: %d of %d files dropped as entire-file orphans, %d left\n",
            $before - count($files),
            $before,
            count($files),
        ));
    }

    if ($maxFiles > 0 && count($files) > $maxFiles) {
        // An even stride over the sorted list, not the first N.
        //
        // Taking a prefix samples one corner of a tree: measured on the dogfood
        // corpus, the first 200 files in sorted order were 196 files of one
        // scratch directory full of saved near-duplicates, which reported 952
        // clones and would have made the audit a study of that directory. A
        // stride spreads the slice over every part of the tree and is just as
        // reproducible.
        $step   = count($files) / $maxFiles;
        $spread = [];

        for ($i = 0; $i < $maxFiles; $i++) {
            $spread[] = $files[(int) floor($i * $step)];
        }

        $files = $spread;
        fwrite(STDERR, sprintf("corpus: %d files, sampled on an even stride over the sorted tree\n", count($files)));
    } else {
        fwrite(STDERR, sprintf("corpus: %d files\n", count($files)));
    }

    // A per-run salt, so a digest cannot be matched back to a path by anyone
    // holding the corpus and this script, and cannot be correlated across runs.
    $salt    = bin2hex(random_bytes(16));
    $digests = [];
    $digest  = static function (string $path) use ($salt, &$digests): string {
        return $digests[$path] ??= 'f' . substr(hash('xxh128', $salt . $path), 0, 6);
    };

    /** @var array<string, array{engines: array<string, true>, lines: int, tokens: int, sites: list<array{0: string, 1: int}>, paths: list<array{0: string, 1: int}>, strata: list<string>}> $pool */
    $pool = [];

    $strata = new Strata(new FileFactsIndex());

    fwrite(STDERR, 'engines pooled: ' . implode(', ', $engines) . "\n");

    foreach ($engines as $engine) {
        fwrite(STDERR, '  ' . $engine . ' … ');

        try {
            // `name+fuzzy` pools that engine under identifier normalization, so
            // the question "what does --fuzzy add, and is it true?" is rated in
            // the same pass and by the same rater as everything else rather than
            // argued about. The suffix is not a fourth engine: it is a switch on
            // one, and the key file records it as written so a reader can tell
            // which of the two produced a finding.
            // Three suffixes, because there are three matching modes and the
            // pool has to be able to name the one that ships. A bare name is raw
            // text; `+fuzzy` is name-blind normalization; `+anchored` is
            // normalization with type keywords kept concrete, which is the
            // default since 2.0.0. Before that default moved, `+fuzzy` and "what
            // ships" were the same thing and the bare name carried the other
            // mode, so two suffixes were enough — and a pool drawn with two of
            // them now rates every mode except the one users get.
            [$fuzzy, $anchored, $suffix] = match (true) {
                str_ends_with($engine, '+anchored') => [true, true, 9],
                str_ends_with($engine, '+fuzzy')    => [true, false, 6],
                default                             => [false, false, 0],
            };

            $algorithm = $suffix === 0 ? $engine : substr($engine, 0, -$suffix);

            $map = bcb_detect($files, [
                'algorithm'    => $algorithm,
                'fuzzy'        => $fuzzy,
                'typeAnchored' => $anchored,
                'minTokens'    => $minTokens,
                'minLines'     => 5,
            ]);
        } catch (Throwable $e) {
            fwrite(STDERR, "failed: " . $e->getMessage() . "\n");

            continue;
        }

        $clones = $map->clones();
        fwrite(STDERR, count($clones) . " clones\n");

        foreach ($clones as $clone) {
            $sites = [];
            $paths = [];

            foreach ($clone->files() as $file) {
                $paths[] = [$file->name, $file->startLine];
            }

            // One sort, and the digests derived from its result.
            //
            // The two lists are read **index by index** when the worksheet is
            // written — site *i*'s digest label beside site *i*'s excerpt — and
            // sorting them independently does not keep them aligned: digest
            // order is a hash order and path order is alphabetical, so a class
            // whose two sites live in two files could be labelled with the other
            // site's digest. That label is what `bench/relocate-worksheet.php`
            // propagates by, so a mislabelled site is a site that relocates to
            // the wrong file.
            sort($paths);

            foreach ($paths as [$path, $startLine]) {
                $sites[] = [$digest($path), $startLine];
            }

            // Two engines reporting the same places is one finding to rate.
            //
            // Keyed on the **paths**, not on the salted digests. The key is
            // internal — it never reaches the worksheet, which still shows only
            // digests — and it decides both the pool's order and, through the
            // stride below, which findings are sampled. Keyed on digests, that
            // order is a function of a per-run random salt, so two runs of this
            // command over one unchanged tree produce two different worksheets:
            // measured here at 14, 17 and 18 table-stratum findings on three
            // consecutive runs of the same pool. A sample that moves when
            // nothing moved is not the reproducible sample the comment below
            // claims, and it would make an auditor's re-run of the pool
            // disagree with the sheet the raters filled in.
            $key = $clone->numberOfLines() . '|' . implode(',', array_map(
                static fn(array $site): string => $site[0] . ':' . $site[1],
                $paths,
            ));

            if (!isset($pool[$key])) {
                $pool[$key] = [
                    'engines' => [],
                    'lines'   => $clone->numberOfLines(),
                    'tokens'  => $clone->numberOfTokens(),
                    'sites'   => $sites,
                    'paths'   => $paths,
                    // Through the shipped class, over the shipped tag set, so
                    // the stratum a finding is rated under is the stratum a user
                    // would see it in.
                    'strata'  => $strata->of($clone),
                ];
            }

            $pool[$key]['engines'][$engine] = true;
        }
    }

    if ($pool === []) {
        fwrite(STDERR, "no findings pooled\n");

        return 1;
    }

    ksort($pool);
    $keys = array_keys($pool);

    // A reproducible, spread sample: an evenly spaced stride over the sorted
    // pool rather than the first N, so the worksheet is not all one directory.
    $take = min($sample, count($keys));
    $step = count($keys) / $take;
    $picked = [];

    for ($i = 0; $i < $take; $i++) {
        $picked[] = $keys[(int) floor($i * $step)];
    }

    $handle = fopen($out, 'wb');

    if ($handle === false) {
        fwrite(STDERR, "cannot write " . $out . "\n");

        return 1;
    }

    /** @var list<string> $keyRows finding id -> engines, written beside the worksheet */
    $keyRows = [];

    fwrite($handle, "# Precision audit worksheet — " . count($picked) . " of " . count($keys) . " pooled findings\n");
    fwrite($handle, "#\n");
    fwrite($handle, "# Put Y, N or ? in the verdict column of each FINDING line. Judge the code,\n");
    fwrite($handle, "# not the engine:\n");
    fwrite($handle, "#   Y  duplicated logic — a change to one copy would plausibly have to be\n");
    fwrite($handle, "#      made to the others. Renames, reorderings and diverged copies count.\n");
    fwrite($handle, "#   N  not duplicated logic — the regions match on shape while sharing no\n");
    fwrite($handle, "#      behaviour (unrelated literal tables, import/class preambles, lookalike\n");
    fwrite($handle, "#      accessors). Nothing would have to change together.\n");
    fwrite($handle, "#   ?  cannot tell from the excerpt.\n");
    fwrite($handle, "#\n");
    fwrite($handle, "# Lines starting with # are ignored by `score`. Do not reorder the file.\n\n");

    foreach ($picked as $index => $key) {
        $entry   = $pool[$key];
        $engines = array_keys($entry['engines']);
        sort($engines);

        fprintf($handle, "FINDING\t%03d\t\t%d lines\t%d tokens\t%d sites\n", $index + 1, $entry['lines'], $entry['tokens'], count($entry['sites']));

        // Which engines reported it goes to the key file, not to the rater.
        // `score` needs the attribution to report precision per engine; a rater
        // who can see that "only the engine under test found this" is no longer
        // judging the code, which is the one thing the rubric asks of them.
        // The stratum goes to the key for the same reason the engine does: a
        // rater who can see that the tool has already declined to assert a
        // finding is no longer judging the code.
        $keyRows[] = sprintf(
            "%03d\t%s\t%s",
            $index + 1,
            implode(' ', $engines),
            $entry['strata'] === [] ? 'asserted' : implode(' ', $entry['strata']),
        );

        // Show enough to judge and no more. A rater has to read sixty of these,
        // and a class of seventeen sites at forty lines apiece is not read, it is
        // skimmed — which produces a verdict about nothing. Two sites are what
        // the rubric's question needs (are *these* the same logic?), the third
        // and fourth guard against a pair that happens to agree, and what is
        // elided is stated rather than quietly dropped.
        foreach (array_slice($entry['paths'], 0, 4, true) as $siteIndex => [$path, $startLine]) {
            fprintf($handle, "#   site %d — %s line %d\n", $siteIndex + 1, $entry['sites'][$siteIndex][0], $startLine);

            $excerpt = bcb_excerpt($path, $startLine, min($entry['lines'], 25));

            foreach (explode("\n", $excerpt) as $line) {
                fwrite($handle, "#     | " . $line . "\n");
            }

            if ($entry['lines'] > 25) {
                fprintf($handle, "#     | … %d more lines of this site not shown\n", $entry['lines'] - 25);
            }
        }

        if (count($entry['paths']) > 4) {
            fprintf($handle, "#   … and %d further sites not shown\n", count($entry['paths']) - 4);
        }

        fwrite($handle, "\n");
    }

    fclose($handle);

    $keyPath = (string) preg_replace('/\.tsv$/', '', $out) . '.key.tsv';
    file_put_contents(
        $keyPath,
        "# finding\tengines\tstratum — kept out of the worksheet so raters judge the code\n"
            . implode("\n", $keyRows) . "\n",
    );

    printf(
        "pooled %d distinct findings, wrote %d to %s\n",
        count($keys),
        count($picked),
        $out,
    );
    printf("engine attribution and strata written to %s (not shown to raters)\n", $keyPath);

    $stratumCounts = ['asserted' => 0, 'demoted' => 0];

    foreach (Strata::NAMES as $name) {
        $stratumCounts[$name] = 0;
    }

    foreach ($picked as $key) {
        $stratumCounts[$pool[$key]['strata'] === [] ? 'asserted' : 'demoted']++;

        foreach ($pool[$key]['strata'] as $name) {
            $stratumCounts[$name]++;
        }
    }

    // Both populations, because they answer different questions: the sample is
    // what gets rated, the pool is whether a stratum reaches anything at all. A
    // stratum that is empty on the sample and non-empty on the pool is a
    // sampling result; one that is empty on both is a result about the stratum,
    // and the two must not be reported as if they were the same thing.
    $poolCounts = ['asserted' => 0, 'demoted' => 0];

    foreach (Strata::NAMES as $name) {
        $poolCounts[$name] = 0;
    }

    foreach ($pool as $entry) {
        $poolCounts[$entry['strata'] === [] ? 'asserted' : 'demoted']++;

        foreach ($entry['strata'] as $name) {
            $poolCounts[$name]++;
        }
    }

    printf("strata — sampled worksheet, then the whole pool:\n");

    foreach ($stratumCounts as $name => $count) {
        printf("  %-12s %4d of %d sampled   %4d of %d pooled\n", $name, $count, count($picked), $poolCounts[$name], count($pool));
    }

    // How many of the scanned files carry the registration role, and — where they
    // do not — which half of the definition refused them. A stratum defined over
    // files needs its denominator stated, or "it reached nothing" cannot be told
    // apart from "there was nothing to reach"; and a definition that reaches
    // nothing should say *which clause* did the refusing rather than leave the
    // reader to guess.
    $roleIndex = new FileFactsIndex();
    $roles     = [
        'registration-role'      => 0,
        'no top-level statement' => 0,
        'no registration at all' => 0,
        'registrations, no majority' => 0,
        'majority, but coupled'  => 0,
        'unreadable'             => 0,
    ];

    foreach ($files as $file) {
        $facts = $roleIndex->for($file);

        if ($facts === null) {
            $roles['unreadable']++;

            continue;
        }

        $role = $facts->role;

        $roles[match (true) {
            $role->registration                         => 'registration-role',
            $role->statements === 0                     => 'no top-level statement',
            $role->registrations === 0                  => 'no registration at all',
            $role->registrations * 2 <= $role->statements => 'registrations, no majority',
            default                                     => 'majority, but coupled',
        }]++;
    }

    printf("the registration role over the %d scanned files:\n", count($files));

    foreach ($roles as $outcome => $count) {
        printf("  %-28s %d\n", $outcome, $count);
    }
    printf("engine coverage of the pool:\n");

    $byEngine = [];

    foreach ($pool as $entry) {
        foreach (array_keys($entry['engines']) as $engine) {
            $byEngine[$engine] = ($byEngine[$engine] ?? 0) + 1;
        }
    }

    ksort($byEngine);

    foreach ($byEngine as $engine => $count) {
        printf("  %-12s %d\n", $engine, $count);
    }

    return 0;
}

/** The source lines of one clone site, for a rater to look at. */
function bcb_excerpt(string $path, int $startLine, int $lines): string
{
    $handle = @fopen($path, 'rb');

    if ($handle === false) {
        return '(unreadable)';
    }

    $out     = [];
    $current = 0;

    while (($line = fgets($handle)) !== false) {
        $current++;

        if ($current < $startLine) {
            continue;
        }

        if ($current >= $startLine + $lines) {
            break;
        }

        $out[] = rtrim($line, "\r\n");
    }

    fclose($handle);

    return implode("\n", $out);
}

/**
 * Read the verdicts out of a filled worksheet.
 *
 * @return array<string, string> finding id -> Y, N or ?
 */
function bcb_read_verdicts(string $path): array
{
    $verdicts = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (!str_starts_with($line, 'FINDING')) {
            continue;
        }

        $columns = explode("\t", $line);
        $id      = trim($columns[1] ?? '');
        $verdict = strtoupper(trim($columns[2] ?? ''));

        if ($id === '') {
            continue;
        }

        $verdicts[$id] = in_array($verdict, ['Y', 'N', '?'], true) ? $verdict : '';
    }

    return $verdicts;
}

/**
 * The Wilson score interval for a proportion — the interval the brief asks for,
 * and the right one for proportions near 0 or 1 on a small sample, where the
 * normal approximation puts its bounds outside [0, 1].
 *
 * @return array{0: float, 1: float}
 */
function bcb_wilson(int $successes, int $trials, float $z = 1.96): array
{
    if ($trials === 0) {
        return [0.0, 1.0];
    }

    $p      = $successes / $trials;
    $z2     = $z * $z;
    $centre = ($p + $z2 / (2 * $trials)) / (1 + $z2 / $trials);
    $spread = $z / (1 + $z2 / $trials) * sqrt($p * (1 - $p) / $trials + $z2 / (4 * $trials * $trials));

    return [max(0.0, $centre - $spread), min(1.0, $centre + $spread)];
}

/** @param list<string> $arguments */
function bcb_precision_score(array $arguments): int
{
    $a = $arguments[0] ?? null;
    $b = $arguments[1] ?? null;

    if ($a === null || !is_file($a)) {
        fwrite(STDERR, "score needs at least one filled worksheet\n");

        return 1;
    }

    $first  = bcb_read_verdicts($a);
    $second = null;
    printf("rater A: %d findings, %d rated\n", count($first), count(array_filter($first)));

    if ($b !== null && is_file($b)) {
        $second = bcb_read_verdicts($b);
        printf("rater B: %d findings, %d rated\n\n", count($second), count(array_filter($second)));

        $shared = array_intersect_key(
            array_filter($first),
            array_filter($second),
        );

        $categories = ['Y', 'N', '?'];
        $agreed     = 0;
        /** @var array<string, int> $marginalA */
        $marginalA = array_fill_keys($categories, 0);
        $marginalB = array_fill_keys($categories, 0);

        foreach (array_keys($shared) as $id) {
            if ($first[$id] === $second[$id]) {
                $agreed++;
            }

            $marginalA[$first[$id]]++;
            $marginalB[$second[$id]]++;
        }

        $n = count($shared);

        if ($n === 0) {
            fwrite(STDERR, "no finding was rated by both raters\n");

            return 1;
        }

        $observed = $agreed / $n;
        $expected = 0.0;

        foreach ($categories as $category) {
            $expected += ($marginalA[$category] / $n) * ($marginalB[$category] / $n);
        }

        $kappa = $expected < 1.0 ? ($observed - $expected) / (1 - $expected) : 1.0;

        printf("both rated:        %d findings\n", $n);
        printf("observed agreement %.3f\n", $observed);
        printf("expected agreement %.3f\n", $expected);
        printf("Cohen's kappa      %.3f  (%s the 0.7 the brief requires)\n\n", $kappa, $kappa >= 0.7 ? 'meets' : 'BELOW');
    } else {
        echo "\nsingle rater — precision below is one rater's; no kappa is computed,\n";
        echo "and none should be reported from one rater's worksheet.\n\n";
    }

    // Per-engine precision, which is what the M3 gate actually compares
    // ("precision not below the better of RK/TokenBag on the audited set").
    // The attribution comes from the key file the raters never saw. One key
    // serves both worksheets: they carry the same finding ids, because they are
    // the same pool rated twice.
    $keyPath  = (string) preg_replace('/\.tsv$/', '', $a) . '.key.tsv';
    $engineOf = is_file($keyPath) ? bcb_read_key($keyPath) : null;

    // Every rater is reported the same way, including per engine. Reporting the
    // engine breakdown for the first worksheet alone would leave the gate's own
    // comparison resting on one rater while κ is quoted from two — the second
    // rater exists precisely so that comparison does not rest on one reading.
    $raters = ['A' => $first];

    if ($second !== null) {
        $raters['B'] = $second;
    }

    foreach ($raters as $label => $verdicts) {
        bcb_report_rater($label, $verdicts, $engineOf);
    }

    if ($engineOf === null) {
        printf("no key file beside %s, so no per-engine breakdown\n", $a);

        return 0;
    }

    echo "A finding several engines reported counts once for each of them, so the\n";
    echo "rows are not disjoint and do not sum to the total above.\n";

    // Every engine in the key, not only `unified`. The bar is about an
    // engine's *asserted* stratum, so an engine whose characteristic false
    // positive the strata already demote reads differently from its raw
    // precision — which is the whole point of stratifying, and was invisible
    // while this reported one engine.
    $rated = [];

    foreach ($engineOf as $engines) {
        foreach ($engines as $name) {
            $rated[$name] = true;
        }
    }

    ksort($rated);

    foreach (array_keys($rated) as $name) {
        bcb_report_strata($raters, bcb_read_strata($keyPath), $engineOf, $name);
    }

    return 0;
}

/**
 * Finding id → the engines that reported it, from the key the raters never saw.
 *
 * @return array<string, list<string>>
 */
function bcb_read_key(string $keyPath): array
{
    /** @var array<string, list<string>> $engineOf */
    $engineOf = [];

    foreach (file($keyPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$id, $engines] = array_pad(explode("\t", $line, 3), 3, '');
        $engineOf[trim($id)] = array_values(array_filter(explode(' ', trim($engines))));
    }

    return $engineOf;
}

/**
 * Finding id → its stratum, from the same key.
 *
 * A key written before M5 has no third column; every finding in it reads as
 * `asserted`, which is what a pool drawn before the strata existed was. Stated
 * rather than left to be discovered: a missing column is not a demoted finding.
 *
 * @return array<string, string> '' for asserted, otherwise the demote tags
 */
function bcb_read_strata(string $keyPath): array
{
    /** @var array<string, string> $stratumOf */
    $stratumOf = [];

    foreach (file($keyPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$id, , $stratum] = array_pad(explode("\t", $line, 3), 3, '');
        $stratum          = trim($stratum);
        $stratumOf[trim($id)] = ($stratum === '' || $stratum === 'asserted') ? '' : $stratum;
    }

    return $stratumOf;
}

/**
 * The stratified bar, reported exactly as the M5 charter pre-registers it.
 *
 * Both strata, both raters, point estimates and Wilson intervals, always
 * together. The demoted stratum's number is published beside the asserted one
 * rather than behind it — the auditor's condition on the whole restatement is
 * that stratification is a lens and never a hiding place, and a report that
 * printed one of the two would be the hiding place.
 *
 * The criterion itself is quoted, not restated: **both raters' asserted-stratum
 * point estimates >= 0.80**. This function applies it; it does not interpret it.
 *
 * @param array<string, array<string, string>> $raters   label => finding id => verdict
 * @param array<string, string>                $stratumOf
 * @param array<string, list<string>>|null     $engineOf
 */
function bcb_report_strata(array $raters, array $stratumOf, ?array $engineOf, string $engine): void
{
    printf("\nthe stratified bar (M5) — engine: %s\n", $engine);
    printf("  %-8s %-9s %8s %8s   %s\n", 'rater', 'stratum', 'ratio', 'point', 'Wilson 95%');

    $asserted = [];

    foreach ($raters as $label => $verdicts) {
        foreach (['asserted', 'demoted'] as $stratum) {
            $yes = 0;
            $n   = 0;

            foreach ($verdicts as $id => $verdict) {
                if ($verdict !== 'Y' && $verdict !== 'N') {
                    continue;
                }

                if ($engineOf !== null && !in_array($engine, $engineOf[$id] ?? [], true)) {
                    continue;
                }

                $isDemoted = ($stratumOf[$id] ?? '') !== '';

                if ($isDemoted !== ($stratum === 'demoted')) {
                    continue;
                }

                $n++;
                $yes += $verdict === 'Y' ? 1 : 0;
            }

            $point        = $n > 0 ? $yes / $n : 0.0;
            [$low, $high] = bcb_wilson($yes, $n);

            if ($stratum === 'asserted') {
                $asserted[$label] = ['n' => $n, 'point' => $point];
            }

            printf(
                "  %-8s %-9s %4d/%-3d %8.3f   [%.3f, %.3f]%s\n",
                $label,
                $stratum,
                $yes,
                $n,
                $point,
                $low,
                $high,
                $n === 0 ? '   (empty stratum)' : '',
            );
        }
    }

    // The pre-registered criterion, applied rather than reinterpreted.
    $met = $asserted !== [];

    foreach ($asserted as $counts) {
        $met = $met && $counts['n'] > 0 && $counts['point'] >= 0.80;
    }

    printf(
        "\n  pre-registered flip criterion — both raters' asserted point estimates >= 0.80: %s\n",
        $met ? 'MET' : 'NOT MET',
    );

    if (count($raters) < 2) {
        printf("  (one rater only: the criterion needs two, and no flip is decided from one)\n");
    }

    // Per-demote-tag detail, so the demoted stratum is not one opaque bucket.
    $tags = [];

    foreach ($stratumOf as $id => $stratum) {
        foreach ($stratum === '' ? [] : explode(' ', $stratum) as $tag) {
            $tags[$tag][] = $id;
        }
    }

    if ($tags === []) {
        return;
    }

    ksort($tags);
    printf("\n  the demoted stratum, by tag (rater A):\n");

    $first = array_key_first($raters);

    if ($first === null) {
        return;
    }

    foreach ($tags as $tag => $ids) {
        $yes = 0;
        $n   = 0;

        foreach ($ids as $id) {
            $verdict = $raters[$first][$id] ?? '';

            if ($verdict !== 'Y' && $verdict !== 'N') {
                continue;
            }

            if ($engineOf !== null && !in_array($engine, $engineOf[$id] ?? [], true)) {
                continue;
            }

            $n++;
            $yes += $verdict === 'Y' ? 1 : 0;
        }

        printf("    %-14s %d/%d\n", $tag, $yes, $n);
    }
}

/**
 * One rater's precision: overall, then per engine when the key is available.
 *
 * @param array<string, string> $verdicts
 * @param array<string, list<string>>|null $engineOf
 */
function bcb_report_rater(string $label, array $verdicts, ?array $engineOf): void
{
    $rated = array_filter($verdicts, static fn(string $verdict): bool => $verdict === 'Y' || $verdict === 'N');
    $yes   = count(array_filter($rated, static fn(string $verdict): bool => $verdict === 'Y'));
    $n     = count($rated);

    [$low, $high] = bcb_wilson($yes, $n);

    printf("rater %s precision over the sampled pool: %d/%d = %.3f\n", $label, $yes, $n, $n > 0 ? $yes / $n : 0.0);
    printf("Wilson 95%% interval:                     [%.3f, %.3f]\n", $low, $high);
    printf("unrateable ('?'):                        %d\n", count(array_filter($verdicts, static fn(string $v): bool => $v === '?')));

    if ($engineOf === null) {
        echo "\n";

        return;
    }

    /** @var array<string, array{yes: int, n: int}> $perEngine */
    $perEngine = [];

    foreach ($rated as $id => $verdict) {
        foreach ($engineOf[$id] ?? [] as $engine) {
            $perEngine[$engine] ??= ['yes' => 0, 'n' => 0];
            $perEngine[$engine] = [
                'yes' => $perEngine[$engine]['yes'] + ($verdict === 'Y' ? 1 : 0),
                'n'   => $perEngine[$engine]['n'] + 1,
            ];
        }
    }

    ksort($perEngine);

    printf("\nprecision per engine over the rated sample (rater %s):\n", $label);

    foreach ($perEngine as $engine => $counts) {
        [$engineLow, $engineHigh] = bcb_wilson($counts['yes'], $counts['n']);

        printf(
            "  %-12s %d/%-3d = %.3f   Wilson [%.3f, %.3f]\n",
            $engine,
            $counts['yes'],
            $counts['n'],
            $counts['n'] > 0 ? $counts['yes'] / $counts['n'] : 0.0,
            $engineLow,
            $engineHigh,
        );
    }

    echo "\n";
}
