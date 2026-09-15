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

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use function dirname;

/**
 * A throwaway project on disk, for the tests that have to run against a real
 * tree rather than a string.
 *
 * Several properties are only visible from outside the library — what triage
 * discards, which files the finder admits, how a scan root is spelled — so the
 * fixture has to be a directory the binary can be pointed at. Building one is
 * the same three steps every time (a unique root, files written into it,
 * everything removed afterwards), and each test class had written those steps
 * out again: five copies of a recursive delete is five chances for one of them
 * to leave a temp directory behind.
 */
trait BuildsAFixtureProject
{
    /** @var non-empty-string set per test in setUp() */
    private string $root = 'unset';

    /**
     * A fresh root for this test, under the system temp directory and named
     * after $label so a leftover directory says which test left it.
     */
    private function makeFixtureRoot(string $label): void
    {
        $this->root = sys_get_temp_dir() . '/phpcpd-' . $label . '-' . uniqid();
    }

    /**
     * Write one file into the fixture, creating whatever directories it names.
     */
    private function write(string $path, string $contents): void
    {
        $file      = $this->root . '/' . $path;
        $directory = dirname($file);

        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        file_put_contents($file, $contents);
    }

    /**
     * Remove a directory and everything under it. Called on the fixture root in
     * tearDown(), and on itself for each subdirectory.
     */
    private function delete(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $child = $path . '/' . $entry;

            is_dir($child) ? $this->delete($child) : unlink($child);
        }

        rmdir($path);
    }
}
