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

use LucianoPereira\PhpcpdNext\ArgumentsBuilder;
use LucianoPereira\PhpcpdNext\ArgumentsBuilderException;
use LucianoPereira\PhpcpdNext\Preset;
use LucianoPereira\PhpcpdNext\Presets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preset::class)]
#[CoversClass(Presets::class)]
#[CoversClass(ArgumentsBuilder::class)]
final class PresetTest extends TestCase
{
    #[Test]
    public function laravel_preset_is_registered(): void
    {
        self::assertContains('laravel', Presets::names());
        self::assertInstanceOf(Preset::class, Presets::get('laravel'));
    }

    #[Test]
    public function unknown_preset_is_not_found(): void
    {
        self::assertNull(Presets::get('symfony'));
    }

    #[Test]
    public function laravel_preset_excludes_framework_noise(): void
    {
        $preset = Presets::get('laravel');

        self::assertNotNull($preset);
        self::assertContains('vendor', $preset->exclude);
        self::assertContains('*.blade.php', $preset->exclude);
        self::assertContains('database/migrations', $preset->exclude);
        self::assertSame(['app', 'routes', 'database', 'config'], $preset->paths);
    }

    #[Test]
    public function preset_seeds_excludes_and_default_paths(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--preset=laravel']);

        self::assertSame(['app', 'routes', 'database', 'config'], $args->directories());
        self::assertContains('vendor', $args->exclude());
        self::assertContains('*.blade.php', $args->exclude());
    }

    #[Test]
    public function explicit_directory_overrides_preset_paths_but_keeps_excludes(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--preset=laravel', 'src/']);

        self::assertSame(['src/'], $args->directories());
        self::assertContains('vendor', $args->exclude());
    }

    #[Test]
    public function explicit_exclude_appends_to_preset_excludes(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--preset=laravel', '--exclude=custom', 'src/']);

        self::assertContains('vendor', $args->exclude());  // from preset
        self::assertContains('custom', $args->exclude());  // from the flag
    }

    #[Test]
    public function explicit_threshold_overrides_preset(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--preset=laravel', '--min-tokens=99', 'src/']);

        self::assertSame(99, $args->tokensThreshold());
    }

    #[Test]
    public function unknown_preset_is_rejected(): void
    {
        $this->expectException(ArgumentsBuilderException::class);
        $this->expectExceptionMessage('symfony');

        (new ArgumentsBuilder())->build(['phpcpd', '--preset=symfony', 'src/']);
    }
}
