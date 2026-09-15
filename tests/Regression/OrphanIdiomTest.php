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

use function count;
use function realpath;
use function sort;

use LucianoPereira\PhpcpdNext\Orphan\IdiomIndex;
use LucianoPereira\PhpcpdNext\Orphan\IdiomScanner;
use LucianoPereira\PhpcpdNext\Orphan\Orphan;
use LucianoPereira\PhpcpdNext\Orphan\OrphanConfiguration;
use LucianoPereira\PhpcpdNext\Orphan\OrphanDetector;
use LucianoPereira\PhpcpdNext\Orphan\OrphanResult;
use LucianoPereira\PhpcpdNext\Orphan\ProjectContext;
use LucianoPereira\PhpcpdNext\Orphan\Rule;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use LucianoPereira\PhpcpdNext\Tests\BuildsAFixtureProject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The constructed-name family: symbols whose class name is never written down,
 * because it is assembled from a filename or from a base class plus a suffix.
 *
 * Measured on a live Laravel monolith, these accounted for 64 of 94 reported
 * dead symbols — 68% false positives, every one of which would have deleted
 * wired code. What makes them hard is not finding them; it is that a rule which
 * suppresses too broadly is indistinguishable from one that works, since both
 * make the count go down.
 *
 * So every fixture here pairs the cluster that must be spared with a
 * KNOWN-POSITIVE that must still be reported, and asserts the tier COUNTS as
 * well as the names: a rule that silently widens shows up as a count that moved,
 * not as a test that still passes.
 */
#[CoversClass(IdiomIndex::class)]
#[CoversClass(IdiomScanner::class)]
#[CoversClass(OrphanDetector::class)]
final class OrphanIdiomTest extends TestCase
{
    use BuildsAFixtureProject;

    /**
     * The subtree a scan is pointed at — the whole fixture unless a cluster needs
     * the tool run the way a user runs it, at `src/` with `config/` outside.
     *
     * @var non-empty-string
     */
    private string $scanRoot = 'unset';

    protected function setUp(): void
    {
        $this->makeFixtureRoot('idiom');

        $this->scanRoot = $this->root;

        $this->write('composer.json', '{"name":"neutral/app","autoload":{"psr-4":{"Neutral\\\\":"src/"}}}');
    }

    protected function tearDown(): void
    {
        $this->delete($this->root);
    }

    /**
     * Pattern A. A base class globs its own directory, derives a name from each
     * filename and instantiates it behind `class_exists`; a front controller
     * dispatches through the map it builds. No discovered class is named
     * anywhere, so every one of them read as definitely dead — 52 of them in a
     * single directory on the audited project, where acting on the finding
     * removes a live endpoint.
     *
     * The paired known-positive is the whole test: a class in the SAME directory
     * that the loop's glob does not select must still be reported. A rule keyed
     * on the directory alone would swallow it and still look like it worked.
     */
    public function testDiscoveredClassesAreSparedAndTheirUnreachedNeighbourIsNot(): void
    {
        $this->writeDiscoveryCluster();

        $this->assertSame(['AbandonedRoutine'], $this->deadNames());
        $this->assertSame(
            ['AlphaHandler', 'BetaHandler', 'GammaHandler'],
            $this->suppressedNames(Rule::DISCOVERY),
        );

        // The registry itself is not selected by its own glob, so the rule does
        // not account for it: it lands in `possible` as an abstract contract,
        // which is what it is. A rule keyed on the directory would have swallowed
        // it too, and the difference would not have been visible.
        $this->assertSame(['HandlerRegistry'], $this->namesIn($this->scan()->possible()));

        // Counts, not just names: a rule that widens moves these even when the
        // name assertions above are written loosely enough to survive it.
        $this->assertSame(1, count($this->scan()->definite()));
        $this->assertSame(3, count($this->tier(Rule::DISCOVERY)));
    }

    /**
     * The evidence has to name the discovering loop, not merely the rule. A user
     * who disagrees with a suppression needs the file and line of the code that
     * claims to wire the symbol, or the rule is unfalsifiable.
     */
    public function testTheDiscoveryEvidenceNamesTheLoop(): void
    {
        $this->writeDiscoveryCluster();

        foreach ($this->tier(Rule::DISCOVERY) as $orphan) {
            $this->assertSame(
                $this->root . '/src/Handlers/HandlerRegistry.php:13',
                $orphan->evidence,
                'the evidence must be the file:line of the glob that discovers the class',
            );
        }
    }

