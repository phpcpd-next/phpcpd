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
 * The reporter equivalence gate — golden output for every format.
 *
 * Ruling S replaces each inherited file under one standard: written without
 * opening the inherited implementation, and **proven behaviour-identical by
 * the gates**. For the reporters there was no such gate. `tests/` covered the
 * detector, the facts layer and the presentation tier; nothing asserted a
 * single byte any format actually writes, so "behaviour-identical" had nothing
 * to be identical to.
 *
 * This script is that missing gate, and the protocol requires it to exist
 * **before** the rewrite: the goldens are captured from the inherited
 * implementations, committed, and then the rewritten reporters are required to
 * reproduce them byte for byte. A golden captured after a rewrite proves that
 * the rewrite equals itself.
 *
 * ## What is compared, and why it is the reporter and not the CLI
 *
 * The unit is the reporter, not `phpcpd` the command. A console run also prints
 * a banner, a scan root, an orphan section and a wall-clock line, none of which
 * belong to `Log\Text`; folding them in would make a golden that breaks when
 * `CLI\Application` changes and — worse — would let a reporter regression hide
 * inside a diff nobody attributes to it. So the script builds the real
 * `Findings` the CLI builds, hands it to each reporter, and captures exactly
 * what that reporter emits:
 *
 *   Text    stdout of `printResult()`, both plain and `--verbose`
 *   PMD     the file `PMD::process()` writes
 *   JSON    the file `Json::process()` writes
 *   SARIF   the file `Sarif::process()` writes
 *
 * JSON and SARIF are already original work and are not being rewritten. They
 * are in the gate anyway, because `AbstractXmlLogger` is not the only
 * scaffolding a reporter shares and a rewrite that quietly moved a shared
 * behaviour would show up in them first.
 *
 * ## Determinism, and the two things deliberately excluded
 *
 * Paths are made repository-relative by running the detection from the
 * repository root over relative fixture paths, so a golden does not carry the
 * checkout's location. Nothing else is normalised: a golden that has been
 * cleaned up is a golden that stopped proving byte-identity, which is the whole
 * claim. Timings and memory are not in any reporter's output and so need no
 * exclusion — `CLI\Application` prints those, which is a second reason the unit
 * is the reporter.
 *
 * ## Usage
 *
 *   php bench/check-log-equivalence.php            compare against the goldens
 *   php bench/check-log-equivalence.php --capture  (re-)write the goldens
 *   php bench/check-log-equivalence.php self-test  the instrument against itself
 *
 * `--capture` is deliberately a separate word rather than a fallback when a
 * golden is missing: a gate that writes the answer it failed to find is not a
 * gate. Interpretation checklist rule 3 — the destructive path takes an
 * explicit flag.
 */

require __DIR__ . '/lib.php';
require __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\Log\Json;
use LucianoPereira\PhpcpdNext\Log\PMD;
use LucianoPereira\PhpcpdNext\Log\Sarif;
use LucianoPereira\PhpcpdNext\Log\Text;
use LucianoPereira\PhpcpdNext\Presentation\Findings;
use LucianoPereira\PhpcpdNext\Presentation\Presenter;

const LOGEQ_GOLDEN_DIR = __DIR__ . '/../tests/fixtures/golden-logs';

/**
 * The fixture cases, with the knobs that make each one report something worth
 * comparing. `minTokens`/`minLines` are the bench defaults where the fixture
 * was written for the product defaults, and lowered where the fixture is small
 * — a case that reports nothing compares two empty strings and proves nothing,
 * which is the failure mode `check-determinism.php` already learned to guard.
 *
 * @return array<string, array{dir: string, opts: array{minTokens?:int, minLines?:int, fuzzy?:bool, typeAnchored?:bool, editDistance?:int, headEquality?:int, similarity?:float, algorithm?:string}}>
 */
