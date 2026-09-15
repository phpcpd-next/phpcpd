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

namespace LucianoPereira\PhpcpdNext\Tests;

use function bcb_repeat;
use function bcb_run;
use function bcb_run_parsed;
use function bcb_time;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../bench/harness.php';

/**
 * The benchmark harness, and the two scripts that gate it.
 *
 * This project shipped a benchmark table in which every cell was fabricated by
 * an unchecked assumption: the runner drove its subject through `timeout`, a
 * binary macOS does not have, so every run exited 127 without executing and
 * every one of those was recorded as a timeout. The table looked fine.
 *
 * The lesson taken was not "be careful" but "make the harness prove itself",
 * which is what bench/self-test.php does and what this class keeps in CI: the
 * gate scripts are run here as subprocesses and their exit status is asserted,
 * so a harness that stops discriminating fails the suite rather than quietly
 * producing numbers again.
 */
final class BenchHarnessTest extends TestCase
{
    private const float GENEROUS_TIMEOUT = 20.0;

    // ── Classification: the distinctions the original burn collapsed ──────

    #[Test]
    public function a_run_past_its_deadline_is_a_timeout(): void
    {
        $result = bcb_run([PHP_BINARY, '-r', 'usleep(10000000);'], 0.5);

        self::assertSame(BCB_TIMEOUT, $result['outcome']);
        self::assertTrue($result['started']);
        self::assertGreaterThanOrEqual(0.5, $result['seconds']);
        self::assertLessThan(5.0, $result['seconds'], 'the deadline must be enforced, not just observed');
    }

    #[Test]
    public function a_run_inside_its_deadline_is_not_a_timeout(): void
    {
        // The mirror image, without which "always report timeout" would pass.
        self::assertSame(BCB_OK, bcb_run([PHP_BINARY, '-r', 'usleep(1000);'], self::GENEROUS_TIMEOUT)['outcome']);
    }

    #[Test]
    public function a_non_zero_exit_is_a_failure_with_its_status_and_stderr(): void
    {
        $result = bcb_run([PHP_BINARY, '-r', 'fwrite(STDERR, "boom"); exit(3);'], self::GENEROUS_TIMEOUT);

        self::assertSame(BCB_FAILED, $result['outcome']);
        self::assertSame(3, $result['exitCode']);
        self::assertStringContainsString('boom', $result['stderr']);
    }

    #[Test]
    public function a_missing_binary_is_a_failure_and_never_a_timeout(): void
    {
        // The exact shape of the 2024 burn.
        $result = bcb_run(['bcb-no-such-binary-' . getmypid(), '--version'], self::GENEROUS_TIMEOUT);

        self::assertSame(BCB_FAILED, $result['outcome']);
        self::assertNotSame(BCB_TIMEOUT, $result['outcome']);
        self::assertTrue($result['started'] === false || $result['exitCode'] === 127);
        self::assertLessThan(5.0, $result['seconds'], 'a missing binary must fail at once, not burn its deadline');
    }

    #[Test]
    public function stdout_is_captured_whole(): void
    {
        $result = bcb_run([PHP_BINARY, '-r', 'echo str_repeat("x", 100000);'], self::GENEROUS_TIMEOUT);

        self::assertSame(BCB_OK, $result['outcome']);
        self::assertSame(100_000, strlen($result['stdout']), 'a full pipe buffer must not truncate or deadlock');
    }

    // ── Parseable output ──────────────────────────────────────────────────

    #[Test]
    public function a_clean_exit_with_unparseable_output_is_a_failure(): void
    {
        $parser = static fn(string $out): ?int => preg_match('/n=(\d+)/', $out, $m) === 1 ? (int) $m[1] : null;

        $result = bcb_run_parsed([PHP_BINARY, '-r', 'echo "nothing";'], self::GENEROUS_TIMEOUT, $parser);

        self::assertSame(BCB_FAILED, $result['outcome']);
        self::assertSame(0, $result['exitCode'], 'the clean exit is still reported, so the cause is visible');
        self::assertStringContainsString('no parseable output', (string) $result['error']);
    }

    #[Test]
    public function a_run_that_answers_yields_its_parsed_value(): void
    {
        $parser = static fn(string $out): ?int => preg_match('/n=(\d+)/', $out, $m) === 1 ? (int) $m[1] : null;

        $result = bcb_run_parsed([PHP_BINARY, '-r', 'echo "n=7";'], self::GENEROUS_TIMEOUT, $parser);

        self::assertSame(BCB_OK, $result['outcome']);
        self::assertSame(7, $result['parsed']);
    }