    /**
     * Any one or two of the three signals is ordinary code. A file that globs
     * `__DIR__` and instantiates through a variable but never guards with
     * `class_exists` is not the discovery idiom, and its directory must be judged
     * normally — otherwise the rule is "this file mentions glob", which suppresses
     * directories nothing discovers.
     */
    public function testTwoOfTheThreeSignalsDoNotSuppressADirectory(): void
    {
        $this->write('src/Partial/PartialLister.php', <<<'PHP'
            <?php
            namespace Neutral\Partial;

            class PartialLister
            {
                /** Globs and instantiates dynamically, but guards nothing. */
                public function all(): array
                {
                    $found = [];

                    foreach (glob(__DIR__ . '/*Item.php') ?: [] as $path) {
                        $class   = __NAMESPACE__ . '\\' . basename($path, '.php');
                        $found[] = new $class();
                    }

                    return $found;
                }
            }
            PHP);

        $this->write('src/Partial/FirstItem.php', "<?php\nnamespace Neutral\\Partial;\nclass FirstItem {}\n");

        $this->assertSame(['FirstItem', 'PartialLister'], $this->deadNames());
        $this->assertSame([], $this->suppressedNames(Rule::DISCOVERY));
    }

    /**
     * A glob over a path that is not built from `__DIR__` is out of scope by
     * design: with no anchor there is nothing in the token stream that says which
     * directory on disk the loop reads, so the rule would be suppressing a
     * directory it cannot name.
     */
    public function testAGlobOverAnUnanchoredPathDoesNotSuppress(): void
    {
        $this->write('src/Configured/ConfiguredRegistry.php', <<<'PHP'
            <?php
            namespace Neutral\Configured;

            class ConfiguredRegistry
            {
                public function all(string $directory): array
                {
                    $found = [];

                    foreach (glob($directory . '/*Task.php') ?: [] as $path) {
                        $class = __NAMESPACE__ . '\\' . basename($path, '.php');

                        if (class_exists($class)) {
                            $found[] = new $class();
                        }
                    }

                    return $found;
                }
            }
            PHP);

        $this->write('src/Configured/PruneTask.php', "<?php\nnamespace Neutral\\Configured;\nclass PruneTask {}\n");

        $this->assertSame(['ConfiguredRegistry', 'PruneTask'], $this->deadNames());
        $this->assertSame([], $this->suppressedNames(Rule::DISCOVERY));
    }

    /**
     * Turning the rule off must judge the symbols normally rather than skip them
     * — that is what makes `--no-suppress` a way to audit the rule itself, and it
     * is the only way to see what the rule is actually costing.
     */
    public function testDisablingTheRuleRestoresEveryFinding(): void
    {
        $this->writeDiscoveryCluster();

        $this->assertSame(
            ['AbandonedRoutine', 'AlphaHandler', 'BetaHandler', 'GammaHandler'],
            $this->deadNames([Rule::DISCOVERY]),
        );
        $this->assertSame([], $this->suppressedNames(Rule::DISCOVERY, [Rule::DISCOVERY]));
    }

    /** Two runs over the same tree must produce byte-identical verdicts. */
    public function testTheDiscoveryVerdictIsDeterministic(): void
    {
        $this->writeDiscoveryCluster();

        $this->assertSame($this->transcript(), $this->transcript());
    }

    /**
     * Pattern B. A trait resolves a companion class by appending a literal
     * suffix to its consumer's own runtime class name, so the companion is named
     * nowhere and reads as dead — 12 of them on the audited project.
     *
     * Two known-positives, because the rule has two halves and either one
     * dropped turns it into "a class ending in Translation":
     *
     *   - `WidgetTranslation`, whose base class `Widget` does not exist;
     *   - `GadgetTranslation`, whose base `Gadget` exists but does not use the
     *     trait that declares the suffix.
     *
     * Both must still be reported. A rule keyed on the suffix alone would spare
     * all three and look exactly as effective.
     */
    public function testConventionCompanionsAreSparedAndLookalikesAreNot(): void
    {
        $this->writeConventionCluster();

        $this->assertSame(['GadgetTranslation', 'WidgetTranslation'], $this->deadNames());
        $this->assertSame(['ArticleTranslation'], $this->suppressedNames(Rule::CONVENTION));

        $this->assertSame(2, count($this->scan()->definite()));
        $this->assertSame(1, count($this->tier(Rule::CONVENTION)));
    }

