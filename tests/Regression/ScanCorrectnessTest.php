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

use function array_map;
use function basename;
use function chdir;
use function chmod;
use function getcwd;
use function is_readable;

use LucianoPereira\PhpcpdNext\Orphan\NameSweep;
use LucianoPereira\PhpcpdNext\Orphan\Orphan;
use LucianoPereira\PhpcpdNext\Orphan\OrphanConfiguration;
use LucianoPereira\PhpcpdNext\Orphan\OrphanDetector;
use LucianoPereira\PhpcpdNext\Orphan\OrphanResult;
use LucianoPereira\PhpcpdNext\Orphan\ProjectContext;
use LucianoPereira\PhpcpdNext\ScanScope;
use LucianoPereira\PhpcpdNext\Settings;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use LucianoPereira\PhpcpdNext\Tests\BuildsAFixtureProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Regressions for the scope and reference-detection defects found by adopting
 * 1.4.0 on a 382k-line Laravel modular monolith.
 *
 * Every case here shares one shape: the tool produced a wrong answer that read as
 * a right one — a clean gate over a contaminated corpus, a clean gate over 2% of
 * the source, or a live class named as safe to delete. A test that only asserted
 * "no crash" would have passed throughout, so each case asserts the *number* the
 * user would have acted on.
 */
#[CoversClass(FileFinder::class)]
#[CoversClass(NameSweep::class)]
#[CoversClass(OrphanDetector::class)]
#[CoversClass(ProjectContext::class)]
#[CoversClass(ScanScope::class)]
final class ScanCorrectnessTest extends TestCase
{
    use BuildsAFixtureProject;

    protected function setUp(): void
    {
        $this->makeFixtureRoot('regression');

        $this->write('composer.json', '{"name":"acme/app","autoload":{"psr-4":{"Acme\\\\":"src/"}}}');
    }

    protected function tearDown(): void
    {
        $this->delete($this->root);
    }

    /**
     * F2 — a tool cache must never be counted as source. PHPStan's `tmpDir` is
     * user-configured, so 1.4.0's `.phpstan.cache`-only default left the shorter
     * spelling scanning as source: 15.52% duplication reported against a real
     * 0.58%, on 1.1M lines of generated container code.
     */
    public function testDuplicationInsideAToolCacheIsNotScanned(): void
    {
        $body = "<?php\nnamespace Acme;\nclass Dup { public function run(): void {\n"
            . "        \$a = 1;\n        \$b = 2;\n        \$c = 3;\n    } }\n";

        foreach (['.phpstan', '.phpstan.cache', '.rector', '.psalm'] as $cache) {
            $this->write($cache . '/Container_a.php', $body);
            $this->write($cache . '/Container_b.php', $body);
        }

        $this->assertSame([], $this->find(), 'a tool cache directory must be pruned in either spelling');
    }

    /**
     * F12 — `*.blade.php` belongs in the clone excludes and must not follow into
     * reference detection. In a Laravel application a view is a primary call site,
     * so a class called only from a template was reported dead while the file
     * holding the reference was never opened.
     */
    public function testAClassUsedOnlyFromABladeTemplateIsNotDead(): void
    {
        $this->write('src/StaticPage/HydrationMarker.php', <<<'PHP'
            <?php
            namespace Acme\StaticPage;

            class HydrationMarker
            {
                public static function marksFor(string $page): string { return $page; }
            }
            PHP);

        $this->write(
            'resources/views/layouts/index.blade.php',
            "<html>\n@if (\\Acme\\StaticPage\\HydrationMarker::marksFor(\$page))\n<meta>\n@endif\n</html>\n",
        );

        // The exclude that keeps templates out of clone detection must not also
        // blind the reference scan — that is the whole defect.
        $this->assertNotContains('HydrationMarker', $this->deadNames(['*.blade.php']));
    }

