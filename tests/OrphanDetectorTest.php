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

require_once __DIR__ . '/_guard.php';

use function array_map;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LucianoPereira\PhpcpdNext\Orphan\Orphan;
use LucianoPereira\PhpcpdNext\Orphan\OrphanDetector;
use LucianoPereira\PhpcpdNext\Orphan\OrphanResult;
use LucianoPereira\PhpcpdNext\Orphan\Symbol;
use LucianoPereira\PhpcpdNext\Orphan\SymbolCollector;
use LucianoPereira\PhpcpdNext\Orphans;
use LucianoPereira\PhpcpdNext\Phpcpd;
use LucianoPereira\PhpcpdNext\Util\FileFinder;

#[CoversClass(OrphanDetector::class)]
#[CoversClass(SymbolCollector::class)]
#[CoversClass(Orphans::class)]
#[CoversClass(Symbol::class)]
#[CoversClass(Orphan::class)]
#[CoversClass(OrphanResult::class)]
final class OrphanDetectorTest extends TestCase
{
    private const string DIR      = __DIR__ . '/fixtures/orphans/src';
    private const string REPL_DIR = __DIR__ . '/fixtures/orphans/replacement';

    #[Test]
    public function it_reports_an_unreferenced_concrete_class_as_a_definite_orphan(): void
    {
        $definite = $this->fqns(Orphans::detect(self::DIR)->definite());

        self::assertContains('Demo\\AbandonedClass', $definite);
    }

    #[Test]
    public function it_demotes_an_unreferenced_interface_to_possible(): void
    {
        $result = Orphans::detect(self::DIR);

        self::assertContains('Demo\\PaymentContract', $this->fqns($result->possible()));
        self::assertNotContains('Demo\\PaymentContract', $this->fqns($result->definite()));
    }

    #[Test]
    public function it_demotes_a_string_referenced_class_to_possible(): void
    {
        // DynamicWidget is never referenced in code, but its FQN appears in a
        // string literal in UsedService — a possible dynamic use.
        $result = Orphans::detect(self::DIR);

        self::assertContains('Demo\\DynamicWidget', $this->fqns($result->possible()));
        self::assertNotContains('Demo\\DynamicWidget', $this->fqns($result->definite()));
    }

    #[Test]
    public function it_does_not_report_a_referenced_class(): void
    {
        // Helper is used by UsedService::run().
        self::assertNotContains('Demo\\Helper', $this->fqns(Orphans::detect(self::DIR)->all()));
    }

    #[Test]
    public function it_respects_api_suppression(): void
    {
        // UsedService and KeptApi are unreferenced within the set but carry @api.
        $all = $this->fqns(Orphans::detect(self::DIR)->all());

        self::assertNotContains('Demo\\UsedService', $all);
        self::assertNotContains('Demo\\KeptApi', $all);
    }

    #[Test]
    public function it_skips_attribute_wired_entry_points(): void
    {
        // ImportCommand is wired via #[AsCommand] — reachable, not dead.
        self::assertNotContains('Demo\\ImportCommand', $this->fqns(Orphans::detect(self::DIR)->all()));
    }

    #[Test]
    public function it_skips_test_classes_by_naming_convention(): void
    {
        self::assertNotContains('Demo\\OrphanWidgetTest', $this->fqns(Orphans::detect(self::DIR)->all()));
    }

    #[Test]
    public function definite_orphans_drive_the_exit_gate_but_possible_ones_do_not(): void
    {
        $result = Orphans::detect(self::DIR);

        self::assertTrue($result->hasDefiniteOrphans());
        self::assertGreaterThan(0, $result->count());
        self::assertSame(8, $result->symbolsScanned);
    }

    #[Test]
    public function a_recursive_only_function_stays_hidden_rather_than_falsely_flagged(): void
    {
        // Safety bias: a name used solely by itself still counts as referenced,
        // so we never tell a developer to delete something that is called.
        $files    = [__DIR__ . '/fixtures/orphans/recursion.php'];
        $result   = (new OrphanDetector())->detect($files);
        $reported = $this->fqns($result->all());

        self::assertNotContains('countdown', $reported);
    }

    #[Test]
    public function it_marks_a_wholly_dead_file_as_unwired(): void
    {
        // AbandonedClass.php declares only AbandonedClass, itself an orphan.
        $abandoned = $this->find(Orphans::detect(self::DIR)->all(), 'Demo\\AbandonedClass');

        self::assertNotNull($abandoned);
        self::assertTrue($abandoned->entireFileOrphaned);
    }

    #[Test]
    public function it_flags_an_orphan_that_is_a_superseded_copy_of_live_code(): void
    {
        // InvoiceLegacy is a token-identical, unreferenced twin of the live
        // Invoice, in a file that also holds a live class.
        $files  = (new FileFinder())->find([self::REPL_DIR], ['.php'], []);
        $clones = Phpcpd::detect(self::REPL_DIR, minTokens: 25, algorithm: 'rabin-karp');
        $result = (new OrphanDetector())->detect($files, $clones);

        $legacy = $this->find($result->all(), 'Demo\\Ledger\\InvoiceLegacy');

        self::assertNotNull($legacy);
        self::assertNotNull($legacy->duplicateOf);
        self::assertStringContainsString('Demo\\Ledger\\Invoice', (string) $legacy->duplicateOf);
        // The file also declares the live Invoice, so it is not wholly unwired.
        self::assertFalse($legacy->entireFileOrphaned);
    }

    #[Test]
    public function replacement_annotation_is_skipped_without_a_clone_map(): void
    {
        $files  = (new FileFinder())->find([self::REPL_DIR], ['.php'], []);
        $legacy = $this->find((new OrphanDetector())->detect($files)->all(), 'Demo\\Ledger\\InvoiceLegacy');

        self::assertNotNull($legacy);
        self::assertNull($legacy->duplicateOf);
    }

    #[Test]
    public function an_empty_set_produces_no_orphans(): void
    {
        $result = (new OrphanDetector())->detect([]);

        self::assertTrue($result->isEmpty());
        self::assertFalse($result->hasDefiniteOrphans());
        self::assertSame(0, $result->symbolsScanned);
    }

    /**
     * @param list<Orphan> $orphans
     * @return list<string>
     */
    private function fqns(array $orphans): array
    {
        return array_map(static fn(Orphan $o): string => $o->symbol->fqn, $orphans);
    }

    /**
     * @param list<Orphan> $orphans
     */
    private function find(array $orphans, string $fqn): ?Orphan
    {
        foreach ($orphans as $orphan) {
            if ($orphan->symbol->fqn === $fqn) {
                return $orphan;
            }
        }

        return null;
    }
}