    /** The evidence must be the concatenation site inside the declaring trait. */
    public function testTheConventionEvidenceNamesTheDeclaringTrait(): void
    {
        $this->writeConventionCluster();

        $spared = $this->tier(Rule::CONVENTION)[0];

        $this->assertSame($this->root . '/src/Support/Translatable.php:9', $spared->evidence);
        $this->assertStringContainsString('Neutral\Support\Translatable', $spared->reason);
    }

    /**
     * The `config()` shape of the same idiom: the suffix is a literal DEFAULT
     * rather than a literal operand. It is still a literal in the source, so it
     * is still registrable.
     */
    public function testASuffixTakenFromAConfigDefaultIsRegistered(): void
    {
        $this->writeConventionCluster(<<<'PHP'
                    return static::class . config('neutral.companion_suffix', 'Translation');
            PHP);

        $this->assertSame(['ArticleTranslation'], $this->suppressedNames(Rule::CONVENTION));
    }

    /**
     * The other side of that boundary: a suffix with no literal anywhere is out
     * of scope. `config('neutral.companion_suffix')` resolves out of a value this
     * tool never reads, so there is nothing to register — and inventing one would
     * mean suppressing by class-name pattern, which is what every rule here
     * exists to avoid.
     */
    public function testASuffixWithNoLiteralDefaultIsNotRegistered(): void
    {
        $this->writeConventionCluster(<<<'PHP'
                    return static::class . config('neutral.companion_suffix');
            PHP);

        $this->assertSame([], $this->suppressedNames(Rule::CONVENTION));
        $this->assertContains('ArticleTranslation', $this->deadNames());
    }

    /** Turning the rule off must put every companion back as an ordinary finding. */
    public function testDisablingTheConventionRuleRestoresEveryFinding(): void
    {
        $this->writeConventionCluster();

        $this->assertSame(
            ['ArticleTranslation', 'GadgetTranslation', 'WidgetTranslation'],
            $this->deadNames([Rule::CONVENTION]),
        );
    }

    /**
     * Pattern C. Laravel's native config format is PHP — `config/*.php` returns
     * an array of `Provider::class` entries and quoted fully-qualified names —
     * and `.php` was absent from the config sweep's suffix list. Measured on the
     * audited monolith, the config rule suppressed exactly ONE symbol repo-wide
     * while the wiring it should have found sat in those arrays.
     *
     * The paired known-positive is a class in the same package that no config
     * file names: it must stay reported, or the rule is "anything in a package
     * that has a config file".
     */
    public function testClassesWiredInPhpConfigAreSparedAndTheirNeighbourIsNot(): void
    {
        $this->writeConfigCluster();

        $this->assertSame(['NeverWiredService'], $this->deadNames());
        $this->assertSame(
            ['QueueService', 'ReportService'],
            $this->suppressedNames(Rule::CONFIG),
        );

        $this->assertSame(1, count($this->scan()->definite()));
        $this->assertSame(2, count($this->tier(Rule::CONFIG)));
    }

    /**
     * Both citation styles a PHP config file uses resolve, and each cites the
     * config entry it was found at. The tier split is what users act on: without
     * this these are "possible" string mentions, which is the tier people skim.
     */
    public function testThePhpConfigEvidenceNamesTheConfigEntry(): void
    {
        $this->writeConfigCluster();

        $evidence = [];

        foreach ($this->tier(Rule::CONFIG) as $orphan) {
            $evidence[$orphan->symbol->name] = $orphan->evidence;
        }

        // The config sweep resolves its roots from composer.json's location, so
        // the citation is the canonical path — on macOS that differs from the
        // temp path by a /private prefix.
        $canonical = realpath($this->root);

        $this->assertSame([
            'QueueService'  => $canonical . '/config/services.php:8',
            'ReportService' => $canonical . '/config/services.php:7',
        ], $evidence);
    }

