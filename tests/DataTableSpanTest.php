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

use function array_values;

use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\UnifiedStrategy;
use LucianoPereira\PhpcpdNext\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The span discriminator, end to end through the engine — ruling H's one
 * surviving avenue.
 *
 * Two fixtures state the whole rule as a contrast, because the rule is only
 * worth anything if it separates them:
 *
 *   `self_repeating_table.php`   one table, matching itself. Not duplicated
 *                                logic: the second run of rows is not a copy
 *                                anyone could delete.
 *   `generated_table_a/b.php`    two tables, in two files, generated together.
 *                                A change to one has to be made to the other,
 *                                which is the rubric's definition of a clone —
 *                                and it is the shape (php-parser's `Php7.php`
 *                                and `Php8.php`) that refuted every span
 *                                *statistic* the M2 audit measured.
 */
#[CoversClass(UnifiedStrategy::class)]
final class DataTableSpanTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/fixtures/datatable';

    /** @param list<string> $files */
    private function clones(array $files): array
    {
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 70,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        return array_values((new Engine($config, 'unified'))->detect($files)->clones());
    }

    #[Test]
    public function a_table_matching_itself_is_not_reported(): void
    {
        self::assertSame([], $this->clones([self::FIXTURES . '/self_repeating_table.php']));
    }

    #[Test]
    public function two_generated_tables_in_two_files_are_still_reported(): void
    {
        // The counter-example. Suppressing this would be the exact failure the
        // three refuted discriminators shared.
        self::assertNotSame([], $this->clones([
            self::FIXTURES . '/generated_table_a.php',
            self::FIXTURES . '/generated_table_b.php',
        ]));
    }
}
