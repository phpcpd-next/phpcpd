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

use function file_get_contents;
use function intdiv;
use function strlen;

use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Facts\RegionStructure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The facts layer: what a region of a file *is*.
 *
 * Two things are pinned here, and the first is the load-bearing one. The class
 * describes positions in **significant-token indices** — the encoder's own
 * numbering — and it reproduces that numbering from its own tokenizer pass
 * rather than from the encoder. An off-by-one would not fail loudly; it would
 * quietly move every span in the file by one token and give confident, wrong
 * answers. So the alignment is asserted directly, over real source rather than
 * a handful of snippets.
 */
#[CoversClass(RegionStructure::class)]
final class RegionStructureTest extends TestCase
{
    use ReadsEveryShippedSource;

    #[Test]
    #[DataProvider('sourceFiles')]
    public function itNumbersTokensExactlyAsTheEncoderDoes(string $file): void
    {
        $source = file_get_contents($file);
        self::assertIsString($source);

        $expected = intdiv(strlen(self::encoder()->tokenize($source)->signature), FileTokens::TOKEN_BYTES);

        self::assertSame($expected, RegionStructure::fromSource($source)->tokenCount(), $file);
    }

    #[Test]
    public function twoRunsOfOneLiteralTableAreOneTable(): void
    {
        $structure = RegionStructure::fromSource(self::TABLE);

        // Rows one and three of the same literal, whole. The signature counts
        // every token now, punctuation included, so a row is ten of them:
        // `[ 'IT' , 39 , true , 'first' ] ,`
        self::assertSame(44, $structure->tokenCount());
        self::assertTrue($structure->sameLiteralTable(2, 10, 22, 10));
    }

    #[Test]
    public function aRowThatComputesItsDefaultIsStillARow(): void
    {
        // Ruling 5, the positive half. This is the shape of a framework config
        // file: records whose values are leaf calls with literal arguments. Under
        // the struck "no computation token" reading it was not a table and its
        // self-matches were reported; both raters call it one, and the granted
        // rule agrees with them because nothing here is a *statement*.
        //
        // The paired negative below is the same file with closures in place of
        // the calls, at the same token indices, so the two tests differ in
        // exactly the thing the ruling names.
        $structure = RegionStructure::fromSource(self::COMPUTED_DEFAULTS);

        self::assertTrue($structure->sameLiteralTable(1, 10, 11, 10));
    }

    #[Test]
    public function oneClosureInsideTheTableIsEnoughToDisqualifyIt(): void
    {
        // Ruling 5, the negative half, and the boundary it pins: an array whose
        // elements carry statements is logic-bearing and is never silenced. A
        // second copy of a closure body is a second copy of behaviour, which is
        // the rubric's own definition of duplication.
        //
        // The disqualification has to reach the frames *enclosing* the closure,
        // not only the row holding it — the ranges asserted here span the outer
        // table, and the closures sit two frames down.
        $structure = RegionStructure::fromSource(self::CLOSURE_DEFAULTS);

        self::assertSame(
            $structure->tokenCount(),
            RegionStructure::fromSource(self::COMPUTED_DEFAULTS)->tokenCount(),
            // Three arguments, so the call is as many tokens as the closure it
            // is paired against: `function () { return 'mysql'; }` is eight,
            // and `env('X', 'mysql')` was six. The pairing is the control for
            // this test and only works while the two are the same size.
            'the paired fixtures must differ only in closure-vs-call, not in shape',
        );
        // Two whole outer entries — `'first' => [ … ] ,` at 2 and `'second'`
        // at 21, nineteen tokens each. Each holds a closure two frames down,
        // and one closure anywhere inside disqualifies the run.
        self::assertFalse($structure->sameLiteralTable(2, 19, 21, 19));
    }

