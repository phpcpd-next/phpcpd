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
 * BCB-PHP harness self-test — the gate every published number stands behind.
 *
 * This project once shipped a benchmark table in which every cell was a lie: the
 * runner drove its subject through the `timeout` binary, which macOS does not
 * have, so every invocation exited 127 without running anything and every one of
 * those was recorded as "timeout". Nothing in the output looked wrong.
 *
 * So the harness is not trusted, it is tested — and tested by making it do the
 * bad things on purpose:
 *
 *   A. a run that really does exceed its deadline is recorded as a timeout, is
 *      killed at the deadline rather than allowed to finish, and the process is
 *      actually dead afterwards (not an orphan behind a dead shell);
 *   B. a run that fails is recorded as a failure, with its exit status — and a
 *      command that does not exist, the original burn, is a failure too and is
 *      never, under any circumstances, called a timeout;
 *   C. durations are wall-clock deltas measured in this process, monotonic and
 *      never negative, and the number the harness reports for a subprocess is
 *      the one an independent in-process measurement of the same call sees;
 *   D. a zero exit with unparseable output is a failure, not a clean row.
 *
 * Usage:  php bench/self-test.php
 * Exit 0 only if every check passes. Run it before recording any measurement.
 */

require_once __DIR__ . '/harness.php';

/** @var list<array{ok: bool, claim: string, detail: string}> $log */
$log = [];

$php     = PHP_BINARY;
$tmpDir  = sys_get_temp_dir() . '/bcb-self-test-' . getmypid();
@mkdir($tmpDir, 0o755, true);
$heartbeat = $tmpDir . '/heartbeat';

echo "BCB-PHP harness self-test\n\n";

// ---------------------------------------------------------------------------
// A. A deliberate timeout is recorded as a timeout — and the child really dies.
// ---------------------------------------------------------------------------

echo "A. deliberate timeout\n";

// A child that outlives its deadline by 20x and leaves evidence of every moment
// it is still alive, so we can prove afterwards that it stopped when we said so.
$heartbeatScript = sprintf(
    'for ($i = 0; $i < 200; $i++) { file_put_contents(%s, "x", FILE_APPEND); usleep(50000); }',
    var_export($heartbeat, true),
);

$slow = bcb_run([$php, '-r', $heartbeatScript], 0.6);

bcb_check(
    $log,
    $slow['outcome'] === BCB_TIMEOUT,
    'a run past its deadline is recorded as a timeout',
    'outcome=' . $slow['outcome'],
);

bcb_check(
    $log,
    $slow['started'] === true,
    'the timed-out run is recorded as having actually started',
    'started=' . var_export($slow['started'], true),
);

bcb_check(
    $log,
    $slow['seconds'] >= 0.6 && $slow['seconds'] < 5.0,
    'the deadline is enforced, not merely observed after the fact',
    sprintf('deadline 0.600s, child would run 10s, returned after %.3fs', $slow['seconds']),
);

// The reason bcb_run() execs an argv list instead of a shell string: killing a
// shell wrapper leaves the real subject running, and a benchmark that reports a
// timeout while its subject is still burning CPU is worse than no benchmark.
$sizeAtKill = (int) @filesize($heartbeat);
clearstatcache(true, $heartbeat);
usleep(400_000);
clearstatcache(true, $heartbeat);
$sizeLater = (int) @filesize($heartbeat);

bcb_check(
    $log,
    $sizeAtKill === $sizeLater,
    'the timed-out process is actually dead, not orphaned behind a killed shell',
    sprintf('heartbeat %d bytes at kill, %d bytes 0.4s later', $sizeAtKill, $sizeLater),
);

// The mirror image: a harness that called everything a timeout would also pass
// the checks above. The same command under a deadline it fits inside must be OK.
$notSlow = bcb_run([$php, '-r', 'usleep(100000);'], 10.0);

bcb_check(
    $log,
    $notSlow['outcome'] === BCB_OK,
    'a run that fits inside its deadline is NOT recorded as a timeout',
    'outcome=' . $notSlow['outcome'],
);

// ---------------------------------------------------------------------------
// B. A deliberate failure is recorded as a failure — including exit 127.
// ---------------------------------------------------------------------------

echo "\nB. deliberate failure\n";

$failed = bcb_run([$php, '-r', 'fwrite(STDERR, "deliberate failure"); exit(3);'], 10.0);

bcb_check(
    $log,
    $failed['outcome'] === BCB_FAILED,
    'a run that exits non-zero is recorded as a failure',
    'outcome=' . $failed['outcome'],
);

bcb_check(
    $log,
    $failed['exitCode'] === 3,
    'the failing run reports its actual exit status',
    'exitCode=' . var_export($failed['exitCode'], true),
);

bcb_check(
    $log,
    str_contains($failed['stderr'], 'deliberate failure'),
    'the failing run captures what the subject wrote to stderr',
    'stderr=' . var_export(trim($failed['stderr']), true),
);

bcb_check(
    $log,
    $failed['outcome'] !== BCB_TIMEOUT,
    'a failure is never reclassified as a timeout',
);

// The original burn, reproduced exactly: name a binary that is not there.
$missing = bcb_run(['bcb-no-such-binary-' . getmypid(), '--version'], 10.0);

