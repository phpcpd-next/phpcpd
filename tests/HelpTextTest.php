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
use function preg_split;
use function trim;

use LucianoPereira\PhpcpdNext\Options;
use LucianoPereira\PhpcpdNext\Strings\Catalogue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `--help`, pinned as text.
 *
 * A third of everything this tool says to a user is `--help`, and nothing
 * asserted a word of it: the only existing test checks that the flag sets a
 * boolean, which passes just as well when every description is empty. That was
 * survivable while each description sat as a literal beside the option it
 * describes; it stops being survivable when they move into a catalogue, because
 * a move without an oracle cannot be told apart from a rewrite.
 *
 * The banner is dropped before comparing. It carries the version, and a golden
 * that has to be recaptured at every release teaches people to recapture
 * goldens without reading them.
 *
 * Width is not pinned because it does not need to be: help wraps to the
 * terminal, and a test has no terminal, so the layout here is the one every
 * piped and redirected run gets.
 */
final class HelpTextTest extends TestCase
{
    use RunsTheBinary;

    #[Test]
    public function helpIsWhatTheGoldenSays(): void
    {
        [$stdout, , $exit] = $this->invoke(['--help']);

        self::assertSame(0, $exit);

        $golden = (string) file_get_contents(__DIR__ . '/fixtures/help/help.txt');

        self::assertSame(trim($golden), trim(self::withoutBanner($stdout)));
    }

    #[Test]
    public function everyOptionTheParserAcceptsIsDescribedInHelp(): void
    {
        // The failure this catches is an option that exists and is invisible:
        // shipped, parseable, and impossible to discover.
        [$stdout, , ] = $this->invoke(['--help']);

        foreach (['--min-tokens', '--min-lines', '--language', '--exclude', '--preset', '--verbose'] as $option) {
            self::assertStringContainsString($option, $stdout, $option . ' is accepted but undocumented');
        }
    }

    #[Test]
    public function everyOptionHasTextInTheCatalogue(): void
    {
        // The drift guard, matching the one `SettingsTest` keeps over bindings:
        // an option added without a locale entry fails here, in CI, rather than
        // on the first user who runs `--help` and reads an exception instead of
        // a description. `get()` raises on a missing key, so calling it is the
        // assertion; the count is asserted too, so a definitions list that
        // silently empties cannot pass by iterating nothing.
        $strings = new Catalogue();
        $seen    = 0;

        foreach (Options::definitions() as $definition) {
            $strings->get('help.option.' . $definition->description, $definition->descriptionParameters);
            $strings->get('help.group.' . $definition->group);
            $seen++;
        }

        self::assertGreaterThan(30, $seen);
    }

    /** The first line names the version; everything after it is the help itself. */
    private static function withoutBanner(string $output): string
    {
        $lines = preg_split('/\R/', $output, 2);

        return $lines[1] ?? '';
    }
}
