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


use LucianoPereira\PhpcpdNext\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A diagnostic goes to stderr, and the report goes to stdout.
 *
 * Every message this tool printed used to go to stdout, errors included, so
 * `phpcpd src > report.txt` wrote its failures into the report. Asserted
 * through a real process rather than an output buffer, because the thing under
 * test is which file descriptor a byte lands on, and an in-process test cannot
 * see the difference.
 */
#[CoversClass(Application::class)]
final class DiagnosticStreamTest extends TestCase
{
    use RunsTheBinary;

    #[Test]
    public function aFailureIsReportedOnStderrAndNotOnStdout(): void
    {
        [$stdout, $stderr, $exit] = $this->invoke(['--no-config', '/nonexistent-path-for-this-test']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('No files found to scan', $stderr);
        self::assertStringNotContainsString('No files found to scan', $stdout);
    }

    /**
     * And the banner is not a diagnostic. It is the first line of the text
     * report, so a run that succeeds writes it where the report goes.
     */
    #[Test]
    public function theBannerStaysOnStdout(): void
    {
        [$stdout, , ] = $this->invoke(['--version']);

        self::assertStringContainsString('phpcpd', $stdout);
    }
}
