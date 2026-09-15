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
use function file_get_contents;
use function intdiv;
use function strlen;

use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Facts\FileRole;
use LucianoPereira\PhpcpdNext\Facts\FileStatements;
use LucianoPereira\PhpcpdNext\Facts\RegionStructure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The second half of the facts layer: what a file is made of, and what it is.
 *
 * Three things are pinned here.
 *
 * The **numbering** is load-bearing in exactly the way {@see RegionStructureTest}
 * describes: this class reproduces the encoder's significant-token indices from
 * its own tokenizer pass, and an off-by-one would not fail loudly, it would move
 * every span by one token and answer confidently. So the alignment is asserted
 * against the encoder *and* against `RegionStructure` over the whole of `src/`.
 *
 * The **segmentation** is asserted total: every significant token belongs to
 * exactly one statement. A partial segmentation would make "a majority of the
 * top-level statements" a fraction of an unknown denominator.
 *
 * The **paired negatives** are the M5 charter's, and they are here because the
 * charter requires the registration role to ship with its negative *built before
 * the role is measured*. Each pair differs only in the thing the definition
 * names, which is the shape `RegionStructureTest`'s ruling-5 fixtures fixed for
 * this project.
 */
#[CoversClass(FileStatements::class)]
#[CoversClass(FileRole::class)]
final class FileStatementsTest extends TestCase
{
    use ReadsEveryShippedSource;

    /** A route table: registration expressions, no shared variable. */
    private const string ROUTES = <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace App;
        use Illuminate\Support\Facades\Route;
        Route::get('/a', [AController::class, 'index']);
        Route::get('/b', [BController::class, 'index']);
        Route::post('/c', [CController::class, 'store']);
        PHP;

    /**
     * The paired negative for the registration role: the same three
     * registrations written against one shared receiver. Dataflow-coupled, so it
     * is a procedure rather than a table, and it stays asserted.
     */
    private const string COUPLED = <<<'PHP'
        <?php
        declare(strict_types=1);
        namespace App;
        use Illuminate\Support\Facades\Route;
        $router->get('/a', [AController::class, 'index']);
        $router->get('/b', [BController::class, 'index']);
        $router->post('/c', [CController::class, 'store']);
        PHP;

    /**
     * The second paired negative: a returned config table has one top-level
     * statement and it is not a call, so the role declines to speak. A returned
     * table is stratum D1's business, and the two strata do not cover for each
     * other.
     */
    private const string RETURNS_TABLE = <<<'PHP'
        <?php
        declare(strict_types=1);
        return [
            'it' => ['code' => 'IT', 'dial' => 39],
            'jp' => ['code' => 'JP', 'dial' => 81],
        ];
        PHP;

    /**
     * A route file with grouping wrappers: three plain registrations and one
     * `Route::group(…, function () { … })`.
     *
     * The wrapper used to be counted as a statement and refused as a
     * registration, and the majority carried the file anyway — three of four.
     * That reading held for as long as a route file had a majority of ungrouped
     * calls, and a file written entirely with wrappers has none: firefly-iii's
     * `routes/web.php` is sixty groups of sixty-one statements and scored zero.
     * The wrapper is now read as what it is, one call taking a closure as an
     * argument, so this file is four of four.
     */
    private const string GROUPED = <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        Route::get('/a', [AController::class, 'index']);
        Route::get('/b', [BController::class, 'index']);
        Route::get('/c', [CController::class, 'index']);
        Route::group(['prefix' => 'admin'], function () {
            Route::get('/d', [DController::class, 'index']);
        });
        PHP;

    #[Test]
    #[DataProvider('sourceFiles')]
    public function itNumbersTokensExactlyAsTheEncoderAndTheRegionLayerDo(string $file): void
    {
        $source = file_get_contents($file);
        self::assertIsString($source);

        $expected = intdiv(strlen(self::encoder()->tokenize($source)->signature), FileTokens::TOKEN_BYTES);

        self::assertSame($expected, FileStatements::fromSource($source)->tokenCount(), $file);
        self::assertSame($expected, RegionStructure::fromSource($source)->tokenCount(), $file);
    }

