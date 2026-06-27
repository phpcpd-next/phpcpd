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

use function array_map;
use function array_unique;
use function array_values;

use LucianoPereira\PhpcpdNext\OptionDefinition;
use LucianoPereira\PhpcpdNext\Options;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Options::class)]
#[CoversClass(OptionDefinition::class)]
final class OptionsTest extends TestCase
{
    /**
     * Every non-advanced option must appear in --help. Advanced (research) flags
     * are intentionally hidden from the default help to keep the UX clean.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function visibleOptionNames(): iterable
    {
        foreach (Options::definitions() as $definition) {
            if (!$definition->advanced) {
                yield $definition->name => ['--' . $definition->name];
            }
        }
    }

    /** @return iterable<string, array{0: string}> */
    public static function advancedOptionNames(): iterable
    {
        foreach (Options::definitions() as $definition) {
            if ($definition->advanced) {
                yield $definition->name => ['--' . $definition->name];
            }
        }
    }

    #[Test]
    #[DataProvider('visibleOptionNames')]
    public function help_documents_every_visible_option(string $flag): void
    {
        self::assertStringContainsString($flag, Options::help());
    }

    #[Test]
    #[DataProvider('advancedOptionNames')]
    public function advanced_options_are_hidden_from_help_but_still_parseable(string $flag): void
    {
        self::assertStringNotContainsString($flag, Options::help());
    }

    #[Test]
    public function help_documents_verbose_the_option_the_old_help_dropped(): void
    {
        self::assertStringContainsString('--verbose', Options::help());
    }

    #[Test]
    public function help_shows_short_aliases(): void
    {
        $help = Options::help();

        self::assertStringContainsString('-h, --help', $help);
        self::assertStringContainsString('-v, --version', $help);
    }

    #[Test]
    public function definitions_have_unique_names(): void
    {
        $names = array_map(static fn (OptionDefinition $d): string => $d->name, Options::definitions());

        self::assertSame($names, array_values(array_unique($names)));
    }
}