    /**
     * F6 — seeders and factories are instantiated by framework convention, so no
     * class name appears anywhere in code. Measured: 24 live ones in the tier that
     * gates CI, where acting on the finding deletes a working database bootstrap.
     */
    public function testSeedersAndFactoriesAreNotDefiniteOrphans(): void
    {
        $this->write('src/Database/Seeders/DatabaseSeeder.php', "<?php\nnamespace Acme\\Database\\Seeders;\nclass DatabaseSeeder {}\n");
        $this->write('src/Database/Seeders/S01_UserSeeder.php', "<?php\nnamespace Acme\\Database\\Seeders;\nclass S01_UserSeeder {}\n");
        $this->write('src/Database/Factories/UserFactory.php', "<?php\nnamespace Acme\\Database\\Factories;\nclass UserFactory {}\n");
        $this->write('src/Genuinely/Dead.php', "<?php\nnamespace Acme\\Genuinely;\nclass Dead {}\n");

        // The genuinely unreferenced class must survive: a rule that silences the
        // seeders by silencing everything would pass a weaker assertion.
        $this->assertSame(['Dead'], $this->deadNames());
    }

    /**
     * Found while tracing F11 — a statement ending in `;` never opened the body
     * it announced. A brace-less existence guard left 'guard' pending and stamped
     * it on the next unrelated block, so a type declared in that block was read
     * as living inside a polyfill guard and suppressed. Verified as a witness:
     * before the fix this reports 0 orphaned / 1 suppressed.
     */
    public function testABraceLessExistenceGuardDoesNotSuppressALaterDeclaration(): void
    {
        $this->write('src/Bootstrap.php', <<<'PHP'
            <?php
            namespace Acme;

            if (!class_exists('Acme\Legacy')) require __DIR__ . '/legacy.php';

            foreach ([1] as $n) {
                class TotallyDead {}
            }
            PHP);

        $this->assertSame(['TotallyDead'], $this->deadNames());
    }

    /**
     * F1 — a single permission-denied directory anywhere beneath the scan root
     * killed the process: no report, no partial result, no usable exit code. The
     * walk must survive it, still return everything it *could* read, and say how
     * much it could not.
     */
    public function testAnUnreadableDirectoryIsSkippedAndCounted(): void
    {
        $this->write('src/Readable.php', "<?php\nnamespace Acme;\nclass Readable {}\n");
        $this->write('src/locked/Hidden.php', "<?php\nnamespace Acme;\nclass Hidden {}\n");

        if (!chmod($this->root . '/src/locked', 0o000)) {
            $this->markTestSkipped('cannot revoke directory permissions here');
        }

        // Running as root defeats the permission bit entirely, so only assert
        // once the directory really is unreadable.
        if (is_readable($this->root . '/src/locked')) {
            chmod($this->root . '/src/locked', 0o777);
            $this->markTestSkipped('permissions are not enforced for this user');
        }

        $finder = new FileFinder();
        $files  = $finder->find([$this->root], ['.php'], []);

        chmod($this->root . '/src/locked', 0o777);

        $this->assertCount(1, $files, 'the readable file must still be returned');
        $this->assertSame(1, $finder->skippedDirectoryCount(), 'the run must say how much it could not read');
    }

    /**
     * F9 — `RecursiveDirectoryIterator` joins its root to each entry by
     * concatenation, so the scan root `/` reported every path as
     * `//usr/bin/sudo`. Exercised with a root that already carries the doubled
     * separator, which reproduces the defect exactly without walking the
     * filesystem: passing the root unchanged is what produced the doubling.
     */
    public function testPathsAreNotReportedWithADoubledSeparator(): void
    {
        $this->write('src/Thing.php', "<?php\nnamespace Acme;\nclass Thing {}\n");

        $files = (new FileFinder())->find(['/' . $this->root], ['.php'], []);

        $this->assertCount(1, $files);
        $this->assertStringStartsNotWith('//', $files[0]);
    }

    /**
     * F3 — a preset that resolves to almost nothing is a misconfiguration, not a
     * clean result. Measured: `--preset=laravel` scanned 61 of 2,626 files and
     * printed `No code clones found`, which is indistinguishable from `I did not
     * look`.
     */
    public function testAPresetWarnsAboutScanPathsThatDoNotExist(): void
    {
        // The layout the warning exists for: two of the preset's four declared
        // paths exist, so a scan "succeeds" over a fraction of the project.
        $this->write('config/app.php', "<?php\nreturn [];\n");
        $this->write('database/.gitkeep', '');

        // Preset paths are relative by design, so resolve them from the fixture
        // root the way a user's shell would.
        $warning = $this->inFixtureRoot(function (): ?string {
            return ScanScope::presetWarning(Settings::resolve([['preset', 'laravel']]));
        });

        $this->assertNotNull($warning);
        // The count of missing paths, however the sentence is worded: the
        // regression this guards is a preset silently scanning a fraction of a
        // project, and what proves the warning fired is that it names how many
        // of the declared paths are absent.
        $this->assertStringContainsString('(2)', $warning);
        $this->assertStringContainsString('app', $warning);
        $this->assertStringContainsString('routes', $warning);
    }

