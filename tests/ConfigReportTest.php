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
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\ArgumentsBuilder;
use LucianoPereira\PhpcpdNext\ConfigReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `--show-config` answers "why is it doing that?". A layered configuration is
 * only trustworthy if the layer that produced each value can be named, since a
 * setting no layer mentions keeps a built-in default that appears in no file.
 */
#[CoversClass(ConfigReport::class)]
final class ConfigReportTest extends TestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ($this->file !== '') {
            @unlink($this->file);
            $this->file = '';
        }
    }

    #[Test]
    public function an_untouched_setting_is_marked_as_a_fallback(): void
    {
        self::assertMatchesRegularExpression('/min-lines\s+5\s+\(\*\) default/', $this->render(['phpcpd', '--no-config', 'src']));
    }

    #[Test]
    public function a_setting_from_the_command_line_names_the_command_line(): void
    {
        $report = $this->render(['phpcpd', '--no-config', '--min-tokens', '99', 'src']);

        self::assertMatchesRegularExpression('/min-tokens\s+99\s+command line/', $report);
    }

    #[Test]
    public function a_setting_from_a_file_names_the_file_layer(): void
    {
        $this->file = sys_get_temp_dir() . '/phpcpd-' . uniqid() . '.ini';
        file_put_contents($this->file, "min-tokens = 42\n");

        $report = $this->render(['phpcpd', '--config', $this->file, 'src']);

        self::assertMatchesRegularExpression('/min-tokens\s+42\s+--config/', $report);
        self::assertStringContainsString($this->file, $report);
    }

    #[Test]
    public function the_command_line_is_reported_as_the_winner_over_a_file(): void
    {
        $this->file = sys_get_temp_dir() . '/phpcpd-' . uniqid() . '.ini';
        file_put_contents($this->file, "min-tokens = 42\n");

        $report = $this->render(['phpcpd', '--config', $this->file, '--min-tokens', '99', 'src']);

        self::assertMatchesRegularExpression('/min-tokens\s+99\s+command line/', $report);
    }

    #[Test]
    public function a_repeatable_setting_names_every_layer_that_contributed(): void
    {
        $this->file = sys_get_temp_dir() . '/phpcpd-' . uniqid() . '.ini';
        file_put_contents($this->file, "exclude[] = build\n");

        $report = $this->render(['phpcpd', '--config', $this->file, '--exclude', 'extra', 'src']);

        self::assertMatchesRegularExpression('/exclude\s+build, extra\s+--config \+ command line/', $report);
    }

    #[Test]
    public function research_flags_stay_out_of_the_table_until_they_are_set(): void
    {
        self::assertStringNotContainsString('edit-distance', $this->render(['phpcpd', '--no-config', 'src']));
        self::assertStringContainsString('edit-distance', $this->render(['phpcpd', '--no-config', '--edit-distance', '7', 'src']));
    }

    #[Test]
    public function show_config_does_not_require_a_directory(): void
    {
        self::assertStringContainsString('SETTING', $this->render(['phpcpd', '--no-config', '--show-config']));
    }

    /** @param list<string> $argv */
    private function render(array $argv): string
    {
        return ConfigReport::render($argv, (new ArgumentsBuilder())->build($argv));
    }
}
