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

use function count;
use function crc32;
use function dirname;
use function file_get_contents;

use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Two different literals the Rabin–Karp matcher cannot tell apart.
 *
 * Its per-token signature is five bytes: one for the token's type, four for
 * `crc32()` of its text. Four bytes is 32 bits, so distinct texts share a value
 * often enough to happen in ordinary code rather than only in a search for it —
 * across WordPress's 137,684 distinct token texts there are two such pairs, and
 * the pair used here is one of them. Neither literal was chosen by hunting for
 * a collision; both were found by scanning the corpus.
 *
 * A window whose only difference is such a token therefore hashes identically,
 * and the matcher never checks: `isset($this->hashes[$hash])` is the whole of
 * its verification. So two files that differ are reported as an **exact**
 * clone — not a near-miss, not gapped, but an identical copy.
 *
 * The signature is eight bytes per token now — one `xxh64` over the token's type
 * and its text together — so the two literals below no longer look alike, and a
 * rolling window hash pays for the extra width by not re-reading the window at
 * every position.
 *
 * What these tests pin is the shape of the answer rather than its absence. The
 * two fixtures really do share their tail: everything after the literal is
 * identical, and reporting *that* is correct. The defect was that the reported
 * clone began before the line they differ on, claiming code that differs was an
 * identical copy.
 */
#[CoversClass(DefaultStrategy::class)]
final class SignatureCollisionTest extends TestCase
{
    private const string FIXTURES = '/tests/fixtures/collision';

    /**
     * The fact underneath everything else, and the one part of this that no fix
     * can change: crc32 is 32 bits, and these two strings share a value.
     */
    #[Test]
    public function twoRealWordpressLiteralsShareOneCrc32(): void
    {
        // With their quotes: `$token[1]` for a string literal is the literal as
        // written, and that is what the signature hashes.
        self::assertSame(crc32("'wp_scrape_nonce'"), crc32("'Antananarivo'"));
    }

    /** The line the two fixtures disagree on. */
    private const int DIFFERING_LINE = 11;

    /**
     * No clone may cover the line the files differ on.
     *
     * Before the signature was widened this reported one clone of ten lines
     * starting at line 2 — straight through the difference, and flagged exact,
     * so nothing downstream could tell it from a real copy. It now reports the
     * shared tail from line 13, which is a true clone.
     */
    #[Test]
    public function noReportedCloneCoversTheLineTheFilesDifferOn(): void
    {
        $root  = dirname(__DIR__) . self::FIXTURES;
        $files = [$root . '/handler_a.php', $root . '/handler_b.php'];

        self::assertNotSame(
            file_get_contents($files[0]),
            file_get_contents($files[1]),
            'the fixtures must differ, or this test proves nothing',
        );

        $clones = (new Detector(new DefaultStrategy(
            new StrategyConfiguration(
                minLines: 3,
                minTokens: 20,
                normalization: Normalization::Raw,
                minSimilarity: 0.7,
            ),
        )))->copyPasteDetection($files)->clones();

        self::assertNotEmpty($clones, 'the shared tail is a real clone and should still be found');

        // No clone may *bridge* the difference — reach from before line 11 to
        // after it. Two do reach onto line 11 and both are right to: the line
        // is `$key = 'wp_scrape_nonce';` and only the literal differs, so
        // `$key` and `=` are shared and a run may legitimately stop or start
        // between them. Reported as a prefix and a tail now, where the blind
        // matcher left both under the token floor.
        //
        // Bridging is the thing the collision caused and the thing that must
        // not come back: one clone covering line 10 through line 12 says the
        // two literals are the same text.
        foreach ($clones as $clone) {
            foreach ($clone->files() as $occurrence) {
                $last = $occurrence->lastLine($clone->numberOfLines());

                self::assertFalse(
                    $occurrence->startLine < self::DIFFERING_LINE && $last > self::DIFFERING_LINE,
                    'a clone bridging the differing line claims that differing code is identical',
                );
            }
        }
    }

    /**
     * The control: one file named twice is one occurrence, not two, so the
     * assertions above are about the literals and not about the harness.
     */
    #[Test]
    public function oneFileListedTwiceIsNotADuplicate(): void
    {
        $root  = dirname(__DIR__) . self::FIXTURES;
        $files = [$root . '/handler_a.php', $root . '/handler_a.php'];

        // The same file twice is the control: one identical pair, so the count
        // above is about the literals and not about the harness.
        $clones = (new Detector(new DefaultStrategy(
            new StrategyConfiguration(
                minLines: 3,
                minTokens: 20,
                normalization: Normalization::Raw,
                minSimilarity: 0.7,
            ),
        )))->copyPasteDetection($files)->clones();

        self::assertCount(0, $clones, 'one file listed twice is not two occurrences');
    }
}
