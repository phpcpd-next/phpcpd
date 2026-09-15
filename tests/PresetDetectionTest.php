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

use LucianoPereira\PhpcpdNext\PresetDetection;
use LucianoPereira\PhpcpdNext\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Framework detection, against a constructed fixture rather than a real tree.
 *
 * The fixture exists to pin the two halves of the evidence rule *apart*: one
 * project declares the dependency and carries the marker, one declares it only
 * in `require-dev`, and one carries a file named `artisan` while requiring
 * nothing. Only the first is a Laravel application, and a detector that fired on
 * either of the others would be guessing.
 */
#[CoversClass(PresetDetection::class)]
final class PresetDetectionTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/fixtures/preset-detection';

    public function testDetectsAnApplicationDeclaringAndCarryingTheEvidence(): void
    {
        $preset = PresetDetection::detect([self::FIXTURES . '/app']);

        self::assertNotNull($preset);
        self::assertSame('laravel', $preset->name);
    }

    /**
     * A package that *tests against* a framework is not built on it. This is the
     * case that makes `require-dev` unusable as evidence, and the reason the
     * detector reads `require` alone.
     */
    public function testDeclinesWhenTheFrameworkIsOnlyADevelopmentDependency(): void
    {
        self::assertNull(PresetDetection::detect([self::FIXTURES . '/library']));
    }

    /** A filename is not a declaration: anyone may ship a file called `artisan`. */
    public function testDeclinesOnAStructuralMarkerAlone(): void
    {
        self::assertNull(PresetDetection::detect([self::FIXTURES . '/marker-only']));
    }

    /**
     * `phpcpd app/` inside a project is an ordinary invocation, and the manifest
     * that describes the project sits above the directory being scanned.
     */
    public function testWalksUpFromASubdirectoryToTheManifest(): void
    {
        $preset = PresetDetection::detect([self::FIXTURES . '/app/src']);

        self::assertNotNull($preset);
        self::assertSame('laravel', $preset->name);
    }

    /**
     * Detection seeds the framework's excludes and nothing else. Scan paths stay
     * the user's, because detection established what the project *is* — not that
     * the user wanted four of its directories instead of the one they typed.
     */
    public function testDetectionSeedsExcludesButNeverScanPaths(): void
    {
        $settings = Settings::resolve([], [self::FIXTURES . '/app']);

        self::assertTrue($settings->presetDetected);
        self::assertSame('laravel', $settings->preset);
        self::assertContains('storage', $settings->exclude);
        self::assertContains('bootstrap/cache', $settings->exclude);
        self::assertFalse($settings->directoriesFromPreset);
        self::assertSame([self::FIXTURES . '/app'], $settings->directories);
    }

    public function testNoPresetSuppressesDetection(): void
    {
        $settings = Settings::resolve([['no-preset', null]], [self::FIXTURES . '/app']);

        self::assertFalse($settings->presetDetected);
        self::assertNull($settings->preset);
        self::assertNotContains('storage', $settings->exclude);
    }

    /**
     * An explicit preset is the user asking for the full treatment, so it seeds
     * scan paths too — and it is not reported as detected, because it was not.
     */
    public function testAnExplicitPresetOverridesDetection(): void
    {
        $settings = Settings::resolve([['preset', 'laravel']], [self::FIXTURES . '/app']);

        self::assertFalse($settings->presetDetected);
        self::assertSame('laravel', $settings->preset);
    }

    public function testDeclinesWhenNoManifestExistsAnywhereAbove(): void
    {
        self::assertNull(PresetDetection::detect(['/']));
    }

    public function testLabelIsTheFrameworkName(): void
    {
        self::assertSame('Laravel', PresetDetection::label('laravel'));
    }
}
