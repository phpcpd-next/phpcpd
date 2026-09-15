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

use LucianoPereira\PhpcpdNext\Options;
use LucianoPereira\PhpcpdNext\Settings;
use LucianoPereira\PhpcpdNext\SettingsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The resolution fold: defaults → preset → config-file pairs → CLI pairs, all
 * through one `match`. These tests are the other half of that design — the
 * match deliberately has no silent default arm, so this suite folds every
 * defined option to guarantee an option can never exist without a binding.
 */
#[CoversClass(Settings::class)]
final class SettingsTest extends TestCase
{
    /**
     * The drift guard. {@see Settings::apply()} throws for an option without a
     * `match` arm; this folds every option {@see Options} defines, so adding a
     * definition without a binding fails here, in CI — not on the first user
     * who passes the new flag.
     */
    public function testEveryDefinedOptionHasABinding(): void
    {
        foreach (Options::definitions() as $definition) {
            $value = $definition->takesValue
                ? ($definition->allowedValues[0] ?? '1')
                : null;

            $settings = Settings::resolve([[$definition->name, $value]]);

            $this->assertInstanceOf(Settings::class, $settings, $definition->name);
        }
    }

    public function testTheDefaultsHoldWhenNothingIsSaid(): void
    {
        $settings = Settings::resolve([]);

        $this->assertSame([], $settings->directories);
        $this->assertSame(['.php'], $settings->suffixes);
        $this->assertSame([], $settings->exclude);
        $this->assertSame(5, $settings->minLines);
        $this->assertSame(100, $settings->minTokens);
        $this->assertSame(['dead'], $settings->failOn);
        $this->assertNull($settings->algorithm);
        $this->assertNull($settings->cacheDir);
        $this->assertTrue($settings->defaultExcludes);
        $this->assertFalse($settings->orphans);
        $this->assertNull($settings->preset);
    }

    /**
     * The layering rule in one test: the preset seeds below everything, a later
     * scalar replaces, a repeatable option appends onto the seed.
     */
    public function testAPresetSeedsAndExplicitOptionsOverrideOrAppend(): void
    {
        $settings = Settings::resolve([
            ['preset', 'laravel'],
            ['suffix', '.inc'],
            ['exclude', 'demo'],
            ['min-tokens', '42'],
        ]);

        $this->assertSame('laravel', $settings->preset);
        $this->assertContains('.php', $settings->suffixes, "a preset's seed survives an append");
        $this->assertContains('.inc', $settings->suffixes, 'an explicit suffix appends');
        $this->assertContains('*.blade.php', $settings->exclude, "the preset's excludes survive");
        $this->assertContains('demo', $settings->exclude, 'an explicit exclude appends');
        $this->assertSame(42, $settings->minTokens, 'an explicit threshold replaces');
    }

    /**
     * Triage is on by default, in the posture that acts on what it decides.
     * `TriagePostureTest` asserts the behaviour of each posture by name; this
     * asserts only which one a bare invocation resolves to.
     */
    public function testTriageIsOnByDefaultInTheDiscardingPosture(): void
    {
        $default = Settings::resolve([]);

        self::assertTrue($default->triage);
        self::assertSame('discard', $default->triagePosture);

        $off = Settings::resolve([['no-triage', null]]);

        self::assertFalse($off->triage);
        self::assertSame('discard', $off->triagePosture, 'the posture default stands even while off');

        $on = Settings::resolve([['triage', null]]);

        self::assertTrue($on->triage, 'asking for what is already on is not an error');
        self::assertSame('discard', $on->triagePosture);
    }

    /** The posture that judges without acting is asked for by name. */
    public function testLabellingIsAskedForByName(): void
    {
        $settings = Settings::resolve([['triage-posture', 'label']]);

        self::assertTrue($settings->triage);
        self::assertSame('label', $settings->triagePosture);
    }

    /** Naming a posture asks for the stage: nobody means "discard, but do not run". */
    public function testNamingAPostureImpliesTheStage(): void
    {
        $settings = Settings::resolve([['triage-posture', 'discard']]);

        self::assertTrue($settings->triage);
        self::assertSame('discard', $settings->triagePosture);
    }