    /**
     * The boundary, and the reason it is the rule rather than an optimisation.
     * An ordinary source file that happens to mention a class in a string is not
     * configuration: it is already read as source, where the mention is scored as
     * the weak signal it is. Sweeping every `.php` file as config would promote
     * every string literal in the project to a config registration.
     */
    public function testAPhpFileOutsideAConfigDirectoryIsNotSweptAsConfig(): void
    {
        $this->writeConfigCluster();

        // Same content as the config file, in a directory that is not config/.
        $this->write('src/Wiring/Notes.php', <<<'PHP'
            <?php
            namespace Neutral\Wiring;

            /** @api */
            class Notes
            {
                public const string TODO = 'Neutral\Services\NeverWiredService may go away.';
            }
            PHP);

        $this->assertSame(['NeverWiredService'], $this->deadNames());
        $this->assertSame(['QueueService', 'ReportService'], $this->suppressedNames(Rule::CONFIG));
    }

    /** Turning the rule off must put the config-wired symbols back. */
    public function testDisablingTheConfigRuleRestoresEveryFinding(): void
    {
        $this->writeConfigCluster();

        $this->assertSame(
            ['NeverWiredService', 'QueueService', 'ReportService'],
            $this->deadNames([Rule::CONFIG]),
        );
    }

    /** Two runs over the same tree must produce byte-identical verdicts. */
    public function testThePhpConfigVerdictIsDeterministic(): void
    {
        $this->writeConfigCluster();

        $this->assertSame($this->transcript(), $this->transcript());
    }

    // --------------------------------------------------------------- fixtures

    /**
     * Pattern A, with neutral names: a registry that discovers `*Handler.php`
     * beside itself, three handlers it reaches, and one class in the same
     * directory it does not.
     */
    private function writeDiscoveryCluster(): void
    {
        // The glob sits on line 13 — asserted as evidence, so keep it there.
        $this->write('src/Handlers/HandlerRegistry.php', <<<'PHP'
            <?php
            namespace Neutral\Handlers;

            /**
             * Builds the handler map the front controller dispatches through.
             */
            abstract class HandlerRegistry
            {
                public static function map(): array
                {
                    $map = [];

                    foreach (glob(__DIR__ . '/*Handler.php') ?: [] as $path) {
                        $class = __NAMESPACE__ . '\\' . basename($path, '.php');

                        if (class_exists($class)) {
                            $map[$class::key()] = new $class();
                        }
                    }

                    return $map;
                }
            }
            PHP);

        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $this->write("src/Handlers/{$name}Handler.php", <<<PHP
                <?php
                namespace Neutral\\Handlers;

                class {$name}Handler
                {
                    public static function key(): string { return '{$name}'; }

                    public function handle(): string { return '{$name}'; }
                }
                PHP);
        }

