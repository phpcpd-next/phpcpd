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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LucianoPereira\PhpcpdNext\Orphan\CollectedSymbols;
use LucianoPereira\PhpcpdNext\Orphan\Symbol;
use LucianoPereira\PhpcpdNext\Orphan\SymbolCollector;
use LucianoPereira\PhpcpdNext\Util\FileFinder;

/**
 * The collector tracks PHP block structure on a `$context` stack. Several
 * constructs can desync that stack, and a desynced stack silently corrupts
 * every later declaration in the same file: methods start being recorded as
 * free functions, and references inside skipped spans are lost — which reports
 * live code as a definite orphan.
 *
 * Each test here pins one construct that must not desync it.
 */
#[CoversClass(SymbolCollector::class)]
final class SymbolCollectorContextTest extends TestCase
{
    private const string DIR = __DIR__ . '/fixtures/orphans/context';

    #[Test]
    public function a_call_made_only_inside_a_closure_capture_counts_as_a_reference(): void
    {
        $collected = $this->collect('ClosureCapture.php');

        self::assertSame(1, $collected->references['onlyCalledInsideTheClosure'] ?? 0);
    }

    #[Test]
    public function methods_after_a_closure_capture_are_not_recorded_as_free_functions(): void
    {
        $collected = $this->collect('ClosureCapture.php');

        self::assertSame([], $this->freeFunctions($collected));
        self::assertSame(['ClosureCapture'], $this->typeNames($collected));
    }

    #[Test]
    public function a_closure_capture_at_top_level_still_counts_its_body(): void
    {
        $collected = $this->collect('top_level_closure.php');

        self::assertSame(1, $collected->references['only_called_inside_the_top_level_closure'] ?? 0);
    }

    #[Test]
    public function an_import_inside_a_brace_delimited_namespace_is_still_not_a_reference(): void
    {
        // Safety guard: an unused import must never count as a use-site, or a
        // dead class stays hidden behind its own import line.
        $collected = $this->collect('braced_namespace.php');

        self::assertArrayNotHasKey('NeverActuallyUsed', $collected->references);
    }

    #[Test]
    public function a_class_imported_under_an_alias_counts_as_referenced(): void
    {
        // `use A\B\Original as Alias;` means the class is only ever written as
        // `Alias`, so without resolving the pair the original looks dead.
        $collected = $this->collect('aliased_import.php');

        self::assertSame(1, $collected->references['PostgresGrammar'] ?? 0);
    }

    #[Test]
    public function an_aliased_import_that_is_never_used_counts_nothing(): void
    {
        // The guarantee resolving aliases must not break: an unused import is
        // still not a use-site, or a dead class hides behind its own import.
        $collected = $this->collect('unused_aliased_import.php');

        self::assertArrayNotHasKey('NeverUsedGrammar', $collected->references);
    }

    #[Test]
    public function aliases_are_resolved_in_every_import_form(): void
    {
        // Comma-separated, braced group, and `use function ... as ...` — each
        // credited only when its own alias is the one actually used.
        $collected = $this->collect('aliased_import_forms.php');

        self::assertSame(1, $collected->references['CommaAliased'] ?? 0);
        self::assertSame(1, $collected->references['BracedAliased'] ?? 0);
        self::assertSame(1, $collected->references['aliased_helper'] ?? 0);

        self::assertArrayNotHasKey('CommaSkipped', $collected->references);
        self::assertArrayNotHasKey('BracedSkipped', $collected->references);
    }

    #[Test]
    public function phpcpds_own_source_declares_no_free_functions(): void
    {
        // Dogfooding invariant: every declaration in src/ lives inside a type.
        // A free function here means the context stack desynced on some
        // construct in our own code — the cheapest smoke alarm we have.
        $files     = (new FileFinder())->find([__DIR__ . '/../src'], ['.php'], []);
        $collected = (new SymbolCollector())->collect($files);

        self::assertSame([], $this->freeFunctions($collected));
    }

    #[Test]
    public function a_trait_used_only_by_an_anonymous_class_counts_as_referenced(): void
    {
        $collected = $this->collect('anonymous_class.php');

        self::assertSame(1, $collected->references['TraitUsedOnlyByAnonymousClass'] ?? 0);
    }

    #[Test]
    public function anonymous_class_methods_are_not_recorded_as_free_functions(): void
    {
        $collected = $this->collect('anonymous_class.php');

        self::assertSame([], $this->freeFunctions($collected));
    }

    #[Test]
    public function the_class_constant_is_not_mistaken_for_a_declaration(): void
    {
        $collected = $this->collect('class_constant.php');

        self::assertSame([], $this->freeFunctions($collected));
        self::assertSame(['ClassConstantHost'], $this->typeNames($collected));
        self::assertSame(1, $collected->references['ClosureCapture'] ?? 0);
    }

    #[Test]
    public function curly_brace_interpolation_does_not_swallow_a_block_level(): void
    {
        // "{$x}" tokenizes as an *array* T_CURLY_OPEN but a plain '}' string
        // token, so the closing brace must not pop a real block level.
        $collected = $this->collect('interpolation.php');

        self::assertSame([], $this->freeFunctions($collected));
        self::assertSame(['Interpolation'], $this->typeNames($collected));
        self::assertSame(1, $collected->references['TraitUsedAfterInterpolation'] ?? 0);
    }

    private function collect(string $fixture): CollectedSymbols
    {
        return (new SymbolCollector())->collect([self::DIR . '/' . $fixture]);
    }

    /**
     * @return list<string> names of everything recorded as a free function
     */
    private function freeFunctions(CollectedSymbols $collected): array
    {
        $functions = [];

        foreach ($collected->definitions as $symbol) {
            if ($symbol->kind === Symbol::KIND_FUNCTION) {
                $functions[] = $symbol->name;
            }
        }

        return $functions;
    }

    /**
     * @return list<string> names of every declared type
     */
    private function typeNames(CollectedSymbols $collected): array
    {
        $types = [];

        foreach ($collected->definitions as $symbol) {
            if ($symbol->kind !== Symbol::KIND_FUNCTION) {
                $types[] = $symbol->name;
            }
        }

        return $types;
    }
}
