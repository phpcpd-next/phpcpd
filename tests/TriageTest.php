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

use function array_map;
use function count;
use function str_replace;

use LucianoPereira\PhpcpdNext\Orphan\AutoloadRule;
use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;
use LucianoPereira\PhpcpdNext\Orphan\SymbolCollector;
use LucianoPereira\PhpcpdNext\Triage\ShadowedDuplicates;
use LucianoPereira\PhpcpdNext\Triage\ShadowedFile;
use LucianoPereira\PhpcpdNext\Triage\Stage0;
use LucianoPereira\PhpcpdNext\Triage\TriageDecision;
use LucianoPereira\PhpcpdNext\Triage\TriageResult;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Stage 0, the corpus-triage stage: what is program text before duplication is
 * measured at all.
 *
 * The fixture behind these tests was **pre-registered** in the M4 audit packet
 * (§4.3) before the rung was written, and for a stated reason: the backup subtree
 * that motivated ruling P had already left the private corpus, so the rung had no
 * live subject and its acceptance would otherwise have been a fixture chosen
 * after seeing the result. It is one positive — a file the autoloader cannot
 * reach, holding nothing but names another file owns — against five negatives,
 * each of which is a way for the question to be unanswerable.
 */
#[CoversClass(AutoloadRule::class)]
#[CoversClass(ComposerManifest::class)]
#[CoversClass(ShadowedDuplicates::class)]
#[CoversClass(ShadowedFile::class)]
#[CoversClass(Stage0::class)]
#[CoversClass(TriageDecision::class)]
#[CoversClass(TriageResult::class)]
final class TriageTest extends TestCase
{
    private const string PROJECT = __DIR__ . '/fixtures/shadowed/project';

    private const string DERIVED = __DIR__ . '/fixtures/derived/project';

    /**
     * The rung's whole claim, on the constructed fixture: exactly one file of the
     * ten is unreachable, and the file it names as the reachable one is the file
     * composer.json maps.
     */
    public function testOnlyTheUnmappedDuplicateIsShadowed(): void
    {
        $shadowed = $this->detect();

        self::assertSame(['copies/Support/Ledger.php'], $this->relative($shadowed));
        self::assertSame(['Demo\Shadow\Support\Ledger'], $shadowed[0]->symbols);
        self::assertSame(
            ['src/Support/Ledger.php'],
            array_map(fn(string $file): string => $this->strip($file), $shadowed[0]->shadowedBy),
        );
    }

    /**
     * Never silent: a discarded file has to be able to say what replaced it, in
     * one line, naming the wired file rather than itself.
     */
    public function testTheFindingExplainsItself(): void
    {
        $shadowed = $this->detect();

        self::assertStringContainsString('1 declared symbol is autoloaded from', $shadowed[0]->reason());
        self::assertStringContainsString('src/Support/Ledger.php', $shadowed[0]->reason());
    }

    /**
     * The five negatives, asserted as the absence they are. Each is a distinct
     * reason the autoloader question cannot be answered against the file, and any
     * one of them appearing here is a live file being called dead.
     */
    public function testEveryUnanswerableCaseIsKept(): void
    {
        $kept = $this->relative($this->detect());

        // A name no other file declares.
        self::assertNotContains('src/Support/Register.php', $kept);
        // Two mapped directories under one prefix: the autoloader reaches both.
        self::assertNotContains('src/Support/Twin.php', $kept);
        self::assertNotContains('lib/Support/Twin.php', $kept);
        // One shadowed name, one of its own — the file still holds code.
        self::assertNotContains('copies/Support/Partial.php', $kept);
        // Declares nothing at all, so the premise is vacuous.
        self::assertNotContains('copies/bootstrap.php', $kept);
        // A namespace the manifest does not map: neither copy is reachable.
        self::assertNotContains('src/Legacy/Thing.php', $kept);
        self::assertNotContains('copies/Legacy/Thing.php', $kept);
    }

    /**
     * With no manifest there is no autoload map, and the rung's discriminator does
     * not exist. The identical file set must then be kept whole — the alternative
     * is falling back on the directory's name, which is what ruling K forbids.
     */
    public function testWithoutAManifestNothingIsShadowed(): void
    {
        $symbols = (new SymbolCollector())->collect($this->files());

        self::assertSame([], (new ShadowedDuplicates())->detect($symbols->definitions, null));
    }

    /** Two runs over one tree report one order, as everything downstream requires. */
    public function testTheAnswerIsDeterministic(): void
    {
        self::assertSame($this->relative($this->detect()), $this->relative($this->detect()));
    }