        // The known-positive: same directory, not selected by the loop's glob.
        $this->write('src/Handlers/AbandonedRoutine.php', <<<'PHP'
            <?php
            namespace Neutral\Handlers;

            class AbandonedRoutine
            {
                public function run(): void {}
            }
            PHP);
    }

    /**
     * Pattern B, with neutral names: a trait that names a companion by suffix,
     * a model that uses it, and two lookalike companions that must stay dead.
     *
     * @param ?string $resolver the body of the trait's resolver, when a test
     *                          needs a different shape of the same idiom
     */
    private function writeConventionCluster(?string $resolver = null): void
    {
        // The concatenation sits on line 9 — asserted as evidence, so keep the
        // resolver body on that line.
        $resolver ??= <<<'PHP'
                    return static::class . 'Translation';
            PHP;

        $this->write('src/Support/Translatable.php', <<<PHP
            <?php
            namespace Neutral\\Support;

            trait Translatable
            {
                /** The companion class this model's translations live in. */
                public function companionClass(): string
                {
            {$resolver}
                }
            }
            PHP);

        $this->write('src/Content/Article.php', <<<'PHP'
            <?php
            namespace Neutral\Content;

            use Neutral\Support\Translatable;

            class Article
            {
                use Translatable;
            }
            PHP);

        $this->write('src/Content/ArticleTranslation.php', "<?php\nnamespace Neutral\\Content;\nclass ArticleTranslation {}\n");

        // Known-positive 1: the same suffix, but no class named Widget exists.
        $this->write('src/Content/WidgetTranslation.php', "<?php\nnamespace Neutral\\Content;\nclass WidgetTranslation {}\n");

        // Known-positive 2: Gadget exists, but does not use the trait.
        $this->write('src/Content/Gadget.php', "<?php\nnamespace Neutral\\Content;\nclass Gadget { public function id(): int { return 1; } }\n");
        $this->write('src/Content/GadgetTranslation.php', "<?php\nnamespace Neutral\\Content;\nclass GadgetTranslation {}\n");

        // Gadget must be referenced, or it joins the findings and buries the
        // point of the fixture.
        $this->write('src/Content/Catalogue.php', <<<'PHP'
            <?php
            namespace Neutral\Content;

            /** @api the fixture's entry point, so its own deadness is not the subject */
            class Catalogue
            {
                public function first(): Gadget { return new Gadget(); }

                public function article(): Article { return new Article(); }
            }
            PHP);
    }

    /**
     * Pattern C, with neutral names: two services wired from a PHP config array
     * — one by `::class`, one by a quoted FQN — and one that nothing names.
     *
     * The scan is pointed at `src/` alone, which is both how the tool is normally
     * run and what makes the fixture meaningful: `config/` is outside the scanned
     * source, so its `::class` entries are not code references the collector can
     * see. Scanning the whole tree would make the config file ordinary source and
     * settle the question before the config rule was ever asked.
     */
    private function writeConfigCluster(): void
    {
        $this->scanRoot = $this->root . '/src';

        // Entries on lines 7 and 8 — asserted as evidence, so keep them there.
        $this->write('config/services.php', <<<'PHP'
            <?php

            // Wiring for the service container.

            return [
                'handlers' => [
                    'report' => \Neutral\Services\ReportService::class,
                    'queue'  => 'Neutral\Services\QueueService',
                ],
            ];
            PHP);

        foreach (['ReportService', 'QueueService', 'NeverWiredService'] as $name) {
            $this->write("src/Services/{$name}.php", <<<PHP
                <?php
                namespace Neutral\\Services;

                class {$name}
                {
                    public function handle(): void {}
                }
                PHP);
        }
    }

    // ---------------------------------------------------------------- helpers

    /** @param list<string> $disabled */
    private function scan(array $disabled = []): OrphanResult
    {
        return $this->scanIn($this->scanRoot, $disabled);
    }

    /**
     * A scan pointed at ONE subtree, the way a user runs `phpcpd --orphans src/`.
     * The distinction matters for the config rule: `config/` sits outside the
     * scanned source, so the wiring in it is invisible to reference detection and
     * has to be reached through the config sweep instead.
     *
     * @param list<string> $disabled
     */
    private function scanIn(string $root, array $disabled = []): OrphanResult
    {
        $config = new OrphanConfiguration([$root], $disabled);

        return (new OrphanDetector())->detect(
            (new FileFinder())->find([$root], ['.php'], []),
            null,
            $config,
            ProjectContext::discover([$root], [], $config),
        );
    }

    /**
     * @param list<Orphan> $orphans
     * @return list<string>
     */
    private function namesIn(array $orphans): array
    {
        $names = [];

        foreach ($orphans as $orphan) {
            $names[] = $orphan->symbol->name;
        }

        sort($names);

        return $names;
    }

    /**
     * The names reported in the tier that gates CI, sorted. Asserting the list
     * rather than a count keeps a failure readable: it names the symbol the tool
     * would have told someone to delete.
     *
     * @param list<string> $disabled
     * @return list<string>
     */
    private function deadNames(array $disabled = []): array
    {
        return $this->namesIn($this->scan($disabled)->definite());
    }

    /**
     * @param list<string> $disabled
     * @return list<Orphan> the suppressed entries a given rule accounts for
     */
    private function tier(string $rule, array $disabled = []): array
    {
        $entries = [];

        foreach ($this->scan($disabled)->suppressed() as $orphan) {
            if ($orphan->rule === $rule) {
                $entries[] = $orphan;
            }
        }

        return $entries;
    }

    /**
     * @param list<string> $disabled
     * @return list<string>
     */
    private function suppressedNames(string $rule, array $disabled = []): array
    {
        return $this->namesIn($this->tier($rule, $disabled));
    }

    /**
     * Every verdict as one comparable string, in the order the detector produced
     * them — so a determinism check catches a reordering as well as a changed
     * classification.
     *
     * @return list<string>
     */
    private function transcript(): array
    {
        $lines = [];

        foreach ($this->scan()->entries() as $orphan) {
            $lines[] = $orphan->symbol->fqn . '|' . $orphan->confidence . '|'
                . ($orphan->rule ?? '-') . '|' . ($orphan->evidence ?? '-');
        }

        return $lines;
    }
}