    #[Test]
    public function aSubscriptDoesNotOpenATable(): void
    {
        // `$table[0]` opens a bracket that is not an array literal. If it were
        // read as one, every frame after it would be misnumbered — so the table
        // above it is asserted to still be found, and found whole.
        $structure = RegionStructure::fromSource(self::TABLE_THEN_SUBSCRIPT);

        // Rows one and two of the table, whole. The subscript `$table[0]`
        // after it is tokens 45-51 and must not join them into one run.
        self::assertSame(52, $structure->tokenCount());
        self::assertTrue($structure->sameLiteralTable(3, 10, 13, 10));
    }

    #[Test]
    public function twoDuplicatedMethodsAreNotATableEvenBesideOne(): void
    {
        // One file, both shapes. The duplicated methods are the finding the tool
        // exists to report and must survive; the table beside them is the finding
        // it exists to stop reporting. A rule that cannot tell them apart in the
        // same file cannot be trusted across files either.
        $structure = RegionStructure::fromSource(self::TABLE_AND_METHODS);

        // Rows one and two of the constant, then the two duplicated methods —
        // `first()` at 52 and `second()` at 74, twenty-two tokens each.
        self::assertTrue($structure->sameLiteralTable(10, 10, 20, 10), 'the table is a table');
        self::assertFalse($structure->sameLiteralTable(52, 22, 74, 22), 'the methods are not');
    }

    #[Test]
    public function twoDifferentTablesAreNotOneTable(): void
    {
        // The php-parser counter-example in miniature. Two generated parser
        // tables are pure literal arrays *and* a genuine clone — a grammar change
        // regenerates both — so identity of the frame, not literalness, has to be
        // what decides. This is the case that killed the three refuted
        // discriminators, and it is the one this rule must not silence.
        $structure = RegionStructure::fromSource(<<<'PHP'
            <?php

            final class Tables
            {
                public const array FIRST = [1, 2, 3, 4, 5, 6, 7, 8];

                public const array SECOND = [1, 2, 3, 4, 5, 6, 7, 8];
            }
            PHP);

        self::assertFalse($structure->sameLiteralTable(7, 4, 19, 4));
    }

    private const string COMPUTED_DEFAULTS = <<<'PHP'
        <?php

        return [
            'first'  => ['driver' => env('FIRST_DRIVER', 'mysql', true),  'strict' => true],
            'second' => ['driver' => env('SECOND_DRIVER', 'mysql', true), 'strict' => true],
            'third'  => ['driver' => env('THIRD_DRIVER', 'mysql', true),  'strict' => true],
        ];
        PHP;

    private const string CLOSURE_DEFAULTS = <<<'PHP'
        <?php

        return [
            'first'  => ['driver' => function () { return 'mysql'; }, 'strict' => true],
            'second' => ['driver' => function () { return 'mysql'; }, 'strict' => true],
            'third'  => ['driver' => function () { return 'mysql'; }, 'strict' => true],
        ];
        PHP;

    private const string TABLE = <<<'PHP'
        <?php

        return [
            ['IT', 39, true, 'first'],
            ['AT', 43, true, 'second'],
            ['BE', 32, true, 'third'],
            ['CH', 41, true, 'fourth'],
        ];
        PHP;

    private const string TABLE_THEN_SUBSCRIPT = <<<'PHP'
        <?php

        $table = [
            ['IT', 39, true, 'first'],
            ['AT', 43, true, 'second'],
            ['BE', 32, true, 'third'],
            ['CH', 41, true, 'fourth'],
        ];

        $first = $table[0];
        PHP;

    private const string TABLE_AND_METHODS = <<<'PHP'
        <?php

        final class Both
        {
            public const array ROWS = [
                ['IT', 39, true, 'first'],
                ['AT', 43, true, 'second'],
                ['BE', 32, true, 'third'],
                ['CH', 41, true, 'fourth'],
            ];

            public function first(int $value): int
            {
                $doubled = $value * 2;

                return $doubled + 1;
            }

            public function second(int $value): int
            {
                $doubled = $value * 2;

                return $doubled + 1;
            }
        }
        PHP;
}
