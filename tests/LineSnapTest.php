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

use LucianoPereira\PhpcpdNext\Facts\FileFacts;
use LucianoPereira\PhpcpdNext\Presentation\LineSnap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A matched token run, reported as whole source lines.
 *
 * The rule is inward: never claim a line the match only partly covers. What
 * that buys, beyond an honest extent, is that two runs abutting on one line
 * stop sharing it — the residue NEXT-TASKS item 2 measures, and the case the
 * last test here pins.
 */
#[CoversClass(LineSnap::class)]
final class LineSnapTest extends TestCase
{
    /** `$a = 1; $b = 2;` — two statements on one line, so a run inside it covers no whole line. */
    #[Test]
    public function aRunInsideOneLineHasNoWholeLineToReport(): void
    {
        $facts = FileFacts::fromSource("<?php\n\$a = 1; \$b = 2;\n");

        // Tokens 4..7 are `$b = 2 ;` — real code, wholly inside line 2, which
        // also holds `$a = 1 ;`. There is no whole line here to snap to.
        self::assertNull(LineSnap::of($facts, [4, 4]));
    }

    /** A run already sitting on whole lines is left exactly where it is. */
    #[Test]
    public function aWholeLineRunIsUnchanged(): void
    {
        $facts = FileFacts::fromSource("<?php\n\$a = 1;\n\$b = 2;\n\$c = 3;\n");

        // `$a = 1 ;` is tokens 0..3 and occupies line 2 alone.
        self::assertSame([2, 2], LineSnap::of($facts, [0, 4]));
    }

    /** A run starting mid-line reports from the next line, not from the one it shares. */
    #[Test]
    public function aRunStartingMidLineBeginsAtTheNextOne(): void
    {
        $facts = FileFacts::fromSource("<?php\n\$a = 1; \$b = 2;\n\$c = 3;\n");

        // Start at `$b` (token 4), run through `$c = 3 ;`. Line 2 is shared
        // with `$a = 1;`, so the report starts at line 3.
        self::assertSame([3, 3], LineSnap::of($facts, [4, 8]));
    }

    /**
     * Two token-disjoint runs that meet on one line do not both claim it.
     *
     * This is NEXT-TASKS item 2's one-line residue, which is the whole of that
     * residue on php-parser, symfony-console and symfony-string. Snapping
     * outward would leave both runs naming the shared line; inward sends one
     * above it and the other below.
     */
    #[Test]
    public function abuttingRunsStopSharingTheirBoundaryLine(): void
    {
        //      line 2   line 3            line 4
        $facts = FileFacts::fromSource("<?php\n\$a = 1;\n\$b = 2; \$c = 3;\n\$d = 4;\n");

        // First run: `$a = 1 ;` and `$b = 2 ;` — tokens 0..7, ending mid-line 3.
        $first = LineSnap::of($facts, [0, 8]);
        // Second run: `$c = 3 ;` and `$d = 4 ;` — tokens 8..15, starting mid-line 3.
        $second = LineSnap::of($facts, [8, 8]);

        self::assertSame([2, 2], $first);
        self::assertSame([4, 4], $second);
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertLessThan($second[0], $first[1], 'the two reported ranges still overlap');
    }
}
