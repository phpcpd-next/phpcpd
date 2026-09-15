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

use function dirname;
use function escapeshellarg;
use function fclose;
use function proc_close;
use function proc_open;
use function stream_get_contents;

use const PHP_BINARY;

/**
 * Runs the real binary in a real process.
 *
 * Some of what this tool does is not visible from inside it: which file
 * descriptor a byte lands on, and whether that descriptor is a terminal. An
 * output buffer sees the same string either way, so these assertions have to
 * cross a process boundary to mean anything.
 */
trait RunsTheBinary
{
    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment added to this process's own
     * @param null|string $workingDirectory where to run, when the defect under
     * test depends on how the scan root is spelled; the project root by default
     * @return array{0: string, 1: string, 2: int} stdout, stderr, exit code
     */
    private function invoke(array $arguments, array $environment = [], ?string $workingDirectory = null): array
    {
        $root    = dirname(__DIR__);
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/phpcpd');

        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg($argument);
        }

        $process = self::assertIsResourceAndReturn(proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workingDirectory ?? $root,
            $environment === [] ? null : [...$_ENV, ...$environment],
        ));

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return [$stdout, $stderr, proc_close($process)];
    }

    /**
     * @return resource
     */
    private static function assertIsResourceAndReturn(mixed $process): mixed
    {
        self::assertIsResource($process);

        return $process;
    }
}
