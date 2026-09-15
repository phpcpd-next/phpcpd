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

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\LanguagePreference;
use LucianoPereira\PhpcpdNext\Strings\Catalogue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `--language` end to end, in a real process. Every assertion is about a
 * sentence a user sees, which is the only place this is observable.
 *
 * The refusals matter most: they are raised while the settings are still being
 * built, so nothing has yet said what language to print them in.
 */
#[CoversClass(Catalogue::class)]
#[CoversClass(LanguagePreference::class)]
final class LocalizationTest extends TestCase
{
    use RunsTheBinary;

    /** @var non-empty-string set per test in setUp() */
    private string $project = '/';

    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir() . '/phpcpd-i18n-' . uniqid();

        mkdir($this->project . '/src', 0o777, true);
        file_put_contents($this->project . '/src/A.php', "<?php\n\nnamespace Fixture;\n\nclass A\n{\n    public function r(): int\n    {\n        return 1;\n    }\n}\n");
    }

    protected function tearDown(): void
    {
        self::remove($this->project);
    }

    #[Test]
    public function theFlagChoosesTheLanguageOfAReport(): void
    {
        [$stdout, , ] = $this->invoke(['--no-config', '--no-triage', '--language=es', '.'], [], $this->project);

        self::assertStringContainsString('No se encontraron clones de código.', $stdout);
    }

    #[Test]
    public function aRefusalRaisedWhileParsingIsAlsoTranslated(): void
    {
        // Thrown by the parse that would have told us the language.
        [, $stderr, $status] = $this->invoke(['--no-config', '--language=es', '--nope', '.'], [], $this->project);

        self::assertSame(1, $status);
        self::assertStringContainsString('Opción desconocida --nope.', $stderr);
    }

    #[Test]
    public function aConfigFileChoosesTheLanguageToo(): void
    {
        file_put_contents($this->project . '/phpcpd.ini', "language = fr\n");

        [, $stderr, ] = $this->invoke(['--nope', '.'], [], $this->project);

        self::assertStringContainsString('Option inconnue --nope.', $stderr);
    }

    #[Test]
    public function theFlagBeatsTheConfigFile(): void
    {
        file_put_contents($this->project . '/phpcpd.ini', "language = fr\n");

        [, $stderr, ] = $this->invoke(['--language=it', '--nope', '.'], [], $this->project);

        self::assertStringContainsString('Opzione sconosciuta --nope.', $stderr);
    }

    #[Test]
    public function noConfigIgnoresTheLanguageTheFileAsksFor(): void
    {
        file_put_contents($this->project . '/phpcpd.ini', "language = fr\n");

        [, $stderr, ] = $this->invoke(['--no-config', '--nope', '.'], [], $this->project);

        self::assertStringContainsString('Unknown option --nope.', $stderr);
    }

    #[Test]
    public function anUnknownLanguageIsRefusedRatherThanServedInEnglish(): void
    {
        [, $stderr, $status] = $this->invoke(['--no-config', '--language=engllish', '.'], [], $this->project);

        self::assertSame(1, $status);
        self::assertStringContainsString('engllish', $stderr);
        self::assertStringContainsString('en', $stderr, 'the refusal names what does exist');
    }

    #[Test]
    public function everyShippedLanguageLoadsAndPrintsAReport(): void
    {
        // The cheapest proof that each file loads and holds only strings.
        foreach (Catalogue::available() as $language) {
            [$stdout, $stderr, $status] = $this->invoke(
                ['--no-config', '--no-triage', '--language=' . $language, '.'],
                [],
                $this->project,
            );

            self::assertSame(0, $status, $language . ' failed: ' . $stderr);
            self::assertNotSame('', $stdout, $language . ' printed nothing');
        }
    }

    private static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach ((array) scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            is_dir($path) ? self::remove($path) : unlink($path);
        }

        rmdir($directory);
    }
}