    #[Test]
    #[DataProvider('sourceFiles')]
    public function itsSegmentationIsTotal(string $file): void
    {
        $source = file_get_contents($file);
        self::assertIsString($source);

        $facts = FileStatements::fromSource($source);

        for ($index = 0; $index < $facts->tokenCount(); $index++) {
            self::assertNotNull($facts->at($index), $file . ' token ' . $index);
        }

        self::assertNull($facts->at($facts->tokenCount()));
    }

    #[Test]
    public function aRouteTableIsRegistrationRole(): void
    {
        $role = FileRole::of(FileStatements::fromSource(self::ROUTES));

        self::assertTrue($role->registration);
        self::assertSame(3, $role->statements);
        self::assertSame(3, $role->registrations);
        self::assertFalse($role->coupled);
    }

    #[Test]
    public function theSameRegistrationsOverOneSharedReceiverAreNot(): void
    {
        $role = FileRole::of(FileStatements::fromSource(self::COUPLED));

        self::assertFalse($role->registration);
        self::assertTrue($role->coupled);
        self::assertSame(3, $role->registrations);
    }

    #[Test]
    public function thePairedNegativeDiffersOnlyInTheThingTheDefinitionNames(): void
    {
        // Equal significant-token counts, so the two halves differ in the shared
        // receiver and in nothing else.
        self::assertSame(
            FileStatements::fromSource(self::ROUTES)->tokenCount(),
            FileStatements::fromSource(self::COUPLED)->tokenCount(),
        );
    }

    #[Test]
    public function aReturnedConfigTableIsNotRegistrationRole(): void
    {
        $role = FileRole::of(FileStatements::fromSource(self::RETURNS_TABLE));

        self::assertFalse($role->registration);
        self::assertSame(1, $role->statements);
        self::assertSame(0, $role->registrations);
    }

    #[Test]
    public function aGroupingWrapperIsItselfARegistration(): void
    {
        $role = FileRole::of(FileStatements::fromSource(self::GROUPED));

        self::assertTrue($role->registration);
        self::assertSame(4, $role->statements);
        self::assertSame(4, $role->registrations);
    }

    /**
     * The position is what decides. A brace in expression position is a closure
     * passed as an argument; a brace opening a block is a block, and its holder
     * is not a call however the statement begins.
     */
    #[Test]
    public function aRealBlockIsStillNotARegistration(): void
    {
        $facts = FileStatements::fromSource("<?php\nif (ready()) {\n    go();\n}\n");
        $top   = $facts->topLevel();

        self::assertNotSame([], $top);
        self::assertFalse($top[0]->singleCall);
    }

    /**
     * A closure argument's parameters are its own, not the file's. Counting them
     * as the holder's dataflow made 135 independent breadcrumb registrations
     * read as one coupled procedure, because every one of them names the
     * `$breadcrumbs` its callback receives.
     */
    #[Test]
    public function aClosureArgumentsParametersAreNotTheStatementsDataflow(): void
    {
        $source = "<?php\n"
            . "Breadcrumbs::for('a', static function (Generator \$b): void { \$b->push('x'); });\n"
            . "Breadcrumbs::for('c', static function (Generator \$b): void { \$b->push('y'); });\n";

        $role = FileRole::of(FileStatements::fromSource($source));

        self::assertFalse($role->coupled, 'two callbacks naming their own parameter are not coupled');
        self::assertTrue($role->registration);
    }

    /**
     * The second clause: a call counts as a registration only when the closures
     * it receives hold registrations too. A grouping wrapper around a route
     * list is one; a wrapper around an assignment, a branch or a loop is not,
     * and nothing but the body decides it.
     */
    #[Test]
    public function aWrapperAroundRealLogicIsNotARegistration(): void
    {
        $list = "<?php\n"
            . "Route::group([], static function (): void { Route::get('a', 'A@a'); });\n"
            . "Route::group([], static function (): void { Route::get('b', 'B@b'); });\n";

        $logic = "<?php\n"
            . "Route::group([], static function (): void { \$u = user(); if (\$u) { Route::get('a', 'A@a'); } });\n"
            . "Route::group([], static function (): void { foreach (\$xs as \$x) { Route::get(\$x, 'B@b'); } });\n";

        self::assertTrue(FileRole::of(FileStatements::fromSource($list))->registration);
        self::assertFalse(FileRole::of(FileStatements::fromSource($logic))->registration);
    }

