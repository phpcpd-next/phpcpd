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

use function preg_replace;
use function str_contains;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Colour is emphasis on text that is already there.
 *
 * The report is read by people and by pipes, and the pipe's copy is the one the
 * golden fixtures, the log-equivalence bench and every `| grep` depend on. So
 * the invariant is not "colour looks nice": it is that a coloured run and a
 * plain run differ by escape sequences and by nothing else — same words, same
 * order, same lines.
 */
final class ColourTest extends TestCase
{
    use RunsTheBinary;

    /** The one line that legitimately differs between two runs. */
    private function withoutTiming(string $report): string
    {
        return (string) preg_replace('/^Time: .*$/m', 'Time: —', $report);
    }

    private function withoutEscapes(string $report): string
    {
        return (string) preg_replace('/\e\[[0-9;]*m/', '', $report);
    }

    #[Test]
    public function aColouredReportSaysExactlyWhatAPlainOneSays(): void
    {
        [$plain]    = $this->invoke(['--no-config', '--verbose', 'tests/fixtures']);
        [$coloured] = $this->invoke(['--no-config', '--verbose', 'tests/fixtures'], ['FORCE_COLOR' => '1']);

        self::assertStringContainsString("\e[", $coloured, 'FORCE_COLOR should have coloured it');
        self::assertStringNotContainsString("\e[", $plain, 'a pipe should not be coloured');

        self::assertSame(
            $this->withoutTiming($plain),
            $this->withoutTiming($this->withoutEscapes($coloured)),
        );
    }

    /**
     * An explicit refusal outranks an explicit request. That is the convention's
     * precedence, and it is the one a user reaches for when a terminal claims
     * colour support it does not have.
     */
    #[Test]
    public function noColourWinsOverForceColour(): void
    {
        [$stdout] = $this->invoke(
            ['--no-config', 'tests/fixtures'],
            ['FORCE_COLOR' => '1', 'NO_COLOR' => '1'],
        );

        self::assertStringNotContainsString("\e[", $stdout);
    }

    /**
     * A finding demoted by triage is dimmed, not hidden. The tag is what the
     * colour marks, so the plain reader loses nothing.
     */
    #[Test]
    public function aDemotedFindingIsDimmedAndStillNamed(): void
    {
        [$stdout] = $this->invoke(
            ['--no-config', '--verbose', '--no-triage', 'tests/fixtures'],
            ['FORCE_COLOR' => '1'],
        );

        if (!str_contains($this->withoutEscapes($stdout), '[demoted:')) {
            self::markTestSkipped('no fixture in this run was demoted');
        }

        self::assertMatchesRegularExpression('/\e\[2m \[demoted: [a-z]+\]\e\[0m/', $stdout);
    }
}
