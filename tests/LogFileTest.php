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

use function file_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use LucianoPereira\PhpcpdNext\Log\LogFile;
use LucianoPereira\PhpcpdNext\LogWriteException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogFile::class)]
#[CoversClass(LogWriteException::class)]
final class LogFileTest extends TestCase
{
    #[Test]
    public function aReportIsWrittenWhereItWasAsked(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phpcpd-log-');

        try {
            (new LogFile($path))->write('<pmd/>');

            self::assertSame('<pmd/>', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    /**
     * The defect this exists for: `--log-sarif=build/report.sarif` on a machine
     * with no `build/` directory used to print its findings, write nothing, and
     * exit 0 — and the pipeline downstream read the missing file as a clean run.
     */
    #[Test]
    public function aReportThatCannotBeWrittenIsNotASilentSuccess(): void
    {
        $this->expectException(LogWriteException::class);
        $this->expectExceptionMessageMatches('/Could not write the report to /');

        (new LogFile('/nonexistent-directory-for-this-test/report.sarif'))->write('{}');
    }

    /** The path is named, because it is the only thing the user can act on. */
    #[Test]
    public function theFailureNamesThePath(): void
    {
        try {
            (new LogFile('/nonexistent-directory-for-this-test/report.xml'))->write('<pmd/>');

            self::fail('expected a ' . LogWriteException::class);
        } catch (LogWriteException $e) {
            self::assertStringContainsString('/nonexistent-directory-for-this-test/report.xml', $e->getMessage());
        }
    }
}