bcb_check(
    $log,
    $missing['outcome'] === BCB_FAILED,
    'a command that does not exist is recorded as a failure',
    'outcome=' . $missing['outcome'],
);

bcb_check(
    $log,
    $missing['outcome'] !== BCB_TIMEOUT,
    'a missing binary is NEVER recorded as a timeout (the 2024 benchmark burn)',
    'error=' . var_export($missing['error'], true),
);

bcb_check(
    $log,
    $missing['started'] === false || $missing['exitCode'] === 127,
    'the missing binary is reported honestly as never-started or exit 127',
    sprintf('started=%s exitCode=%s', var_export($missing['started'], true), var_export($missing['exitCode'], true)),
);

bcb_check(
    $log,
    $missing['seconds'] < 5.0,
    'a missing binary fails immediately rather than consuming its deadline',
    sprintf('%.3fs', $missing['seconds']),
);

// ---------------------------------------------------------------------------
// C. Durations are in-process, monotonic wall-clock deltas.
// ---------------------------------------------------------------------------

echo "\nC. in-process wall clock\n";

$slept = bcb_time(static function (): int {
    usleep(250_000);

    return 1;
});

bcb_check(
    $log,
    $slept['outcome'] === BCB_OK && $slept['seconds'] >= 0.25 && $slept['seconds'] < 5.0,
    'an in-process 0.25s of work measures at least 0.25s',
    sprintf('%.4fs', $slept['seconds']),
);

$noop = bcb_time(static fn(): int => 1);

bcb_check(
    $log,
    $noop['seconds'] >= 0.0,
    'the clock never runs backwards',
    sprintf('%.6fs', $noop['seconds']),
);

bcb_check(
    $log,
    $noop['seconds'] < $slept['seconds'],
    'the clock discriminates: a no-op measures shorter than 0.25s of sleep',
    sprintf('noop %.6fs < slept %.4fs', $noop['seconds'], $slept['seconds']),
);

// The load-bearing claim: the duration the harness reports for a subprocess is
// a delta measured here, in this process — so an independent measurement taken
// around the same call has to agree with it. A number scraped from the child's
// own output, or read off a wall clock, is free to disagree.
$before = bcb_now();
$inner  = bcb_run([$php, '-r', 'usleep(400000);'], 10.0);
$outer  = bcb_now() - $before;
$delta  = abs($outer - $inner['seconds']);

bcb_check(
    $log,
    $inner['outcome'] === BCB_OK && $inner['seconds'] >= 0.4 && $delta < 0.2,
    'a subprocess duration is the in-process delta an outer measurement also sees',
    sprintf('inner %.4fs, outer %.4fs, difference %.4fs', $inner['seconds'], $outer, $delta),
);

$threw = bcb_time(static function (): never {
    throw new RuntimeException('deliberate in-process failure');
});

bcb_check(
    $log,
    $threw['outcome'] === BCB_FAILED && str_contains((string) $threw['error'], 'deliberate in-process failure'),
    'a throwing measurement is recorded as a failure with its message',
    'error=' . var_export($threw['error'], true),
);

bcb_check(
    $log,
    $threw['seconds'] >= 0.0,
    'a failed measurement still reports how long it ran before failing',
    sprintf('%.6fs', $threw['seconds']),
);

$repeated = bcb_repeat(5, static function (): int {
    usleep(100_000);

    return 1;
});

bcb_check(
    $log,
    $repeated['n'] === 5 && count($repeated['runs']) === 5,
    'repeated measurement reports every run, never a single number',
    sprintf('runs=%d', count($repeated['runs'])),
);

bcb_check(
    $log,
    $repeated['median'] >= 0.1 && $repeated['spread'] >= 0.0 && $repeated['errors'] === [],
    'repeated measurement reports a median and a spread',
    sprintf('median %.4fs, spread %.4fs', $repeated['median'], $repeated['spread']),
);

// ---------------------------------------------------------------------------
// D. A clean exit with nothing to parse is a failure, not a clean row.
// ---------------------------------------------------------------------------

echo "\nD. output must be parseable\n";

$parser = static function (string $stdout, string $stderr): ?int {
    return preg_match('/clones=(\d+)/', $stdout, $m) === 1 ? (int) $m[1] : null;
};

$silent = bcb_run_parsed([$php, '-r', 'echo "nothing useful here\n";'], 10.0, $parser);

bcb_check(
    $log,
    $silent['outcome'] === BCB_FAILED && str_contains((string) $silent['error'], 'no parseable output'),
    'a zero-exit run whose output does not parse is recorded as a failure',
    'outcome=' . $silent['outcome'],
);

bcb_check(
    $log,
    $silent['exitCode'] === 0,
    'the unparseable run still reports its clean exit status, so the cause is visible',
    'exitCode=' . var_export($silent['exitCode'], true),
);

$spoke = bcb_run_parsed([$php, '-r', 'echo "clones=42\n";'], 10.0, $parser);

bcb_check(
    $log,
    $spoke['outcome'] === BCB_OK && $spoke['parsed'] === 42,
    'a run that produces the expected answer passes and yields the parsed value',
    'parsed=' . var_export($spoke['parsed'], true),
);

// ---------------------------------------------------------------------------

@unlink($heartbeat);
@rmdir($tmpDir);

exit(bcb_check_summary($log, 'harness self-test'));