function logeq_cases(): array
{
    return [
        // Two exact copies of a whole function: the plainest possible report.
        'exact' => ['dir' => 'tests/fixtures/with_clones', 'opts' => ['minTokens' => 30, 'minLines' => 5]],
        // Type-3: gapped clones, so the divergence ranges reach every format.
        // 38 rather than 30 because the unified engine refuses anything below
        // its derived floor (2K + 6) instead of quietly degrading — the design
        // is not negotiable to a fixture's convenience, so the case moves.
        'gapped' => ['dir' => 'tests/fixtures/type3', 'opts' => ['minTokens' => 38, 'minLines' => 5, 'algorithm' => 'unified']],
        // Renamed identifiers: the normalized view, and multi-site clones.
        'renamed' => ['dir' => 'tests/fixtures/type2', 'opts' => ['minTokens' => 38, 'minLines' => 5, 'algorithm' => 'unified']],
        // Demote strata and confidence scores, which only the text report shows.
        'strata' => ['dir' => 'tests/fixtures/strata', 'opts' => ['minTokens' => 30, 'minLines' => 5]],
        // A literal table: XML escaping meets data, and the `table` tag.
        'table' => ['dir' => 'tests/fixtures/datatable', 'opts' => ['minTokens' => 30, 'minLines' => 5]],
        // The empty report. Every format has one and every format gets it wrong
        // differently, so it is a case and not an afterthought.
        'empty' => ['dir' => 'tests/fixtures/no_clones', 'opts' => ['minTokens' => 70, 'minLines' => 5]],
    ];
}

/**
 * Build the findings for one case, exactly as the CLI builds them.
 *
 * @param array{minTokens?:int, minLines?:int, fuzzy?:bool, typeAnchored?:bool, editDistance?:int, headEquality?:int, similarity?:float, algorithm?:string} $opts
 */
function logeq_findings(string $dir, array $opts): Findings
{
    $files = bcb_gate_files([$dir]);
    sort($files);

    $map = bcb_detect($files, $opts);

    return (new Presenter())->present($map);
}

/**
 * Every format's output for one case, keyed by golden file name.
 *
 * @return array<string, string>
 */
function logeq_outputs(string $case, Findings $findings): array
{
    $out = [];

    foreach ([false, true] as $verbose) {
        ob_start();
        (new Text())->printResult($findings, $verbose);
        $out[$case . ($verbose ? '.text-verbose.txt' : '.text.txt')] = (string) ob_get_clean();
    }

    $tmp = sys_get_temp_dir() . '/phpcpd-logeq-' . $case;

    (new PMD($tmp . '.xml'))->process($findings);
    $out[$case . '.pmd.xml'] = (string) file_get_contents($tmp . '.xml');
    @unlink($tmp . '.xml');

    (new Json($tmp . '.json'))->process($findings);
    $out[$case . '.json'] = (string) file_get_contents($tmp . '.json');
    @unlink($tmp . '.json');

    (new Sarif($tmp . '.sarif.json'))->process($findings);
    $out[$case . '.sarif.json'] = (string) file_get_contents($tmp . '.sarif.json');
    @unlink($tmp . '.sarif.json');

    return $out;
}

/**
 * The first line on which two strings differ, as `line N: got ... want ...`.
 * A byte count alone tells a reader a golden broke; this tells them where.
 */
function logeq_first_diff(string $got, string $want): string
{
    $g = explode("\n", $got);
    $w = explode("\n", $want);
    $n = max(count($g), count($w));

    for ($i = 0; $i < $n; $i++) {
        if (($g[$i] ?? null) !== ($w[$i] ?? null)) {
            return sprintf(
                'first difference at line %d: got %s, want %s',
                $i + 1,
                var_export($g[$i] ?? null, true),
                var_export($w[$i] ?? null, true),
            );
        }
    }

    // Unreachable while the split is on "\n": two strings with identical
    // line lists are the same string. Kept as a total return rather than a
    // fall-through, so a future change to the split cannot make this silently
    // report "no difference" for two strings that differ.
    return sprintf('lines identical, %d vs %d bytes', strlen($got), strlen($want));
}

// ---------------------------------------------------------------------------