    /**
     * F7 — `phpcpd /` differs from `phpcpd ./` by one character and several
     * million files, and announced the difference with a stack trace.
     */
    public function testTheFilesystemRootIsRefused(): void
    {
        $this->assertNotNull(ScanScope::refusal(Settings::resolve([], ['/'])));
        $this->assertNull(ScanScope::refusal(Settings::resolve([], [$this->root])));
    }

    /** The refusal is a guard, not a wall: an explicit flag still gets through. */
    public function testTheRootScanRefusalHasAnEscapeHatch(): void
    {
        $this->assertNull(ScanScope::refusal(Settings::resolve([['allow-root-scan', null]], ['/'])));
    }

    /**
     * F8 — orphan detection is only sound when the scan can see every possible
     * referencing site. Measured: one controller directory reported nine live
     * controllers as "whole file is unwired", every one imported from elsewhere.
     */
    public function testAnOrphanScanBelowTheAutoloadRootsWarns(): void
    {
        $this->write('src/Shop/Controllers/AddressController.php', "<?php\nnamespace Acme\\Shop\\Controllers;\nclass AddressController {}\n");

        $narrow = ProjectContext::discover([$this->root . '/src/Shop/Controllers'], [], new OrphanConfiguration());
        $whole  = ProjectContext::discover([$this->root . '/src'], [], new OrphanConfiguration());

        $this->assertNotNull(ScanScope::subtreeWarning($narrow));
        $this->assertNull(ScanScope::subtreeWarning($whole), 'a scan covering the autoload roots must stay quiet');
    }

    /**
     * The modular-monolith case, and the one `manifestApplies` cannot see. A scan
     * of `packages/` covers 60-odd psr-4 prefixes and looks complete, while the
     * manifest also maps `database/seeders` and `tests` — the top-level wiring
     * that references what those packages declare. Measured on the adopting
     * project: `packages/` alone reported 163 orphans against 158 for the full
     * set of autoload roots.
     */
    public function testAScanCoveringOnlySomeAutoloadRootsWarns(): void
    {
        $this->write('composer.json', '{"name":"acme/app","autoload":{"psr-4":{'
            . '"Acme\\\\Shop\\\\":"packages/Shop/src",'
            . '"Database\\\\Seeders\\\\":"database/seeders"}}}');

        $this->write('packages/Shop/src/Cart.php', "<?php\nnamespace Acme\\Shop;\nclass Cart {}\n");
        $this->write('database/seeders/DatabaseSeeder.php', "<?php\nnamespace Database\\Seeders;\nclass DatabaseSeeder {}\n");

        $partial = ProjectContext::discover([$this->root . '/packages'], [], new OrphanConfiguration());
        $whole   = ProjectContext::discover([$this->root], [], new OrphanConfiguration());

        // The partial scan still "applies" — one root is covered — which is why
        // this needs its own signal rather than riding on manifestApplies.
        $this->assertTrue($partial->manifestApplies);

        $warning = ScanScope::subtreeWarning($partial);

        $this->assertNotNull($warning);
        $this->assertStringContainsString('database/seeders', $warning);
        $this->assertNull(ScanScope::subtreeWarning($whole), 'a scan covering every autoload root must stay quiet');
    }

