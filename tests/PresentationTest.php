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

use function array_keys;
use function array_map;
use function count;
use function array_sum;
use function sort;

use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Presentation\ConfidenceModel;
use LucianoPereira\PhpcpdNext\Presentation\Finding;
use LucianoPereira\PhpcpdNext\Presentation\Findings;
use LucianoPereira\PhpcpdNext\Presentation\Presenter;
use LucianoPereira\PhpcpdNext\Presentation\Strata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The presentation tier, end to end through the engine.
 *
 * The load-bearing assertion is the first one: **the tier never filters**. Every
 * clone the detector produced reaches the report, and the demote strata change
 * how a finding is presented rather than whether it exists. A tier that could
 * drop a finding would be a suppression mechanism wearing a report's clothes,
 * and the M5 experiment is the evidence that silencing this class is exactly
 * what the labels do *not* license.
 *
 * The rest is the charter's pre-registered strata, each with its paired negative:
 *
 *   D1 table         two generated tables in two files — a genuine clone, and
 *                    the corpus's flagship true positive in miniature. Demoted,
 *                    and still reported, still counted.
 *   D2 registration  two route files whose top level is dataflow-independent
 *                    registration expressions. Its negative is the same
 *                    registrations over one shared receiver, which stays
 *                    asserted.
 */
#[CoversClass(Presenter::class)]
#[CoversClass(Strata::class)]
#[CoversClass(Finding::class)]
#[CoversClass(Findings::class)]
#[CoversClass(ConfidenceModel::class)]
#[CoversClass(\LucianoPereira\PhpcpdNext\Presentation\ConfidenceFeatures::class)]
final class PresentationTest extends TestCase
{
    private const string DATATABLE    = __DIR__ . '/fixtures/datatable';
    private const string REGISTRATION = __DIR__ . '/fixtures/registration';
    private const string SEEDER       = __DIR__ . '/fixtures/seeder';
    private const string STRATA       = __DIR__ . '/fixtures/strata';

    /** @param list<string> $files */
    private function present(array $files): Findings
    {
        // The shipped default. A token is every token now, punctuation
        // included, so a window of seventy spans about half the source it used
        // to and these fixtures fall either side of the strata being tested.
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 100,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        return (new Presenter())->present((new Engine($config, 'unified'))->detect($files));
    }

    /**
     * The findings these files produce, having first checked that there are
     * some: every claim below is a loop over them, and an empty presentation
     * would pass all of them without having tested anything.
     *
     * @param  list<string> $files
     * @return list<Finding>
     */
    private function findingsOver(array $files): array
    {
        $findings = $this->present($files);

        self::assertGreaterThan(0, $findings->count());

        return $findings->findings;
    }

    /**
     * Every finding these files produce carries $stratum.
     *
     * @param list<string> $files
     */
    private function assertEveryFindingCarries(string $stratum, array $files): void
    {
        foreach ($this->findingsOver($files) as $finding) {
            self::assertContains($stratum, $finding->strata);
        }
    }

    /**
     * No finding these files produce carries $stratum, and none of them is
     * demoted — the two halves of "stays asserted", which are one claim and
     * are made together everywhere they are made.
     *
     * @param list<string> $files
     */
    private function assertEveryFindingStaysAsserted(string $stratum, array $files): void
    {
        foreach ($this->findingsOver($files) as $finding) {
            self::assertNotContains($stratum, $finding->strata);
            self::assertFalse($finding->demoted());
        }
    }

    #[Test]
    public function itPresentsEveryCloneAndRemovesNone(): void
    {
        $config = new StrategyConfiguration(5, 70, Normalization::Raw, 0.7);
        $files  = [self::STRATA . '/table_rows_a.php', self::STRATA . '/table_rows_b.php'];
        $clones = (new Engine($config, 'unified'))->detect($files);

        $findings = (new Presenter())->present($clones);

        self::assertSame($clones->count(), $findings->count());
        self::assertSame($clones->count(), $findings->asserted() + $findings->demoted());

        // The same SET, in the ranking's order rather than the map's.
        $before = array_map(static fn($clone) => $clone->id(), $clones->clones());
        $after  = array_map(static fn(Finding $finding) => $finding->clone->id(), $findings->findings);
        sort($before);
        sort($after);

        self::assertSame($before, $after);
    }