    /** And it reads through nesting, because a group of groups is still a group. */
    #[Test]
    public function theSecondClauseReadsThroughNestedWrappers(): void
    {
        $source = "<?php\n"
            . "Route::group([], static function (): void {\n"
            . "    Route::group([], static function (): void { Route::get('a', 'A@a'); });\n"
            . "});\n"
            . "Route::get('q', 'Q@q');\n";

        $role = FileRole::of(FileStatements::fromSource($source));

        self::assertTrue($role->registration);
        self::assertSame(2, $role->registrations);
    }

    /**
     * A table can be written as statements, and this is the element of one.
     * Anything computed on the right — a call, a variable, a constant — is code
     * rather than data, and the scope names data.
     */
    #[Test]
    public function aLiteralAppendIsAnElementOfATableWrittenAsStatements(): void
    {
        $facts = FileStatements::fromSource(
            "<?php\n\$rows[] = ['a' => 1, 'b' => 'two'];\n\$rows[] = compute();\n\$rows[] = ['c' => \$x];\n\$n = 1;\n",
        );
        $top   = $facts->all();

        self::assertTrue($top[0]->literalAppend, 'literal rows are an append');
        self::assertFalse($top[1]->literalAppend, 'a call is not data');
        self::assertFalse($top[2]->literalAppend, 'a variable is not data');
        self::assertFalse($top[3]->literalAppend, 'an ordinary assignment is not an append');
    }

    /**
     * A statement the span only clips is not in the run.
     *
     * A match begins and ends where the tokens agreed, which is routinely
     * partway through a statement. Counting a statement the clone holds two
     * tokens of made a table written as statements fail to read as one, for a
     * statement it does not contain.
     */
    #[Test]
    public function aStatementTheSpanOnlyClipsIsNotInTheRun(): void
    {
        $facts = FileStatements::fromSource(
            "<?php\n\$rows = [];\n\$rows[] = ['a', 1];\n\$rows[] = ['b', 2];\n\$rows[] = ['c', 3];\n",
        );

        $all  = $facts->all();
        $init = $all[0];

        self::assertFalse($init->literalAppend, 'the fixture opens with an initialiser, not an append');

        // From the initialiser's own last token to the end: the initialiser is
        // clipped, every append is whole.
        $clipped = $facts->literalAppendRun($init->last, $all[3]->last - $init->last + 1);

        // And from the first append, which holds no part of the initialiser.
        $clean = $facts->literalAppendRun($all[1]->first, $all[3]->last - $all[1]->first + 1);

        self::assertNotNull($clean, 'three whole appends are a run');
        self::assertSame($clean, $clipped, 'clipping the statement before them does not change which run they are');
    }

    /** A run is named by where it begins, so two ranges inside one are named alike. */
    #[Test]
    public function twoRangesInOneAppendRunAreNamedAlike(): void
    {
        $rows = '';

        for ($index = 0; $index < 12; $index++) {
            $rows .= sprintf("\$rows[] = ['code' => 'C%02d', 'n' => %d];\n", $index, $index);
        }

        $facts = FileStatements::fromSource("<?php\n" . $rows);
        $all   = $facts->all();

        $first = $facts->literalAppendRun($all[0]->first, $all[0]->length());
        $later = $facts->literalAppendRun($all[7]->first, $all[7]->length());

        self::assertNotNull($first);
        self::assertSame($first, $later);
    }

    /**
     * A run whose last append is followed straight by the brace closing the
     * function still reads as a run.
     *
     * `literalAppendRun()` already skipped a closing-delimiter statement when
     * checking that every statement is an append — added deliberately, because
     * once the encoder counted single-character tokens a span could reach the
     * `}` — but the second pass, which checks that every statement names the
     * same variable, did not. A run of delimiters names none, so that pass
     * rejected exactly the spans the first had just admitted, and the skip
     * never took effect.
     */
    #[Test]
    public function aRunEndingAtTheBraceThatClosesItIsStillARun(): void
    {
        $facts = FileStatements::fromSource(
            "<?php\nfunction seed(): void\n{\n"
            . "    \$rows[] = ['a', 1];\n"
            . "    \$rows[] = ['b', 2];\n"
            . "    \$rows[] = ['c', 3];\n}\n",
        );

        $all = $facts->all();

        // The three appends, and then the same span extended over the `}`.
        $appends = $facts->literalAppendRun($all[1]->first, $all[3]->last - $all[1]->first + 1);
        $withBrace = $facts->literalAppendRun($all[1]->first, $all[4]->last - $all[1]->first + 1);

        self::assertNotNull($appends, 'three literal appends are a run');
        self::assertTrue($all[4]->closing, 'the fixture really does end in a closing-delimiter statement');
        self::assertSame($appends, $withBrace, 'reaching the closing brace does not stop it being one');
    }

