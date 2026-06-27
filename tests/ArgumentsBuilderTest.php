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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LucianoPereira\PhpcpdNext\ArgumentsBuilder;
use LucianoPereira\PhpcpdNext\ArgumentsBuilderException;

#[CoversClass(ArgumentsBuilder::class)]
final class ArgumentsBuilderTest extends TestCase
{
    #[Test]
    public function defaults_are_set_when_only_directory_is_given(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', 'src/']);

        self::assertSame(['src/'], $args->directories());
        self::assertSame(['.php'], $args->suffixes());
        self::assertSame([], $args->exclude());
        self::assertNull($args->pmdCpdXmlLogfile());
        self::assertSame(5, $args->linesThreshold());
        self::assertSame(70, $args->tokensThreshold());
        self::assertFalse($args->fuzzy());
        self::assertFalse($args->verbose());
        self::assertFalse($args->help());
        self::assertFalse($args->version());
        self::assertNull($args->algorithm()); // null = combined rk+tb default
        self::assertSame(5, $args->editDistance());
        self::assertSame(10, $args->headEquality());
        self::assertFalse($args->incremental());
    }

    #[Test]
    public function incremental_flag_enables_the_per_file_index(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--incremental', 'src/']);

        self::assertTrue($args->incremental());
    }

    #[Test]
    public function suffix_flag_appends_to_default(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--suffix', '.php5', 'src/']);

        self::assertSame(['.php', '.php5'], $args->suffixes());
    }

    #[Test]
    public function suffix_flag_can_be_given_multiple_times(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--suffix', '.php5', '--suffix', '.php7', 'src/']);

        self::assertSame(['.php', '.php5', '.php7'], $args->suffixes());
    }

    #[Test]
    public function exclude_flag_is_collected(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--exclude', 'vendor', '--exclude', 'tests', 'src/']);

        self::assertSame(['vendor', 'tests'], $args->exclude());
    }

    #[Test]
    public function min_lines_flag_overrides_default(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--min-lines', '10', 'src/']);

        self::assertSame(10, $args->linesThreshold());
    }

    #[Test]
    public function min_tokens_flag_overrides_default(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--min-tokens', '100', 'src/']);

        self::assertSame(100, $args->tokensThreshold());
    }

    #[Test]
    public function fuzzy_flag_enables_fuzzy_mode(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--fuzzy', 'src/']);

        self::assertTrue($args->fuzzy());
    }

    #[Test]
    public function verbose_flag_enables_verbose_mode(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--verbose', 'src/']);

        self::assertTrue($args->verbose());
    }

    #[Test]
    public function log_pmd_flag_sets_output_path(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--log-pmd', '/tmp/report.xml', 'src/']);

        self::assertSame('/tmp/report.xml', $args->pmdCpdXmlLogfile());
    }

    #[Test]
    public function log_json_flag_sets_output_path(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--log-json', '/tmp/report.json', 'src/']);

        self::assertSame('/tmp/report.json', $args->jsonLogfile());
    }

    #[Test]
    public function log_sarif_flag_sets_output_path(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--log-sarif', '/tmp/report.sarif', 'src/']);

        self::assertSame('/tmp/report.sarif', $args->sarifLogfile());
    }

    #[Test]
    public function report_logfiles_default_to_null_when_not_requested(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', 'src/']);

        self::assertNull($args->pmdCpdXmlLogfile());
        self::assertNull($args->jsonLogfile());
        self::assertNull($args->sarifLogfile());
    }

    #[Test]
    public function algorithm_flag_overrides_default(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--algorithm', 'suffixtree', 'src/']);

        self::assertSame('suffixtree', $args->algorithm());
    }

    #[Test]
    public function edit_distance_flag_overrides_default(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--edit-distance', '3', 'src/']);

        self::assertSame(3, $args->editDistance());
    }

    #[Test]
    public function head_equality_flag_overrides_default(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--head-equality', '5', 'src/']);

        self::assertSame(5, $args->headEquality());
    }

    #[Test]
    public function help_flag_does_not_require_a_directory(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--help']);

        self::assertTrue($args->help());
    }

    #[Test]
    public function version_flag_does_not_require_a_directory(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--version']);

        self::assertTrue($args->version());
    }

    #[Test]
    public function short_h_flag_sets_help(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '-h']);

        self::assertTrue($args->help());
    }

    #[Test]
    public function short_v_flag_sets_version(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '-v']);

        self::assertTrue($args->version());
    }

    #[Test]
    public function missing_directory_throws(): void
    {
        $this->expectException(ArgumentsBuilderException::class);
        $this->expectExceptionMessage('No directory specified');

        (new ArgumentsBuilder())->build(['phpcpd']);
    }

    #[Test]
    public function unknown_flag_throws(): void
    {
        $this->expectException(ArgumentsBuilderException::class);

        (new ArgumentsBuilder())->build(['phpcpd', '--no-such-flag', 'src/']);
    }

    #[Test]
    public function invalid_algorithm_value_is_rejected_early(): void
    {
        // New: the owned parser validates --algorithm against its allowed set,
        // failing fast at parse time instead of deep inside strategy selection.
        $this->expectException(ArgumentsBuilderException::class);
        $this->expectExceptionMessage('Invalid value "nonsense" for --algorithm');

        (new ArgumentsBuilder())->build(['phpcpd', '--algorithm', 'nonsense', 'src/']);
    }

    #[Test]
    public function option_value_can_be_given_with_equals_sign(): void
    {
        $args = (new ArgumentsBuilder())->build(['phpcpd', '--min-tokens=123', 'src/']);

        self::assertSame(123, $args->tokensThreshold());
    }

    #[Test]
    public function missing_value_for_an_option_throws(): void
    {
        $this->expectException(ArgumentsBuilderException::class);
        $this->expectExceptionMessage('requires a value');

        (new ArgumentsBuilder())->build(['phpcpd', 'src/', '--min-tokens']);
    }
}
