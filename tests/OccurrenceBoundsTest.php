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
use function count;
use function dirname;
use function file_get_contents;
use function glob;

use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * An occurrence may not claim tokens its file does not have.
 *
 * The matcher records where a run's *earlier* copy began by looking the window's
 * hash up in a table, and that entry belongs to whichever occurrence registered
 * the window first. In a file of repeated blocks it is routinely a later,
 * unrelated one — a hazard {@see DefaultStrategy::record()} has always described
 * and normalization is what makes it reachable, because turning a table's rows
 * into windows that hash alike is exactly what it does.
 *
 * The symptom is an occurrence whose token range runs off the end of its file.
 * In `tests/fixtures/strata` the entry points at token 49 where the matching
 * rows begin at 37 — one table row late — so a 229-token run from a 275-token
 * file ends at 277. Nothing crashes: the presentation layer then asks whether
 * tokens past the end of the file sit inside an array literal, is told no, and
 * the pair silently loses the `table` demotion that fixture exists to prove.
 *
 * Measured at `--min-tokens=30`, raw matching produces no such site on
 * php-parser or symfony-console; normalized matching produced one in 1,719 and
 * two in 2,872 before the anchor was withheld.
 */
#[CoversClass(DefaultStrategy::class)]
final class OccurrenceBoundsTest extends TestCase
{
    /**
     * No site may start past its file's tokens or extend beyond them.
     *
     * The strata fixtures are the case in hand, and the token floor matters: at
     * the shipped default of 100 the run is never recorded at all, so a test
     * pinned there would pass without the fix.
     */
    #[Test]
    public function noOccurrenceRunsPastTheEndOfItsFile(): void
    {
        $config = new StrategyConfiguration(
            minLines:      5,
            minTokens:     30,
            normalization:         Normalization::TypeAnchored,
            minSimilarity: 0.7,
        );

        $files = glob(dirname(__DIR__) . '/tests/fixtures/strata/*.php');

        self::assertNotEmpty($files, 'the fixtures must exist, or this test proves nothing');

        $clones  = (new Detector(new DefaultStrategy($config)))->copyPasteDetection($files)->clones();
        $lengths = [];
        $checked = 0;

        self::assertNotEmpty($clones, 'the fixtures hold real clones and should still be found');

        foreach ($clones as $clone) {
            foreach (array_values($clone->files()) as $site) {
                if ($site->startToken === null) {
                    // The anchor was withheld precisely because it could not be
                    // trusted; the occurrence falls back to its lines.
                    continue;
                }

                $tokens = $lengths[$site->name] ??= count(
                    (new DefaultStrategy($config))->tokenize((string) file_get_contents($site->name))->tokenRealLines,
                );

                ++$checked;

                self::assertLessThanOrEqual(
                    $tokens,
                    $site->startToken + $clone->tokensOf($site),
                    "{$site->name} has {$tokens} tokens but a site claims "
                    . "{$clone->tokensOf($site)} of them from index {$site->startToken}",
                );
            }
        }

        self::assertGreaterThan(0, $checked, 'no anchored site was examined, so nothing was checked');
    }
}