    /**
     * And the paired negative the charter ships with is untouched: a run over
     * one shared receiver is a procedure, and order matters in it.
     */
    #[Test]
    public function aSharedReceiverIsStillAProcedure(): void
    {
        $role = FileRole::of(FileStatements::fromSource(
            "<?php\n\$c->set('a', 1);\n\$c->set('b', 2);\n\$c->set('c', 3);\n",
        ));

        self::assertTrue($role->coupled);
        self::assertFalse($role->registration);
    }

    #[Test]
    public function thePreambleIsNotCountedAsTopLevelStatements(): void
    {
        $facts = FileStatements::fromSource(self::ROUTES);
        $top   = $facts->topLevel();

        // declare, namespace, use, and the three registrations.
        self::assertSame(6, count($top));

        $preamble = 0;

        foreach ($top as $statement) {
            $preamble += $statement->preamble ? 1 : 0;
        }

        self::assertSame(3, $preamble);
    }

    #[Test]
    public function anOrdinaryClassFileHasNoRole(): void
    {
        // A class declaration is itself one top-level statement, and it is not a
        // call — so an ordinary source file has a denominator of one and a
        // numerator of zero, and is asserted.
        $role = FileRole::of(FileStatements::fromSource('<?php class Thing { public function go(): void { $this->x(); } }'));

        self::assertFalse($role->registration);
        self::assertSame(1, $role->statements);
        self::assertSame(0, $role->registrations);
    }

    #[Test]
    public function anEmptyFileHasNoRole(): void
    {
        $role = FileRole::of(FileStatements::fromSource('<?php'));

        self::assertFalse($role->registration);
        self::assertSame(0, $role->statements);
        self::assertSame('no top-level statements past the preamble', $role->explain());
    }

    #[Test]
    public function aStatementThatWritesIsNotARegistrationExpression(): void
    {
        $facts = FileStatements::fromSource("<?php\n\$a = go();\nrun();\n");
        $top   = $facts->topLevel();

        self::assertCount(2, $top);
        self::assertFalse($top[0]->singleCall);
        self::assertTrue($top[1]->singleCall);
    }

    #[Test]
    public function aFluentChainIsOneRegistrationExpression(): void
    {
        $facts = FileStatements::fromSource("<?php\nRoute::get('/a')->name('a')->middleware('auth');\n");
        $top   = $facts->topLevel();

        self::assertCount(1, $top);
        self::assertTrue($top[0]->singleCall);
    }

    /**
     * PHP allows every reserved word as a member name, and after `::` the
     * tokenizer hands the keyword back as itself — `Breadcrumbs::for(…)` as
     * `T_FOR`. Reading that as a `for` loop refused every statement in a real
     * breadcrumb file, 136 of 136, which is how it was found.
     */
    #[Test]
    public function aKeywordUsedAsAMethodNameIsAName(): void
    {
        $facts = FileStatements::fromSource(
            "<?php\nBreadcrumbs::for('home', 'x');\nRoute::match('get', '/a', 'b');\nA::print('x');\n",
        );
        $top   = $facts->topLevel();

        self::assertCount(3, $top);

        foreach ($top as $index => $statement) {
            self::assertTrue($statement->singleCall, sprintf('statement %d reads as one call', $index));
        }
    }

    /**
     * And the position is what decides, not the word: the same keyword opening
     * a statement is still the construct it names.
     */
    #[Test]
    public function aKeywordThatIsNotAMemberNameStillRefusesTheStatement(): void
    {
        $facts = FileStatements::fromSource("<?php\nfor (\$i = 0; \$i < 3; ++\$i) {\n    go();\n}\n");
        $top   = $facts->topLevel();

        self::assertNotSame([], $top);
        self::assertFalse($top[0]->singleCall);
    }
}