    #[Test]
    public function aTablePairMatchingInsideOneFrameIsDemotedAndStillReported(): void
    {
        $findings = $this->present([
            self::STRATA . '/table_rows_a.php',
            self::STRATA . '/table_rows_b.php',
        ]);

        self::assertGreaterThan(0, $findings->count());

        foreach ($findings->findings as $finding) {
            self::assertContains(Strata::TABLE, $finding->strata);
            self::assertTrue($finding->demoted());
        }

        // Demote, never suppress: the finding is in the report and in the count.
        self::assertSame($findings->count(), $findings->demoted());
        self::assertSame(0, $findings->asserted());
    }

    #[Test]
    public function theFlagshipTruePositiveShapeStaysAsserted(): void
    {
        // Two generated tables in two files, matching whole-file: the reported
        // span reaches out past the array frame to the `return` that opens it,
        // so it is **not** in D1's scope and the finding is asserted. This is the
        // `Php7.php`/`Php8.php` shape in miniature, and the M5 experiment
        // measured the same thing on the real pair — out of rule 9's scope,
        // because the span begins at the declaration.
        //
        // It is here as a paired negative rather than as a curiosity: the stratum
        // is a scope test, and a scope test that crept outward to cover the
        // corpus's flagship true positive would be the failure every refuted
        // discriminator shared.
        foreach ($this->findingsOver([
            self::DATATABLE . '/generated_table_a.php',
            self::DATATABLE . '/generated_table_b.php',
        ]) as $finding) {
            self::assertNotContains(Strata::TABLE, $finding->strata);
        }
    }

    /**
     * Data does not have to be written as an array. A seeder builds the same
     * table out of `$rows[] = […];` statements, which no statement-free frame
     * can contain, so `literalTable()` cannot see it and the finding was
     * asserted. It is the same table to a reader, and repeating itself is the
     * regularity that makes it one.
     */
    #[Test]
    public function aTableWrittenAsStatementsRepeatingItselfIsDemoted(): void
    {
        // The span a clone covers is now asked in tokens, which is how the
        // match was made. Deriving it from the reported line range handed back
        // `$rows = [];` — a statement this clone covers only the tail of.

        $this->assertEveryFindingCarries(Strata::TABLE, [self::SEEDER . '/seeder_self.php']);
    }

    /**
     * And two *different* runs agreeing is not that. The M5 rating round called
     * copied seeder-like tables genuine duplication — half of what it vindicated
     * in the demoted stratum was exactly this shape — so the identity is the
     * run, the same way `sameLiteralTable()` makes it the array.
     */
    #[Test]
    public function twoDifferentSeedersStayAsserted(): void
    {
        $this->assertEveryFindingStaysAsserted(Strata::TABLE, [
            self::SEEDER . '/seeder_a.php',
            self::SEEDER . '/seeder_b.php',
        ]);
    }

    /**
     * A registration file repeating itself is the regularity that makes it one,
     * and its second run is not a copy anybody could remove.
     */
    #[Test]
    public function aRouteFileRepeatingItselfIsDemotedByItsRole(): void
    {
        $this->assertEveryFindingCarries(Strata::REGISTRATION, [self::REGISTRATION . '/routes_self.php']);
    }

    /**
     * Two *different* registration files agreeing is not that. It is the same
     * block written into two surfaces, and somebody may want it gone — which is
     * the distinction ruling H already draws for tables, where a table matching
     * itself is dropped and the `Php7.php`/`Php8.php` pair is the corpus's
     * flagship true positive and stays asserted.
     *
     * This asserted the other way until the reading was measured. Every crossing
     * of this kind on firefly-iii is one pair, `routes/api.php` against
     * `routes/web.php`, and the fixtures below are byte-identical route lists —
     * the shape a reader would want to see, not the shape a route file has by
     * nature. Six findings stay asserted for it, of 118 the wider composition
     * took.
     */
    #[Test]
    public function twoDifferentRouteFilesStayAsserted(): void
    {
        $this->assertEveryFindingStaysAsserted(Strata::REGISTRATION, [
            self::REGISTRATION . '/routes_a.php',
            self::REGISTRATION . '/routes_b.php',
        ]);
    }

    #[Test]
    public function thePairedNegativeStaysAsserted(): void
    {
        foreach ($this->findingsOver([
            self::REGISTRATION . '/coupled_a.php',
            self::REGISTRATION . '/coupled_b.php',
        ]) as $finding) {
            self::assertSame([], $finding->strata);
            self::assertFalse($finding->demoted());
        }
    }

    #[Test]
    public function ordinaryCodeIsAsserted(): void
    {
        $findings = $this->present([self::STRATA . '/service_a.php', self::STRATA . '/service_b.php']);

        self::assertGreaterThan(0, $findings->count());
        self::assertSame($findings->count(), $findings->asserted());
    }