$argvLocal = $argv ?? [];
$mode = $argvLocal[1] ?? '';

chdir(dirname(__DIR__));

if ($mode === 'self-test') {
    /** @var list<array{ok: bool, claim: string, detail: string}> $log */
    $log = [];

    // The instrument must be able to fail, and it must be exercised on the
    // thing it actually compares rather than on two hand-written literals — a
    // literal comparison is decided at parse time and proves nothing about the
    // gate. So: render a real report twice, then mutate one copy.
    $spec     = logeq_cases()['strata'];
    $findings = logeq_findings($spec['dir'], $spec['opts']);

    ob_start();
    (new Text())->printResult($findings, false);
    $first = (string) ob_get_clean();

    ob_start();
    (new Text())->printResult($findings, false);
    $second = (string) ob_get_clean();

    bcb_check($log, $first === $second, 'a reporter renders one findings set identically twice', strlen($first) . ' bytes');
    bcb_check($log, $first !== '', 'and it rendered something, so the comparison above is not two empty strings', strlen($first) . ' bytes');

    // Mutate the real output the way a regression would: one line, in place.
    $lines = explode("\n", $first);
    $target = 0;

    foreach ($lines as $i => $line) {
        if (trim($line) !== '') {
            $target = $i;

            break;
        }
    }

    $lines[$target] = $lines[$target] . ' MUTATED';
    $mutated = implode("\n", $lines);

    bcb_check($log, $mutated !== $first, 'a one-line change to a real report is not byte-identical to it', 'line ' . ($target + 1));
    bcb_check(
        $log,
        str_contains(logeq_first_diff($mutated, $first), 'line ' . ($target + 1)),
        'and the gate names the line it changed, rather than only counting bytes',
        logeq_first_diff($mutated, $first),
    );
    bcb_check(
        $log,
        str_contains(logeq_first_diff($first . 'x', $first), 'line ' . (count($lines))),
        'an appended byte is caught, not swallowed by the last line',
        logeq_first_diff($first . 'x', $first),
    );

    // And every case must actually report something, or the gate is comparing
    // empty strings. `empty` is exempt by construction and named as such.
    foreach (logeq_cases() as $case => $spec) {
        $findings = logeq_findings($spec['dir'], $spec['opts']);

        if ($case === 'empty') {
            bcb_check($log, $findings->count() === 0, 'case `empty` reports nothing, as its name claims', (string) $findings->count());

            continue;
        }

        bcb_check($log, $findings->count() > 0, sprintf('case `%s` reports findings, so its goldens mean something', $case), $findings->count() . ' findings');
    }

    exit(bcb_check_summary($log, 'reporter equivalence self-test'));
}

/** @var list<array{ok: bool, claim: string, detail: string}> $log */
$log = [];
$written = 0;

foreach (logeq_cases() as $case => $spec) {
    $findings = logeq_findings($spec['dir'], $spec['opts']);

    foreach (logeq_outputs($case, $findings) as $name => $got) {
        $path = LOGEQ_GOLDEN_DIR . '/' . $name;

        if ($mode === '--capture') {
            if (!is_dir(LOGEQ_GOLDEN_DIR)) {
                mkdir(LOGEQ_GOLDEN_DIR, 0o777, true);
            }

            file_put_contents($path, $got);
            $written++;

            continue;
        }

        if (!is_file($path)) {
            bcb_check($log, false, sprintf('%s: a golden to compare against exists', $name), 'none — capture it deliberately with --capture; a gate that writes the answer it failed to find is not a gate');

            continue;
        }

        $want = (string) file_get_contents($path);

        bcb_check(
            $log,
            $got === $want,
            sprintf('%s: byte-identical to the golden', $name),
            $got === $want ? strlen($got) . ' bytes' : logeq_first_diff($got, $want),
        );
    }
}

if ($mode === '--capture') {
    printf("captured %d golden files into %s\n", $written, 'tests/fixtures/golden-logs');

    exit(0);
}

exit(bcb_check_summary($log, 'reporter equivalence'));
