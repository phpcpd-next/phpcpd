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

namespace LucianoPereira\PhpcpdNext\Tests\Regression;

use LucianoPereira\PhpcpdNext\Facts\RegionStructure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A function body is what its braces contain — neither of them.
 *
 * The range used to start at the opening brace and stop before the closing
 * one, which is asymmetric and contradicts what the facts layer promises:
 * *"bodies only — a signature is shared vocabulary and carries no evidence
 * about behaviour"*. A brace is not evidence about behaviour either, and there
 * is no reading on which one belongs and the other does not.
 *
 * It stayed invisible until a reported extent was compared against it. A match
 * runs from the first token *inside* the body, so every site in every corpus
 * sat exactly one token after the recorded start — and the boundary-alignment
 * study in NEXT-TASKS item 1 read that as total drift, reporting "0% of sites
 * start at a body start" on php-parser. Against the corrected boundary that is
 * 20%, and on symfony-console it is 60%.
 */
#[CoversClass(RegionStructure::class)]
final class FunctionBodyExcludesItsBracesTest extends TestCase
{
    #[Test]
    public function aBodyRunsFromTheFirstTokenInsideToTheLast(): void
    {
        // Significant tokens, indexed:
        //   0 function  1 f  2 (  3 int  4 $a  5 )  6 :  7 int  8 {
        //   9 $b  10 =  11 $a  12 +  13 1  14 ;  15 return  16 $b  17 ;  18 }
        $source = "<?php\nfunction f(int \$a): int\n{\n    \$b = \$a + 1;\n\n    return \$b;\n}\n";

        self::assertSame(
            [[9, 9]],
            RegionStructure::fromSource($source)->functions(),
            'the body must start after `{` and end before `}`',
        );
    }

    /** An empty body contains nothing, so there is no unit to report. */
    #[Test]
    public function anEmptyBodyIsNotAUnit(): void
    {
        self::assertSame([], RegionStructure::fromSource("<?php\nfunction f(): void\n{\n}\n")->functions());
    }

    /**
     * The signature stays out, however long it is.
     *
     * A promoted-property constructor puts a great deal of shared vocabulary
     * between `function` and `{`, which is exactly the material the facts layer
     * excludes on purpose.
     */
    #[Test]
    public function aLongSignatureIsNotPartOfTheBody(): void
    {
        $source = "<?php\nclass C\n{\n    public function __construct(\n        private int \$a,\n        private string \$b,\n    ) {\n        \$this->c = 1;\n    }\n}\n";

        $functions = RegionStructure::fromSource($source)->functions();

        self::assertCount(1, $functions);

        [$first, $length] = $functions[0];

        // `$this -> c = 1 ;` — six significant tokens, and not one of the
        // signature's.
        self::assertSame(6, $length);
        self::assertGreaterThan(0, $first);
    }
}
