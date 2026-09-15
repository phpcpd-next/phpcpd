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

use function glob;

use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;

/**
 * Every file the project ships, as a data provider, plus the encoder to read
 * them with.
 *
 * The facts layer reproduces the encoder's own token numbering from its own
 * tokenizer pass, and an off-by-one there would not fail loudly — it would move
 * every span in a file by one token and give confident, wrong answers. So the
 * alignment is asserted over the whole of `src/` rather than a few snippets,
 * and by more than one test class. The corpus and the encoder settings are the
 * same question for all of them, asked here once: two copies of "which files,
 * tokenized how" are two chances for one of them to drift.
 */
trait ReadsEveryShippedSource
{
    private static function encoder(): DefaultStrategy
    {
        return new DefaultStrategy(new StrategyConfiguration(5, 70, Normalization::Raw, 1.0));
    }

    /** @return list<array{0: string}> */
    public static function sourceFiles(): array
    {
        $files = [];

        foreach ([__DIR__ . '/../src/*.php', __DIR__ . '/../src/*/*.php', __DIR__ . '/../src/*/*/*.php'] as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $files[] = [$file];
            }
        }

        return $files;
    }
}
