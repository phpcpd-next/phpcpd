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
 * BCB-PHP measurement harness.
 *
 * Every number this project publishes has to come through here, because the
 * project has already been burned by a benchmark that never ran: it drove the
 * subject through the `timeout` binary, which does not exist on macOS, so every
 * invocation died instantly with exit 127 and every one of those deaths was
 * recorded as "timeout". The table looked plausible. Nothing had executed.
 *
 * The two rules that follow from that, and that this file exists to enforce:
 *
 *   1. A timeout is a fact the harness *causes*, never one it infers. The only
 *      path to BCB_TIMEOUT is this harness deciding the deadline passed and
 *      killing the child itself. No exit status, no stderr text, and no missing
 *      binary can ever be classified as a timeout — an exit 127 is a failure and
 *      says so, loudly, with the command it could not start.
 *   2. Durations are wall-clock deltas taken in-process from hrtime(), the
 *      monotonic clock, never parsed out of a child's output and never taken
 *      from a wall clock that an NTP step can run backwards.
 *
 * And because "it ran" is not the same as "it produced an answer",
 * bcb_run_parsed() demotes a zero-exit run whose output does not parse to a
 * failure rather than letting a silent format change read as a clean result.
 *
 * bench/self-test.php proves all of this by deliberately timing out,
 * deliberately failing, and deliberately naming a command that does not exist.
 * It must pass before any number is recorded anywhere.
 */

/** The run completed and, where a parser was supplied, produced an answer. */
const BCB_OK = 'ok';

/** The run started and finished badly, or never started at all. */
const BCB_FAILED = 'failed';

/** The harness killed the run because it passed its deadline. Nothing else. */
const BCB_TIMEOUT = 'timeout';

/** How often the subprocess loop wakes to check the deadline, in microseconds. */
const BCB_POLL_INTERVAL_US = 20_000;

// ---------------------------------------------------------------------------
// In-process wall clock
// ---------------------------------------------------------------------------

/**
 * Seconds elapsed on the monotonic clock since an arbitrary fixed origin.
 *
 * hrtime() rather than microtime(): microtime() reads the system wall clock,
 * which an NTP correction can step backwards mid-measurement and hand back a
 * negative duration. The origin is meaningless; only differences are used.
 */
function bcb_now(): float
{
    return hrtime(true) / 1e9;
}

/**
 * Run a callable and report how long it took, measured here, in this process.
 *
 * A throwing callable is a recorded failure, not a crash: the benchmark keeps
 * going and the row says what threw. The duration is still reported, because
 * how long a run took before it failed is evidence too.
 *
 * @template T
 * @param callable():T $work
 * @return array{outcome: string, seconds: float, error: ?string, value: mixed}
 */
function bcb_time(callable $work): array
{
    $start = bcb_now();

    try {
        $value = $work();
    } catch (Throwable $e) {
        return [
            'outcome' => BCB_FAILED,
            'seconds' => bcb_now() - $start,
            'error'   => $e::class . ': ' . $e->getMessage(),
            'value'   => null,
        ];
    }

    return [
        'outcome' => BCB_OK,
        'seconds' => bcb_now() - $start,
        'error'   => null,
        'value'   => $value,
    ];
}

/**
 * Repeat a measurement and report median and spread — never a single run.
 *
 * The median is the lower of the two middle values on an even count, chosen by
 * rule so the statistic is reproducible rather than averaged into a number that
 * was never observed. Runs are reported in the order they happened.
 *
 * `$onRun` is called after each run with how many are done, how many there
 * are, and how long that one took. It is how a caller that has a terminal can
 * say what is happening during a measurement that takes minutes; nothing here
 * knows what it does with that.
 *
 * @param positive-int $times
 * @param callable():mixed $work
 * @param ?callable(int, int, float): void $onRun
 * @return array{n: int, runs: list<float>, median: float, min: float, max: float, spread: float, outcomes: list<string>, errors: list<string>}
 */
