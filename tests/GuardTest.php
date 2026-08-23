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

require_once __DIR__ . '/_guard.php';

use function escapeshellarg;
use function fclose;
use function file_put_contents;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function var_export;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The guard in tests/_guard.php must catch `php tests/SomeTest.php` while
 * letting any runner through. The previous implementation asked "is the entry
 * point named phpunit or pest?", which classified every other runner as direct
 * execution and killed the process before a single test ran — an empty suite
 * with no stated reason. One test per branch.
 */
final class GuardTest extends TestCase
{
    #[Test]
    public function running_a_test_file_directly_exits_with_the_explanation(): void
    {
        [$status, , $stderr] = $this->php(__DIR__ . '/OptionsTest.php');

        self::assertSame(1, $status);
        self::assertStringContainsString('not a standalone script', $stderr);
    }

    #[Test]
    public function a_runner_named_neither_phpunit_nor_pest_is_let_through(): void
    {
        $runner = tempnam(sys_get_temp_dir(), 'crucible');
        self::assertIsString($runner);

        // A stand-in for any runner: it lives outside tests/, is named nothing
        // like phpunit or pest, and includes a test file the way a runner does.
        file_put_contents($runner, '<?php require ' . var_export(__DIR__ . '/OptionsTest.php', true) . "; echo 'suite started';");

        [$status, $stdout] = $this->php($runner);
        unlink($runner);

        self::assertSame(0, $status, 'a foreign runner must not be treated as direct execution');
        self::assertStringContainsString('suite started', $stdout);
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function php(string $script): array
    {
        $process = proc_open(
            PHP_BINARY . ' ' . escapeshellarg($script),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return [proc_close($process), $stdout, $stderr];
    }
}
