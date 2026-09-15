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

use function getcwd;

use LucianoPereira\PhpcpdNext\Log\ReportPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportPath::class)]
final class ReportPathTest extends TestCase
{
    /**
     * The defect this exists for: the same scan described itself two ways
     * depending on whether the command named a relative or an absolute
     * directory, so two runs over identical code produced different documents.
     */
    #[Test]
    public function bothWaysOfNamingOneFileGiveOneAnswer(): void
    {
        $path = ReportPath::fromWorkingDirectory();

        self::assertSame(
            $path->of('src/Log/ReportPath.php'),
            $path->of(getcwd() . '/src/Log/ReportPath.php'),
        );
    }

    #[Test]
    public function aFileInsideTheBaseIsNamedRelativeToIt(): void
    {
        self::assertSame(
            'src/Log/ReportPath.php',
            ReportPath::fromWorkingDirectory()->of('src/Log/ReportPath.php'),
        );
    }

    /**
     * There is nothing honest to make it relative to, so it stays absolute
     * rather than growing a run of `../`.
     */
    #[Test]
    public function aFileOutsideTheBaseStaysAbsolute(): void
    {
        self::assertSame('/etc/hosts', ReportPath::fromWorkingDirectory()->of('/etc/hosts'));
    }

    /**
     * A file deleted between the scan and the report cannot be resolved.
     * Repeating what was scanned beats inventing a location for it.
     */
    #[Test]
    public function anUnresolvablePathIsReportedAsItWasGiven(): void
    {
        self::assertSame(
            'no/such/file.php',
            ReportPath::fromWorkingDirectory()->of('no/such/file.php'),
        );
    }

    #[Test]
    public function aCallerCanNameTheBaseItself(): void
    {
        self::assertSame(
            'Log/ReportPath.php',
            ReportPath::relativeTo(getcwd() . '/src')->of('src/Log/ReportPath.php'),
        );
    }
}