    // ── In-process wall clock ─────────────────────────────────────────────

    #[Test]
    public function durations_are_measured_in_process_and_never_run_backwards(): void
    {
        $slept = bcb_time(static fn(): int => usleep(200_000) === false ? 0 : 1);
        $noop  = bcb_time(static fn(): int => 1);

        self::assertSame(BCB_OK, $slept['outcome']);
        self::assertGreaterThanOrEqual(0.2, $slept['seconds']);
        self::assertGreaterThanOrEqual(0.0, $noop['seconds']);
        self::assertLessThan($slept['seconds'], $noop['seconds'], 'the clock must discriminate');
    }

    #[Test]
    public function a_subprocess_duration_matches_an_independent_measurement_of_the_same_call(): void
    {
        // The claim that the reported number is an in-process delta rather than
        // something read off the child or off a wall clock.
        $outer = bcb_time(static fn(): array => bcb_run([PHP_BINARY, '-r', 'usleep(300000);'], self::GENEROUS_TIMEOUT));
        $inner = $outer['value'];

        self::assertIsArray($inner);
        self::assertSame(BCB_OK, $inner['outcome']);
        self::assertEqualsWithDelta($outer['seconds'], $inner['seconds'], 0.2);
    }

    #[Test]
    public function a_throwing_measurement_is_a_recorded_failure_not_a_crash(): void
    {
        $result = bcb_time(static function (): never {
            throw new RuntimeException('deliberate');
        });

        self::assertSame(BCB_FAILED, $result['outcome']);
        self::assertStringContainsString('deliberate', (string) $result['error']);
        self::assertGreaterThanOrEqual(0.0, $result['seconds']);
    }

    #[Test]
    public function repetition_reports_every_run_a_median_and_a_spread(): void
    {
        $result = bcb_repeat(5, static fn(): int => usleep(20_000) === false ? 0 : 1);

        self::assertSame(5, $result['n']);
        self::assertCount(5, $result['runs']);
        self::assertSame([], $result['errors']);
        self::assertGreaterThanOrEqual($result['min'], $result['median']);
        self::assertLessThanOrEqual($result['max'], $result['median']);
        self::assertEqualsWithDelta($result['max'] - $result['min'], $result['spread'], 1e-9);
    }

    // ── The gate scripts themselves ───────────────────────────────────────

    #[Test]
    public function the_harness_self_test_passes(): void
    {
        $result = bcb_run([PHP_BINARY, dirname(__DIR__) . '/bench/self-test.php'], 120.0);

        self::assertSame(BCB_OK, $result['outcome'], $result['stdout'] . $result['stderr']);
        self::assertStringContainsString('checks passed', $result['stdout']);
        self::assertStringNotContainsString('FAIL', $result['stdout']);
    }

    #[Test]
    public function injection_produces_a_manifest_the_checker_accepts_and_tampering_breaks_it(): void
    {
        $root = sys_get_temp_dir() . '/bcb-manifest-test-' . getmypid();
        $dir  = $root . '/subject';

        try {
            $inject = bcb_run(
                [PHP_BINARY, dirname(__DIR__) . '/bench/inject.php', __FILE__, $dir, '--ops', 'all'],
                120.0,
            );
            self::assertSame(BCB_OK, $inject['outcome'], $inject['stdout'] . $inject['stderr']);

            $check = bcb_run([PHP_BINARY, dirname(__DIR__) . '/bench/check-manifest.php', $dir], 120.0);
            self::assertSame(BCB_OK, $check['outcome'], $check['stdout'] . $check['stderr']);
            self::assertStringContainsString('checks passed', $check['stdout']);

            // A checker that cannot fail is not a check. Change one byte of one
            // variant and the same command must reject it.
            $variants = glob($dir . '/*_gapped_insert_d2.php') ?: [];
            self::assertCount(1, $variants);
            file_put_contents($variants[0], (string) file_get_contents($variants[0]) . "\n// tampered\n");

            $after = bcb_run([PHP_BINARY, dirname(__DIR__) . '/bench/check-manifest.php', $dir], 120.0);
            self::assertSame(BCB_FAILED, $after['outcome'], 'the checker must reject a tampered variant');
            self::assertStringContainsString('FAILED', $after['stdout']);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($dir);
            @rmdir($root);
        }
    }
}
