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
use function sort;

use LucianoPereira\PhpcpdNext\Orphan\Orphan;
use LucianoPereira\PhpcpdNext\Orphan\OrphanConfiguration;
use LucianoPereira\PhpcpdNext\Orphan\OrphanDetector;
use LucianoPereira\PhpcpdNext\Orphan\OrphanResult;
use LucianoPereira\PhpcpdNext\Orphan\ProjectContext;
use LucianoPereira\PhpcpdNext\Util\FileFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `tests/fixtures/orphans/` states its own answers, and nothing checked them.
 *
 * Each file there carries a comment saying which tier it belongs in —
 * "Nothing references this anywhere → a definite orphan", "possible orphan, not
 * a safe delete" — so the directory is an acceptance specification for all four
 * tiers, written out and then never run. The tool satisfies it exactly; this is
 * what says so tomorrow.
 */
#[CoversClass(OrphanDetector::class)]
final class OrphanTiersFixtureTest extends TestCase
{
    private const string SRC = __DIR__ . '/../fixtures/orphans/src';

    /** Every tier at once, because the interesting property is the split. */
    #[Test]
    public function theFixtureLandsInTheTiersItDeclares(): void
    {
        $result = self::scan(self::SRC);

        self::assertSame(['Demo\AbandonedClass'], self::names($result->definite()));

        self::assertSame(
            ['Demo\DynamicWidget', 'Demo\PaymentContract'],
            self::names($result->possible()),
            'a name in a string literal and an unimplemented interface are both "review before removing"',
        );

        self::assertSame(
            ['Demo\ImportCommand', 'Demo\KeptApi', 'Demo\OrphanWidgetTest', 'Demo\UsedService'],
            self::names($result->suppressed()),
        );
    }

    /**
     * `Helper` is referenced by `UsedService::run()` and must not appear at all —
     * the one symbol here that is plainly alive.
     */
    #[Test]
    public function aReferencedClassIsNotReported(): void
    {
        self::assertNotContains('Demo\Helper', self::names(self::scan(self::SRC)->all()));
    }

    /**
     * `DynamicWidget` is named only inside a string literal in `UsedService`,
     * and the finding has to say where — a "possible" verdict a reader cannot
     * check is not reviewable.
     */
    #[Test]
    public function thePossibleVerdictNamesTheEvidence(): void
    {
        foreach (self::scan(self::SRC)->possible() as $orphan) {
            if ($orphan->symbol->fqn === 'Demo\DynamicWidget') {
                self::assertNotNull($orphan->evidence, 'the string-literal sighting is cited');

                return;
            }
        }

        self::fail('DynamicWidget was not reported as a possible orphan');
    }

    /**
     * A global function whose only caller is itself. The fixture states the
     * rule: the recursive call counts as a reference, so the detector stays
     * silent, because deleting it would break the recursion.
     */
    #[Test]
    public function aFunctionThatOnlyCallsItselfIsNotDead(): void
    {
        $result = self::scan(__DIR__ . '/../fixtures/orphans/recursion.php');

        self::assertSame([], self::names($result->definite()), 'recursion is a reference');
    }

    /**
     * `tests/fixtures/suppression/project/` is a whole project built to exercise
     * the suppression rules, and it was never run either. Seven rules fire on
     * it, each citing where the claim can be checked.
     */
    #[Test]
    public function everySuppressionRuleTheFixtureProjectExercisesStillFires(): void
    {
        $result = self::scan(__DIR__ . '/../fixtures/suppression/project');

        $byRule = [];

        foreach ($result->suppressed() as $orphan) {
            $byRule[$orphan->rule ?? '?'][] = $orphan->symbol->fqn;
        }

        foreach (['conditional', 'namespace', 'keep', 'config', 'manifest', 'fixtures'] as $rule) {
            self::assertArrayHasKey($rule, $byRule, $rule . ' suppressed nothing');
        }

        self::assertSame(['Demo\App\Support\Spinner'], self::names($result->planned()));
    }

    /**
     * The `Prose` class's docblock *mentions* `@api` in a sentence — "Unlike
     * @api classes, this one is internal and nothing uses it". A keep rule that
     * searched for the tag rather than read it would suppress a dead class on
     * the strength of prose about other classes.
     */
    #[Test]
    public function aDocblockThatTalksAboutTheKeepTagIsNotMarkedWithIt(): void
    {
        $result = self::scan(__DIR__ . '/../fixtures/suppression/project');

        self::assertContains('Demo\App\Support\Prose', self::names($result->definite()));
    }

    /**
     * A config-file registration is a claim about a file the reader has not
     * opened, so it has to say which one.
     */
    #[Test]
    public function aConfigSuppressionNamesTheFileThatWiredIt(): void
    {
        foreach (self::scan(__DIR__ . '/../fixtures/suppression/project')->suppressed() as $orphan) {
            if ($orphan->symbol->fqn === 'Demo\App\Support\NeonWired') {
                self::assertNotNull($orphan->evidence);
                self::assertStringContainsString('services.neon', (string) $orphan->evidence);

                return;
            }
        }

        self::fail('NeonWired was not suppressed by the config rule');
    }

    private static function scan(string $root): OrphanResult
    {
        $config = new OrphanConfiguration([$root]);

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
    private static function names(array $orphans): array
    {
        $names = array_map(static fn(Orphan $orphan): string => $orphan->symbol->fqn, $orphans);
        sort($names);

        return $names;
    }
}
