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

use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\ArgumentsBuilder;
use LucianoPereira\PhpcpdNext\ArgumentsBuilderException;
use LucianoPereira\PhpcpdNext\ConfigFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * phpcpd.ini, whose keys are the long option names. The layering rule under test
 * is "closer wins": the command line beats the project file, and a key the file
 * omits keeps its built-in default.
 */
#[CoversClass(ConfigFile::class)]
#[CoversClass(ArgumentsBuilder::class)]
final class ConfigFileTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }

        $this->paths = [];
    }

    #[Test]
    public function settings_are_read_as_if_they_preceded_the_command_line(): void
    {
        $file = $this->write("min-tokens = 42\nverbose = true\n");

        $arguments = (new ArgumentsBuilder())->build(['phpcpd', '--config', $file, 'src']);

        self::assertSame(42, $arguments->tokensThreshold());
        self::assertTrue($arguments->verbose());
    }

    #[Test]
    public function an_explicit_flag_overrides_the_file(): void
    {
        $file = $this->write("min-tokens = 42\n");

        $arguments = (new ArgumentsBuilder())->build(['phpcpd', '--config', $file, '--min-tokens', '99', 'src']);

        self::assertSame(99, $arguments->tokensThreshold());
    }

    #[Test]
    public function a_key_the_file_omits_keeps_its_default(): void
    {
        $file = $this->write("min-tokens = 42\n");

        $arguments = (new ArgumentsBuilder())->build(['phpcpd', '--config', $file, 'src']);

        self::assertSame(5, $arguments->linesThreshold());
        self::assertFalse($arguments->verbose());
    }

    #[Test]
    public function a_repeatable_key_appends_rather_than_replacing(): void
    {
        $file = $this->write("exclude[] = build\nexclude[] = dist\n");

        $arguments = (new ArgumentsBuilder())->build(['phpcpd', '--config', $file, '--exclude', 'extra', 'src']);

        self::assertSame(['build', 'dist', 'extra'], $arguments->exclude());
    }

    #[Test]
    public function a_false_flag_is_the_same_as_not_writing_the_line(): void
    {
        $file = $this->write("orphans = false\n");

        self::assertFalse((new ArgumentsBuilder())->build(['phpcpd', '--config', $file, 'src'])->orphans());
    }

    #[Test]
    public function list_valued_settings_are_validated_element_by_element(): void
    {
        $file = $this->write("fail-on = dead,planned\n");

        $arguments = (new ArgumentsBuilder())->build(['phpcpd', '--config', $file, 'src']);

        self::assertSame(['dead', 'planned'], $arguments->failOn());
    }

    #[Test]
    public function an_unknown_setting_is_rejected_by_name(): void
    {
        $file = $this->write("not-an-option = 1\n");

        $this->expectException(ArgumentsBuilderException::class);
        $this->expectExceptionMessageMatches('/Unknown setting "not-an-option"/');

        (new ArgumentsBuilder())->build(['phpcpd', '--config', $file, 'src']);
    }

    #[Test]
    public function an_invalid_value_is_rejected_with_the_allowed_set(): void
    {
        $file = $this->write("fail-on = nonsense\n");

        $this->expectException(ArgumentsBuilderException::class);
        $this->expectExceptionMessageMatches('/Invalid value "nonsense"/');

        (new ArgumentsBuilder())->build(['phpcpd', '--config', $file, 'src']);
    }

    #[Test]
    public function no_config_ignores_the_file_entirely(): void
    {
        $file = $this->write("min-tokens = 42\n");

        $arguments = (new ArgumentsBuilder())->build(['phpcpd', '--config', $file, '--no-config', 'src']);

        self::assertSame(70, $arguments->tokensThreshold());
    }

    #[Test]
    public function a_project_file_is_found_from_the_scanned_path_not_the_working_directory(): void
    {
        $project = sys_get_temp_dir() . '/phpcpd-ini-' . uniqid();
        mkdir($project . '/src', 0o777, true);

        $ini = $project . '/' . ConfigFile::DEFAULT_NAME;
        file_put_contents($ini, "min-tokens = 33\n");

        $arguments = (new ArgumentsBuilder())->build(['phpcpd', $project . '/src']);

        unlink($ini);
        rmdir($project . '/src');
        rmdir($project);

        self::assertSame(33, $arguments->tokensThreshold());
    }

    private function write(string $contents): string
    {
        $file = sys_get_temp_dir() . '/phpcpd-' . uniqid() . '.ini';
        file_put_contents($file, $contents);

        $this->paths[] = $file;

        return $file;
    }
}