function bcb_repeat(int $times, callable $work, ?callable $onRun = null): array
{
    $runs     = [];
    $outcomes = [];
    $errors   = [];

    for ($i = 0; $i < $times; $i++) {
        $r          = bcb_time($work);
        $runs[]     = $r['seconds'];
        $outcomes[] = $r['outcome'];

        if ($r['error'] !== null) {
            $errors[] = $r['error'];
        }

        if ($onRun !== null) {
            $onRun($i + 1, $times, $r['seconds']);
        }
    }

    // At least one run happened — $times is a positive int — so there is always
    // a median to report and no empty case to defend against.
    $sorted = $runs;
    sort($sorted);
    $last = count($sorted) - 1;

    return [
        'n'        => $times,
        'runs'     => $runs,
        'median'   => $sorted[intdiv($last, 2)],
        'min'      => $sorted[0],
        'max'      => $sorted[$last],
        'spread'   => $sorted[$last] - $sorted[0],
        'outcomes' => $outcomes,
        'errors'   => $errors,
    ];
}

/**
 * This script's arguments, as a list of strings.
 *
 * Read from $_SERVER rather than the $argv global because a static analyser
 * cannot see that register_argc_argv is on, and filtered to strings so every
 * bench script gets one narrowed shape instead of asserting its own.
 *
 * @return list<string>
 */
function bcb_argv(): array
{
    $raw = $_SERVER['argv'] ?? [];
    $out = [];

    foreach (is_array($raw) ? $raw : [] as $argument) {
        if (is_string($argument)) {
            $out[] = $argument;
        }
    }

    return $out;
}

// ---------------------------------------------------------------------------
// Subprocess execution with a deadline the harness owns
// ---------------------------------------------------------------------------

/**
 * Run a command with a deadline, and report what actually happened.
 *
 * The command is an argv list, never a shell string: PHP execs it directly, so
 * there is no intervening shell to absorb the kill signal and leave the real
 * subject running past its deadline. That also means no quoting rules and no
 * shell injection surface.
 *
 * `started` distinguishes "the OS refused to run this" from "this ran and
 * exited badly" — the two the original burn conflated. Neither is a timeout.
 *
 * @param list<string> $command argv; element 0 is the program
 * @param float        $timeout seconds; the deadline this harness enforces itself
 * @return array{outcome: string, started: bool, exitCode: ?int, signal: ?int, seconds: float, stdout: string, stderr: string, command: string, error: ?string}
 */
function bcb_run(array $command, float $timeout, ?string $cwd = null): array
{
    $printable = implode(' ', $command);
    $start     = bcb_now();

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $pipes   = [];
    $process = @proc_open($command, $descriptors, $pipes, $cwd);

    if (!is_resource($process)) {
        return [
            'outcome'  => BCB_FAILED,
            'started'  => false,
            'exitCode' => null,
            'signal'   => null,
            'seconds'  => bcb_now() - $start,
            'stdout'   => '',
            'stderr'   => '',
            'command'  => $printable,
            'error'    => 'could not start command: ' . $printable,
        ];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout   = '';
    $stderr   = '';
    $timedOut = false;
    $exitCode = null;
    $signal   = null;

    while (true) {
        $status = proc_get_status($process);

        // Drain whatever is buffered before deciding anything: a process that
        // has already exited can still have output waiting in the pipe.
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);

        if (!$status['running']) {
            // proc_get_status reports the real exit status only on the first
            // call that observes termination; later calls report -1.
            $exitCode = $status['exitcode'];
            $signal   = $status['signaled'] ? $status['termsig'] : null;

            break;
        }

        if (bcb_now() - $start >= $timeout) {
            // The deadline passed and we are the ones ending this. This is the
            // ONLY place in the harness that can produce BCB_TIMEOUT.
            $timedOut = true;
            proc_terminate($process, 9);

            // Reap, so the child cannot outlive the measurement as a zombie.
            while (proc_get_status($process)['running']) {
                usleep(BCB_POLL_INTERVAL_US);
            }

            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            break;
        }

        usleep(BCB_POLL_INTERVAL_US);
    }

    $seconds = bcb_now() - $start;

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    if ($timedOut) {
        return [
            'outcome'  => BCB_TIMEOUT,
            'started'  => true,
            'exitCode' => null,
            'signal'   => 9,
            'seconds'  => $seconds,
            'stdout'   => $stdout,
            'stderr'   => $stderr,
            'command'  => $printable,
            'error'    => sprintf('killed after %.3fs deadline: %s', $timeout, $printable),
        ];
    }

    if ($exitCode !== 0) {
        // Exit 127 is the shape of the original burn — the binary was missing.
        // It is a failure, and it says which command and which status, so the
        // next person cannot mistake it for the subject being slow.
        $note = $exitCode === 127
            ? 'command not found (exit 127)'
            : 'exited with status ' . var_export($exitCode, true);

        return [
            'outcome'  => BCB_FAILED,
            'started'  => true,
            'exitCode' => $exitCode,
            'signal'   => $signal,
            'seconds'  => $seconds,
            'stdout'   => $stdout,
            'stderr'   => $stderr,
            'command'  => $printable,
            'error'    => $note . ': ' . $printable,
        ];
    }

    return [
        'outcome'  => BCB_OK,
        'started'  => true,
        'exitCode' => 0,
        'signal'   => null,
        'seconds'  => $seconds,
        'stdout'   => $stdout,
        'stderr'   => $stderr,
        'command'  => $printable,
        'error'    => null,
    ];
}

