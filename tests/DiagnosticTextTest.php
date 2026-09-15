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

use LucianoPereira\PhpcpdNext\OptionParser;
use LucianoPereira\PhpcpdNext\ScanScope;
use LucianoPereira\PhpcpdNext\Settings;
use LucianoPereira\PhpcpdNext\SettingsException;
use function dirname;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What the tool *says* when it refuses, pinned.
 *
 * `ScanScope`, `OptionParser` and the settings errors had no test naming them.
 * That was tolerable while each sentence lived at the line that raised it and
 * nothing else could move it; it stops being tolerable the moment the wording
 * is going to be lifted into a catalogue, because a move with no oracle is
 * indistinguishable from a rewrite.
 *
 * So these assert the sentence a user reads, not the exception class. A test
 * that only checks `SettingsException::class` passes just as happily when the
 * message becomes empty, and an empty refusal is the failure mode that matters:
 * the tool declining to do something without saying why.
 *
 * Each assertion names the *whole* sentence rather than a distinctive fragment
 * of it. Half of these messages are currently written as two string literals
 * joined by `.`, which is exactly what makes them untranslatable, and a test
 * matching only the first half would keep passing while the second half went
 * missing.
 */
#[CoversClass(ScanScope::class)]
#[CoversClass(OptionParser::class)]
final class DiagnosticTextTest extends TestCase
{
    #[Test]
    public function refusingTheFilesystemRootSaysWhyAndHowToOverride(): void
    {
        $refusal = ScanScope::refusal(Settings::fromArgv(['phpcpd', '--no-config', '/']));

        self::assertIsString($refusal);
        self::assertStringContainsString('Refusing to scan the filesystem root (/).', $refusal);
        self::assertStringContainsString('Did you mean "./"?', $refusal);
        self::assertStringContainsString('Pass --allow-root-scan if you really meant the whole filesystem.', $refusal);
    }

    #[Test]
    public function theRootRefusalIsLiftedByTheFlagItNames(): void
    {
        // The remedy a message offers has to work, or the sentence is a lie.
        self::assertNull(ScanScope::refusal(Settings::fromArgv(['phpcpd', '--no-config', '--allow-root-scan', '/'])));
    }

    #[Test]
    public function refusingAboveTheProjectRootNamesBothPathsAndTheOverride(): void
    {
        // The message this pins is currently three string literals joined by
        // `.` around a `PHP_EOL`, which is the shape that cannot be translated
        // and the shape a fragment-matching test would fail to notice breaking.
        $project = dirname(__DIR__);
        $above   = dirname($project);

        $refusal = ScanScope::refusal(Settings::fromArgv(['phpcpd', '--no-config', $above]));

        self::assertIsString($refusal);
        self::assertStringContainsString('Refusing to scan ' . $above . ': it is above the project root ' . $project . '.', $refusal);
        self::assertStringContainsString('Pass --allow-root-scan to scan outside the project anyway.', $refusal);
    }

    #[Test]
    public function aLongOptionMissingItsValueNamesTheOption(): void
    {
        $this->expectException(SettingsException::class);
        $this->expectExceptionMessage('Option --min-tokens needs a value');

        Settings::fromArgv(['phpcpd', '--no-config', '--min-tokens']);
    }

    #[Test]
    public function aValuelessOptionGivenOneNamesTheOption(): void
    {
        $this->expectException(SettingsException::class);
        $this->expectExceptionMessage('Option --verbose does not take a value');

        Settings::fromArgv(['phpcpd', '--no-config', '--verbose=3']);
    }

    #[Test]
    public function aRejectedValueListsWhatWouldBeAccepted(): void
    {
        $this->expectException(SettingsException::class);
        $this->expectExceptionMessage('Invalid value "sideways" for --algorithm (allowed: rabin-karp, tokenbag, unified)');

        Settings::fromArgv(['phpcpd', '--no-config', '--algorithm=sideways', '.']);
    }
}