    #[Test]
    public function strataDoNotComposeAcrossSites(): void
    {
        // A table file and a registration file are two different strata, and
        // neither holds over both sites, so the finding is asserted. Demotion
        // requires one stratum to hold over the whole finding; a union of partial
        // evidence is the hiding place the auditor's condition forbids.
        foreach ($this->present([
            self::STRATA . '/table_rows_a.php',
            self::STRATA . '/table_rows_b.php',
        ])->findings as $finding) {
            self::assertNotContains(Strata::REGISTRATION, $finding->strata);
        }

        foreach ($this->present([
            self::REGISTRATION . '/routes_a.php',
            self::REGISTRATION . '/routes_b.php',
        ])->findings as $finding) {
            self::assertNotContains(Strata::TABLE, $finding->strata);
        }
    }

    /**
     * Every site, not any site — the composition rule, asserted where it is
     * load-bearing rather than only documented.
     *
     * The same fourteen registrations appear twice: once as a route file's whole
     * top level, and once copied into a class method. The first pair is
     * registration-role on both sides and is demoted; the second has the stratum
     * on one site only, and stays asserted, because a copy of registration *into*
     * program text is exactly what a reader wants shown.
     *
     * This test replaces the one the retired classifier's tag used to carry the
     * property on. The property is the charter's, not the classifier's.
     */
    #[Test]
    public function aStratumHoldingOverOnlyOneSiteDoesNotDemote(): void
    {
        $this->assertEveryFindingCarries(Strata::REGISTRATION, [self::REGISTRATION . '/routes_self.php']);

        $this->assertEveryFindingStaysAsserted(Strata::REGISTRATION, [
            self::REGISTRATION . '/routes_a.php',
            self::REGISTRATION . '/routes_inlined.php',
        ]);
    }

    #[Test]
    public function everyStratumReportsItsZero(): void
    {
        $counts = $this->present([self::STRATA . '/service_a.php', self::STRATA . '/service_b.php'])->perStratum();

        self::assertSame(Strata::NAMES, array_keys($counts));

        foreach ($counts as $count) {
            self::assertSame(0, $count);
        }
    }

    #[Test]
    public function rankingChangesTheOrderAndNeverTheSet(): void
    {
        // Four files, two clone classes of very different shape: a table pair and
        // an ordinary pair. Ranking has something to do, and must still hand back
        // exactly what it was given.
        $files = [
            self::STRATA . '/table_rows_a.php',
            self::STRATA . '/table_rows_b.php',
            self::STRATA . '/service_a.php',
            self::STRATA . '/service_b.php',
        ];

        $config = new StrategyConfiguration(5, 70, Normalization::Raw, 0.7);
        $clones = (new Engine($config, 'unified'))->detect($files);

        $ranked = (new Presenter())->present($clones);
        // The untrained model has no opinion, so it cannot reorder anything —
        // which is how the ranking's own null hypothesis is pinned.
        $unranked = (new Presenter(model: new ConfidenceModel([], 0, 0)))->present($clones);

        self::assertSame($clones->count(), $ranked->count());
        self::assertSame($ranked->count(), $unranked->count());

        $ids = static fn(Findings $findings): array
            => array_map(static fn(Finding $finding) => $finding->clone->id(), $findings->findings);

        $a = $ids($ranked);
        $b = $ids($unranked);
        sort($a);
        sort($b);

        self::assertSame($a, $b);

        foreach ($unranked->findings as $finding) {
            self::assertSame(0.0, $finding->confidence);
        }
    }

    #[Test]
    public function everyFindingCarriesTheTermsThatSumToItsScore(): void
    {
        foreach ($this->present([
            self::STRATA . '/service_a.php',
            self::STRATA . '/service_b.php',
        ])->findings as $finding) {
            self::assertEqualsWithDelta($finding->confidence, array_sum($finding->terms), 1.0e-9);
            self::assertStringContainsString('confidence', $finding->why());
        }
    }

    #[Test]
    public function twoRunsPresentIdentically(): void
    {
        $files = [self::REGISTRATION . '/routes_a.php', self::REGISTRATION . '/routes_b.php'];

        $first  = $this->present($files);
        $second = $this->present($files);

        self::assertSame(
            array_map(static fn(Finding $f) => $f->clone->id() . ':' . $f->tag(), $first->findings),
            array_map(static fn(Finding $f) => $f->clone->id() . ':' . $f->tag(), $second->findings),
        );
        self::assertSame(count($first->findings), count($second->findings));
    }
}