    /**
     * The real cause of the "definite orphan for a `use` + type-hint" report: the
     * file holding the reference was never scanned. A *generator* carries the
     * banner it writes into the file it emits, in a string literal, and the
     * content sniff read the file head as bytes. Dropping that file dropped every
     * reference it made, so a live repository reached the tier that gates CI.
     */
    public function testAGeneratorIsNotMistakenForGeneratedCode(): void
    {
        $this->write('src/Repositories/RuleRepository.php', "<?php\nnamespace Acme\\Repositories;\nclass RuleRepository {}\n");

        $this->write('src/Services/HtaccessWriter.php', <<<'PHP'
            <?php
            namespace Acme\Services;

            use Acme\Repositories\RuleRepository;

            class HtaccessWriter
            {
                public function __construct(protected RuleRepository $repository) {}

                public function render(): string
                {
                    return '# AUTO-GENERATED, do not edit by hand.';
                }
            }
            PHP);

        $finder = new FileFinder();
        $files  = $finder->find([$this->root], ['.php'], []);

        $this->assertCount(2, $files, 'a file that WRITES a generated banner is ordinary source');
        $this->assertSame(0, $finder->skippedGeneratedCount());
        $this->assertNotContains('RuleRepository', $this->deadNames());
    }

    /**
     * The other half: a real banner must still be caught, and prose that merely
     * mentions generated code must not be. All three cases are taken verbatim
     * from the adopting codebase.
     */
    public function testTheGeneratedSniffSeparatesBannersFromProse(): void
    {
        $this->write('src/Generated/Metrics.php', "<?php\n// Generated from metrics.afm. Do not edit by hand.\nnamespace Acme;\nclass Metrics {}\n");
        $this->write('src/Prose/OptionSync.php', "<?php\nnamespace Acme;\n\nclass OptionSync\n{\n    /** Marker prefix used to identify auto-generated options. */\n    public const P = 'x';\n}\n");
        $this->write('src/Prose/Migration.php', "<?php\n/**\n * Verified — only a generated type list referenced it.\n */\nnamespace Acme;\nclass Migration {}\n");

        $finder = new FileFinder();
        $files  = $finder->find([$this->root], ['.php'], []);

        $this->assertSame(1, $finder->skippedGeneratedCount(), 'exactly the banner file must be dropped');

        $names = array_map(static fn(string $f): string => basename($f), $files);

        $this->assertNotContains('Metrics.php', $names);
        $this->assertContains('OptionSync.php', $names);
        $this->assertContains('Migration.php', $names);
    }

    /**
     * The phrasing that needed a rule of its own. nikic/php-parser's generated
     * parsers announce themselves four words into a sentence, behind "This is
     * an", so no anchored marker reaches them; and "automatically generated" is
     * ordinary English, so a marker widened to reach them fires on WordPress's
     * `* automatically generated and user-created excerpts.` and drops a live
     * source file. Both halves are verbatim, and both must hold at once.
     */
    public function testTheGeneratedSniffReadsAnAnnouncingPhraseButNotADescribingOne(): void
    {
        $this->write('src/Parser/Php7.php', <<<'PHP'
            <?php
            namespace Acme\Parser;

            use Acme\Node;

            /* This is an automatically GENERATED file, which should not be manually edited.
             * Instead edit one of the following:
             *  * the grammar file grammar/php.y
             */
            class Php7 {}
            PHP);

        $this->write('src/Blocks/PostExcerpt.php', <<<'PHP'
            <?php
            namespace Acme\Blocks;

            class PostExcerpt
            {
                public function length(): int
                {
                    /*
                     * The purpose of the excerpt length setting is to limit the length of both
                     * automatically generated and user-created excerpts.
                     */
                    return 55;
                }
            }
            PHP);

        $this->write('src/Emitter/Tables.php', "<?php\n/**\n * Automatically generated file. Regenerate with bin/build.\n */\nnamespace Acme;\nclass Tables {}\n");

        $finder = new FileFinder();
        $files  = $finder->find([$this->root], ['.php'], []);
        $names  = array_map(static fn(string $f): string => basename($f), $files);

        $this->assertSame(2, $finder->skippedGeneratedCount(), 'the two banners, and only those');
        $this->assertNotContains('Php7.php', $names, 'a banner behind "This is an" still announces the file');
        $this->assertNotContains('Tables.php', $names, 'a banner naming the artifact announces it without a lead-in');
        $this->assertContains('PostExcerpt.php', $names, 'prose describing generated content is not a banner');
    }