/**
 * Run a command and require that it produced an answer, not merely a zero exit.
 *
 * The parser returns null when the output does not carry the measurement being
 * asked for; that demotes the run to a failure. A tool whose output format
 * drifts should break the benchmark visibly rather than quietly contributing a
 * row of zeroes to a published table.
 *
 * @param list<string>                $command
 * @param callable(string,string):mixed $parser stdout, stderr => parsed value, or null when unparseable
 * @return array{outcome: string, started: bool, exitCode: ?int, signal: ?int, seconds: float, stdout: string, stderr: string, command: string, error: ?string, parsed: mixed}
 */
function bcb_run_parsed(array $command, float $timeout, callable $parser, ?string $cwd = null): array
{
    $result           = bcb_run($command, $timeout, $cwd);
    $result['parsed'] = null;

    if ($result['outcome'] !== BCB_OK) {
        return $result;
    }

    $parsed = $parser($result['stdout'], $result['stderr']);

    if ($parsed === null) {
        $result['outcome'] = BCB_FAILED;
        $result['error']   = 'ran cleanly but produced no parseable output: ' . $result['command'];

        return $result;
    }

    $result['parsed'] = $parsed;

    return $result;
}

// ---------------------------------------------------------------------------
// Self-verification reporting
// ---------------------------------------------------------------------------

/**
 * One checked claim. Shared by bench/self-test.php and bench/check-manifest.php
 * so both report in the same shape and both fail the same way: a red line names
 * what was expected, and the script's exit status carries it to CI.
 *
 * @param list<array{ok: bool, claim: string, detail: string}> $log
 */
function bcb_check(array &$log, bool $ok, string $claim, string $detail = ''): bool
{
    $log[] = ['ok' => $ok, 'claim' => $claim, 'detail' => $detail];

    printf("  %s  %s%s\n", $ok ? 'PASS' : 'FAIL', $claim, $detail === '' ? '' : ' — ' . $detail);

    return $ok;
}

/**
 * Print the tally and return the process exit status: 0 only when every check
 * passed. Silence is never success — a run with no checks at all fails.
 *
 * @param list<array{ok: bool, claim: string, detail: string}> $log
 */
function bcb_check_summary(array $log, string $title): int
{
    $failed = array_values(array_filter($log, static fn(array $c): bool => !$c['ok']));
    $total  = count($log);

    echo "\n";

    if ($total === 0) {
        printf("%s: NO CHECKS RAN — treating as failure.\n", $title);

        return 1;
    }

    if ($failed === []) {
        printf("%s: %d/%d checks passed.\n", $title, $total, $total);

        return 0;
    }

    printf("%s: %d of %d checks FAILED:\n", $title, count($failed), $total);

    foreach ($failed as $c) {
        printf("  - %s%s\n", $c['claim'], $c['detail'] === '' ? '' : ' — ' . $c['detail']);
    }

    return 1;
}