    /**
     * The CLI checks `allowedValues`, but a config file and the headless facade
     * reach {@see Settings::apply()} without passing through that check.
     */
    public function testAnUnknownPostureIsRefused(): void
    {
        $this->expectException(SettingsException::class);

        Settings::resolve([['triage-posture', 'delete']]);
    }

    /** The preset seeds wherever its flag appears — it is a layer, not a position. */
    public function testAPresetSeedsBelowOptionsThatPrecedeItInTheFold(): void
    {
        $before = Settings::resolve([['min-tokens', '42'], ['preset', 'laravel']]);
        $after  = Settings::resolve([['preset', 'laravel'], ['min-tokens', '42']]);

        $this->assertSame(42, $before->minTokens);
        $this->assertSame($after->suffixes, $before->suffixes);
        $this->assertSame($after->exclude, $before->exclude);
    }

    public function testDirectoriesFallBackToThePresetPathsAndSaySo(): void
    {
        $seeded   = Settings::resolve([['preset', 'laravel']]);
        $explicit = Settings::resolve([['preset', 'laravel']], ['src']);

        $this->assertSame(['app', 'routes', 'database', 'config'], $seeded->directories);
        $this->assertTrue($seeded->directoriesFromPreset);
        $this->assertSame(['src'], $explicit->directories);
        $this->assertFalse($explicit->directoriesFromPreset);
    }

    public function testAnUnknownPresetIsRefusedByName(): void
    {
        $this->expectException(SettingsException::class);
        $this->expectExceptionMessage('Unknown preset: rails');

        Settings::resolve([['preset', 'rails']]);
    }

    public function testAliasAndImplicationArms(): void
    {
        $rk    = Settings::resolve([['rk', null]]);
        $cache = Settings::resolve([['cache', null]]);
        $both  = Settings::resolve([['cache-dir', 'x'], ['cache', null]]);

        $this->assertSame('rabin-karp', $rk->algorithm);
        $this->assertSame('.phpcpd-cache', $cache->cacheDir);
        $this->assertSame('x', $both->cacheDir, '--cache must not clobber an explicit --cache-dir');
    }

    public function testCommaListsReplaceForFailOnAndAppendForNoSuppress(): void
    {
        $settings = Settings::resolve([
            ['fail-on', 'dead,planned'],
            ['no-suppress', 'fixtures'],
            ['no-suppress', 'config, template'],
        ]);

        $this->assertSame(['dead', 'planned'], $settings->failOn);
        $this->assertSame(['fixtures', 'config', 'template'], $settings->noSuppress);
    }

    /**
     * The point of routing the headless facades through the same fold: a run
     * described by named parameters and the identical run described by argv must
     * resolve to the same settings, field for field.
     */
    public function testTheCliAndTheFoldResolveIdentically(): void
    {
        $argv = Settings::fromArgv(['phpcpd', '--no-config', '--preset', 'laravel', '--min-tokens', '42', '--exclude', 'demo']);
        $fold = Settings::resolve([
            ['preset', 'laravel'],
            ['min-tokens', '42'],
            ['exclude', 'demo'],
        ]);

        $this->assertSame($fold->directories, $argv->directories);
        $this->assertSame($fold->suffixes, $argv->suffixes);
        $this->assertSame($fold->exclude, $argv->exclude);
        $this->assertSame($fold->minTokens, $argv->minTokens);
        $this->assertSame($fold->preset, $argv->preset);
        $this->assertSame($fold->directoriesFromPreset, $argv->directoriesFromPreset);
    }

    public function testARunWithNothingToScanAndNothingElseToDoIsRefused(): void
    {
        $this->expectException(SettingsException::class);
        $this->expectExceptionMessage('No directory specified');

        Settings::fromArgv(['phpcpd', '--no-config']);
    }

    public function testHelpVersionAndShowConfigNeedNoDirectory(): void
    {
        $this->assertTrue(Settings::fromArgv(['phpcpd', '--no-config', '--help'])->help);
        $this->assertTrue(Settings::fromArgv(['phpcpd', '--no-config', '--version'])->version);
        $this->assertTrue(Settings::fromArgv(['phpcpd', '--no-config', '--show-config'])->showConfig);
    }
}