    /**
     * The map itself, at the two standards' one substantive difference: PSR-4
     * strips the matched prefix, PSR-0 keeps the whole name below the directory
     * and reads underscores in the class name as separators.
     */
    public function testTheAutoloadRuleMapsBothStandards(): void
    {
        $psr4 = new AutoloadRule(AutoloadRule::PSR_4, 'Demo\\App\\', '/p/src');
        $psr0 = new AutoloadRule(AutoloadRule::PSR_0, 'Demo\\App\\', '/p/lib');

        self::assertSame('/p/src/Support/Ledger.php', $psr4->pathFor('Demo\\App\\Support\\Ledger'));
        self::assertSame('/p/lib/Demo/App/Support/Ledger.php', $psr0->pathFor('Demo\\App\\Support\\Ledger'));
        self::assertSame('/p/lib/Twig/Node/Print.php', (new AutoloadRule(AutoloadRule::PSR_0, 'Twig_', '/p/lib'))->pathFor('Twig_Node_Print'));

        // A prefix must match on a namespace boundary, or it claims names in a
        // namespace the project never declared.
        self::assertNull($psr4->pathFor('Demo\\Application\\Thing'));
        self::assertNull($psr4->pathFor('Other\\Thing'));
    }

    /**
     * Ruling V, stated as the defect it closes. `CachedOnly` is named by exactly
     * one file in the tree — the compiled container blob under
     * `storage/framework`, which the product's own default excludes never scan.
     * Allowed to witness, that blob makes a dead class look alive; and because
     * the blob is regenerated on a warm cache and absent on a cold one, the
     * verdict moved with the developer's cache state rather than with the code.
     */
    public function testACompiledCacheCanMakeADeadClassLookWiredWhenItMayWitness(): void
    {
        $result = $this->triageDerivedFixture(witnesses: null);

        self::assertSame(
            ['src/Support/CachedOnly.php', 'src/Support/Wired.php', 'storage/framework/cache/container.php'],
            $this->strippedDerived($result->kept),
        );
    }

    /**
     * The same tree, with the witness set restricted to the rung-2 survivors: the
     * blob is discarded as derived and, no longer able to vouch for anything, it
     * stops holding `CachedOnly` up. A derived artifact is judged, and never a
     * judge.
     */
    public function testADerivedArtifactIsJudgedButNeverAJudge(): void
    {
        $result = $this->triageDerivedFixture(witnesses: $this->derivedRungTwo());

        self::assertSame(['src/Support/Wired.php'], $this->strippedDerived($result->kept));

        $reasons = [];

        foreach ($result->discarded as $decision) {
            $reasons[$this->stripDerived($decision->file)] = $decision->reason;
        }

        self::assertSame(
            [
                'storage/framework/cache/container.php' => TriageDecision::DERIVED,
                'src/Boot.php'                          => TriageDecision::UNWIRED,
                'src/Support/CachedOnly.php'            => TriageDecision::UNWIRED,
            ],
            $reasons,
        );
    }

    /**
     * Never silent, for the new rung too: the derived file is counted where the
     * caller looks for counts, and says in one line what removed it.
     */
    public function testTheDerivedRungIsCountedAndExplainsItself(): void
    {
        $result = $this->triageDerivedFixture(witnesses: $this->derivedRungTwo());

        self::assertSame(1, $result->counts()[TriageDecision::DERIVED]);
        self::assertCount(1, $result->because(TriageDecision::DERIVED));
        self::assertStringContainsString(
            'may not witness wiring',
            $result->because(TriageDecision::DERIVED)[0]->detail,
        );
    }

    /**
     * Wiring is the only thing the restriction changes. `Wired` is referenced
     * from program text and survives either way, so the ruling narrows what
     * counts as evidence without narrowing what is scanned.
     */
    public function testRestrictingWitnessesDoesNotDisturbAFileWiredFromProgramText(): void
    {
        foreach ([null, $this->derivedRungTwo()] as $witnesses) {
            self::assertContains(
                'src/Support/Wired.php',
                $this->strippedDerived($this->triageDerivedFixture($witnesses)->kept),
            );
        }
    }

    /**
     * The four rungs, all of which prove their case.
     *
     * @param ?list<string> $witnesses
     */
    private function triageDerivedFixture(?array $witnesses): TriageResult
    {
        return (new Stage0())->triage(
            (new FileFinder())->find([self::DERIVED], ['.php'], [], false),
            ComposerManifest::locate([self::DERIVED]),
            null,
            null,
            $witnesses,
        );
    }

    /** @return list<string> the rung-2 survivors: the product's own default excludes applied */
    private function derivedRungTwo(): array
    {
        return (new FileFinder())->find([self::DERIVED], ['.php'], [], true);
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private function strippedDerived(array $files): array
    {
        return array_map(fn(string $file): string => $this->stripDerived($file), $files);
    }

    private function stripDerived(string $path): string
    {
        return str_replace(self::DERIVED . '/', '', $path);
    }

    /** @return list<ShadowedFile> */
    private function detect(): array
    {
        $symbols  = (new SymbolCollector())->collect($this->files());
        $manifest = ComposerManifest::locate([self::PROJECT]);

        self::assertInstanceOf(ComposerManifest::class, $manifest);

        return (new ShadowedDuplicates())->detect($symbols->definitions, $manifest);
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = (new FileFinder())->find([self::PROJECT], ['.php'], []);

        self::assertSame(9, count($files));

        return $files;
    }

    /**
     * @param list<ShadowedFile> $shadowed
     * @return list<string>
     */
    private function relative(array $shadowed): array
    {
        return array_map(fn(ShadowedFile $file): string => $this->strip($file->file), $shadowed);
    }

    private function strip(string $path): string
    {
        return str_replace(self::PROJECT . '/', '', $path);
    }
}
