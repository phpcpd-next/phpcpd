<?php

declare(strict_types=1);
/*
 * This file is part of PhpcpdNext.
 *
 * (c) 2026 Luciano Federico Pereira.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LucianoPereira\PhpcpdNext\Tests;

use function count;
use function str_replace;
use function strlen;

use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\ShingleBags;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ruling R's bag machinery, at the two places it can be wrong in ways a corpus
 * would not obviously show.
 *
 * Both tests below are regression pins for defects that actually happened while
 * this was built, not hypotheticals — which is why they are stated as properties
 * rather than as example outputs.
 */
#[CoversClass(ShingleBags::class)]
final class ShingleBagsTest extends TestCase
{
    private static function signature(string $php): string
    {
        return (new DefaultStrategy(new StrategyConfiguration(5, 70, Normalization::Raw, 1.0)))
            ->tokenize($php)->signature;
    }

    #[Test]
    public function coverage_is_bijective_so_a_repetitive_span_cannot_cover_a_short_one(): void
    {
        // The M2 lesson that killed one-sided coverage: every shingle of the
        // short bag is present in the long one, many times over, and a one-sided
        // measure scores that 1.0. Matching occurrences one-to-one scores it by
        // what can actually be paired.
        $short = [1, 2, 3];
        $long  = [1, 2, 3, 1, 2, 3, 1, 2, 3, 1, 2, 3];

        self::assertSame(0.25, ShingleBags::coverage($short, $long));
    }

    #[Test]
    public function an_insertion_displaces_nothing_and_a_swap_displaces_something(): void
    {
        // The distinction the whole reorder verdict rests on. An insertion
        // shifts every later run to a new offset without changing any run's
        // *order*, so an offset-based rule calls it a reorder and an order-based
        // one does not. A swap changes the order, and only that is a reorder.
        $base = <<<'PHP'
            <?php
            function compute(array $data): array
            {
                $alpha = $data['alpha'];
                $bravo = $data['bravo'];
                $charlie = $data['charlie'];
                $delta = $alpha + $bravo;
                $echo = $bravo + $charlie;
                $foxtrot = $charlie + $alpha;
                return [$delta, $echo, $foxtrot];
            }
            PHP;

        $inserted = str_replace(
            '    $delta = $alpha + $bravo;',
            "    \$extra = \$data['extra'];\n    \$delta = \$alpha + \$bravo;",
            $base,
        );

        $swapped = str_replace(
            "    \$delta = \$alpha + \$bravo;\n    \$echo = \$bravo + \$charlie;",
            "    \$echo = \$bravo + \$charlie;\n    \$delta = \$alpha + \$bravo;",
            $base,
        );

        $of = static function (string $php): array {
            $signature = self::signature($php);

            return ShingleBags::shingles($signature, 0, (int) (strlen($signature) / 5));
        };

        self::assertSame([], ShingleBags::displacedRuns($of($base), $of($inserted)), 'an insertion is not a reorder');
        self::assertNotSame([], ShingleBags::displacedRuns($of($base), $of($swapped)), 'a swap is');
    }

    #[Test]
    public function the_prefix_filter_keeps_every_pair_it_could_have_kept(): void
    {
        // The filter's whole justification is that it loses nothing: two bags
        // whose overlap reaches theta must share a prefix element. Asserted
        // against the exhaustive answer rather than against a fixed expectation,
        // so the property is what is pinned.
        $units = [];

        foreach ([
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
            [1, 2, 3, 4, 5, 6, 7, 8, 9, 11],   // 0.9 against the first
            [50, 51, 52, 53, 54, 55, 56, 57],  // nothing in common
            [1, 2, 3, 60, 61, 62, 63, 64, 65, 66],
        ] as $hashes) {
            $units[] = [
                'file'     => 0,
                'start'    => 0,
                'length'   => 0,
                'hashes'   => $hashes,
                'distinct' => $hashes,
                'shingles' => [],
            ];
        }

        $expected = [];

        for ($i = 0; $i < count($units); $i++) {
            for ($j = $i + 1; $j < count($units); $j++) {
                if (ShingleBags::coverage($units[$i]['hashes'], $units[$j]['hashes']) >= 0.7) {
                    $expected[] = [$i, $j];
                }
            }
        }

        $survivors = [];

        foreach (ShingleBags::candidatePairs($units, 0.7) as $pair) {
            $survivors[] = $pair;
        }

        foreach ($expected as $pair) {
            self::assertContains($pair, $survivors, 'the prefix filter dropped a pair that clears theta');
        }
    }
}