    /**
     * The config and template maps are the last thing {@see OrphanDetector} asks
     * about, and the only thing it asks that costs a filesystem sweep. Sweeping
     * them at construction billed every run for an answer most runs never read —
     * on a corpus with no unreferenced symbols, nothing reached the lookup at all.
     *
     * Both halves of the fix are asserted here, because either alone is a defect:
     * a sweep that never defers is the old cost, and a sweep that defers without
     * memoizing turns one traversal into one per symbol.
     */
    public function testTheNameSweepsRunOnlyWhenConsultedAndOnlyOnce(): void
    {
        // Mutually referenced, so classification settles at the reference check.
        $this->write('src/Live.php', "<?php\nnamespace Acme;\nclass Live { public function go(): Wired { return new Wired(); } }\n");
        $this->write('src/Wired.php', "<?php\nnamespace Acme;\nclass Wired { public function back(): Live { return new Live(); } }\n");

        $sweeps  = ['config' => 0, 'template' => 0];
        $context = function () use (&$sweeps): ProjectContext {
            return new ProjectContext(
                configNames: new NameSweep(static function () use (&$sweeps): array {
                    $sweeps['config']++;

                    return ['Acme\\Registered' => 'services.yaml:3'];
                }),
                templateNames: new NameSweep(static function () use (&$sweeps): array {
                    $sweeps['template']++;

                    return [];
                }),
            );
        };

        $config = new OrphanConfiguration([$this->root]);

        // (a) Every symbol here is either referenced or the referring class, so
        // classification settles before the lookup: neither sweep may run.
        (new OrphanDetector())->detect($this->find(), null, $config, $context());

        $this->assertSame(['config' => 0, 'template' => 0], $sweeps, 'a scan that never reaches the lookup must not sweep');

        // (b) Two unreferenced symbols now reach the lookup. Each sweep runs once,
        // however many symbols consult it.
        $this->write('src/OrphanA.php', "<?php\nnamespace Acme;\nclass OrphanA {}\n");
        $this->write('src/OrphanB.php', "<?php\nnamespace Acme;\nclass OrphanB {}\n");

        $sweeps = ['config' => 0, 'template' => 0];

        (new OrphanDetector())->detect($this->find(), null, $config, $context());

        $this->assertSame(['config' => 1, 'template' => 1], $sweeps, 'each sweep must run exactly once per context');
    }

    /**
     * Deferring changes WHEN the sweep happens, never WHAT it reports: a symbol
     * named only in a config file is still suppressed rather than reported dead,
     * and the evidence still names the file and line it was found at.
     */
    public function testALazilySweptConfigNameStillSuppressesTheSymbol(): void
    {
        $this->write('src/Handlers/PruneHandler.php', "<?php\nnamespace Acme\\Handlers;\nclass PruneHandler {}\n");
        $this->write('services.yaml', "services:\n    prune:\n        class: Acme\\Handlers\\PruneHandler\n");

        $this->assertNotContains('PruneHandler', $this->deadNames());
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The short names reported in the tier that gates CI. Asserting on this list
     * rather than on a count keeps a failure readable: it names the symbol the
     * tool would have told someone to delete.
     *
     * @param list<string> $excludes
     * @return list<string>
     */
    private function deadNames(array $excludes = []): array
    {
        $config = new OrphanConfiguration([$this->root]);
        $result = (new OrphanDetector())->detect(
            $this->find($excludes),
            null,
            $config,
            ProjectContext::discover([$this->root], $excludes, $config),
        );

        return $this->namesOf($result);
    }

    /** @return list<string> */
    private function namesOf(OrphanResult $result): array
    {
        $names = [];

        foreach ($result->entries() as $orphan) {
            if ($orphan->confidence === Orphan::CONFIDENCE_DEAD) {
                $names[] = $orphan->symbol->name;
            }
        }

        return $names;
    }

    /**
     * @param list<string> $excludes
     * @return list<string>
     */
    private function find(array $excludes = []): array
    {
        return (new FileFinder())->find([$this->root], ['.php'], $excludes);
    }

    /**
     * Run $probe with the fixture root as the working directory, restoring the
     * caller's afterwards even when the probe throws.
     *
     * @template T
     * @param callable(): T $probe
     * @return T
     */
    private function inFixtureRoot(callable $probe): mixed
    {
        $cwd = getcwd();

        self::assertNotFalse($cwd);
        self::assertTrue(chdir($this->root));

        try {
            return $probe();
        } finally {
            chdir($cwd);
        }
    }
}
