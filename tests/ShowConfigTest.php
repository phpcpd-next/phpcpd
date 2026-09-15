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

use function in_array;
use function sprintf;

use LucianoPereira\PhpcpdNext\ConfigReport;
use LucianoPereira\PhpcpdNext\Options;
use LucianoPereira\PhpcpdNext\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * `--show-config` prints the settings in force. An option it has no row for is
 * dropped without a word, which reads exactly like a setting that is not set.
 *
 * Seven were being dropped that way — `preset`, `allow-root-scan`,
 * `acknowledged`, `write-acknowledged` and `language` among them — because the
 * value map ends in `default => null` and a new option falls through it.
 */
#[CoversClass(ConfigReport::class)]
final class ShowConfigTest extends TestCase
{
    #[Test]
    public function everyOptionThatConfiguresTheRunHasARow(): void
    {
        $unreported = (new ReflectionClass(ConfigReport::class))->getConstant('UNREPORTED');
        self::assertIsArray($unreported);

        $value = new ReflectionMethod(ConfigReport::class, 'value');
        $settings = Settings::resolve([]);

        foreach (Options::definitions() as $definition) {
            // Deliberate omissions: options that act on this run rather than
            // configuring it, aliases shown under their canonical name, and
            // research flags, which appear only once someone has set one.
            if (in_array($definition->name, $unreported, true) || $definition->advanced) {
                continue;
            }

            self::assertNotNull(
                $value->invoke(null, $settings, $definition->name),
                sprintf(
                    '--%s is reported by --show-config as nothing at all. Give it a row in '
                    . 'ConfigReport::value(), or name it in UNREPORTED and say why.',
                    $definition->name,
                ),
            );
        }
    }

    #[Test]
    public function theReportNamesTheSettingsItPrints(): void
    {
        $report = ConfigReport::render(
            ['phpcpd', '--no-config', '--show-config', '.'],
            Settings::fromArgv(['phpcpd', '--no-config', '--show-config', '.']),
        );

        foreach (['paths', 'min-tokens', 'language', 'preset', 'allow-root-scan'] as $name) {
            self::assertStringContainsString($name, $report);
        }
    }
}
