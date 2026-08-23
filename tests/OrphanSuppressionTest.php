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
use function count;
use function str_contains;

use LucianoPereira\PhpcpdNext\Orphan\ComposerManifest;
use LucianoPereira\PhpcpdNext\Orphan\FqnScanner;
use LucianoPereira\PhpcpdNext\Orphan\Orphan;
use LucianoPereira\PhpcpdNext\Orphan\OrphanConfiguration;
use LucianoPereira\PhpcpdNext\Orphan\OrphanResult;
use LucianoPereira\PhpcpdNext\Orphan\ProjectContext;
use LucianoPereira\PhpcpdNext\Orphan\Rule;
use LucianoPereira\PhpcpdNext\Orphans;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The suppression rules, exercised against one synthetic project that contains a
 * live example of each: a polyfill behind an existence guard, a shim in a
 * foreign namespace, classes wired from neon and from composer's extra block, a
 * fixture under a test tree, and the two docblock tags.
 *
 * Every assertion pairs a rule with the symbol it must account for AND checks
 * that the residual "no reference found" group still holds the genuinely dead
 * classes — a suppression that swallowed those would be the failure worth
 * catching.
 */
#[CoversClass(ComposerManifest::class)]
#[CoversClass(FqnScanner::class)]
#[CoversClass(OrphanConfiguration::class)]
#[CoversClass(ProjectContext::class)]
#[CoversClass(Rule::class)]
final class OrphanSuppressionTest extends TestCase
{
    private const string PROJECT = __DIR__ . '/fixtures/suppression/project';

    #[Test]
    public function a_declaration_behind_an_existence_guard_is_conditional(): void
    {
        self::assertSame(Rule::CONDITIONAL, $this->ruleFor('demo_array_find_key'));
    }

    #[Test]
    public function a_symbol_outside_the_declared_psr4_prefixes_is_a_compatibility_shim(): void
    {
        self::assertSame(Rule::NAMESPACE_PREFIX, $this->ruleFor('Other\\Vendor\\Shimmed'));
    }

    #[Test]
    public function a_class_named_in_a_neon_file_is_registered_in_config(): void
    {
        self::assertSame(Rule::CONFIG, $this->ruleFor('Demo\\App\\Support\\NeonWired'));
    }

    #[Test]
    public function a_class_named_in_composer_extra_is_registered_in_the_manifest(): void
    {
        self::assertSame(Rule::MANIFEST, $this->ruleFor('Demo\\App\\Support\\ProviderWired'));
    }

    #[Test]
    public function a_declaration_in_an_autoload_files_entry_point_is_never_orphaned(): void
    {
        self::assertSame(Rule::MANIFEST, $this->ruleFor('Demo\\App\\demo_helper'));
    }

    #[Test]
    public function a_fixture_under_a_test_tree_is_suppressed(): void
    {
        self::assertSame(Rule::FIXTURES, $this->ruleFor('Demo\\App\\Tests\\Fixtures\\Sample'));
    }

    #[Test]
    public function a_class_instantiated_only_from_an_extensionless_bin_entry_point_is_live(): void
    {
        self::assertNotContains('Demo\\App\\Kernel', $this->fqns($this->scan()->entries()));
    }

    #[Test]
    public function a_keep_tag_carries_its_reason_into_the_report(): void
    {
        $kept = $this->find('Demo\\App\\Support\\Kept');

        self::assertSame(Rule::KEEP, $kept->rule);
        self::assertSame('Registered in config/services.neon', $kept->reason);
    }

    #[Test]
    public function a_tag_named_in_prose_does_not_suppress(): void
    {
        // "Unlike @api classes, this one is internal" must not read as @api.
        self::assertSame(Orphan::CONFIDENCE_DEAD, $this->find('Demo\\App\\Support\\Prose')->confidence);
    }

    #[Test]
    public function planned_code_is_its_own_tier_rather_than_a_finding(): void
    {
        $spinner = $this->find('Demo\\App\\Support\\Spinner');

        self::assertSame(Orphan::CONFIDENCE_PLANNED, $spinner->confidence);
        self::assertSame('Wired by the console rework.', $spinner->reason);
        self::assertNotContains('Demo\\App\\Support\\Spinner', $this->fqns($this->scan()->all()));
    }

    #[Test]
    public function a_string_literal_demotion_cites_where_the_name_appears(): void
    {
        $dynamic = $this->find('Demo\\App\\Support\\Dynamic');

        self::assertSame(Orphan::CONFIDENCE_POSSIBLE, $dynamic->confidence);
        self::assertNotNull($dynamic->evidence);
        self::assertTrue(str_contains((string) $dynamic->evidence, 'Registry.php:'));
    }

    #[Test]
    public function genuinely_dead_classes_survive_every_rule(): void
    {
        self::assertContains('Demo\\App\\Support\\Abandoned', $this->fqns($this->scan()->definite()));
    }

    #[Test]
    public function disabling_a_rule_reclassifies_its_symbols_instead_of_hiding_them(): void
    {
        $withRule    = $this->find('Demo\\App\\Tests\\Fixtures\\Sample');
        $withoutRule = $this->find('Demo\\App\\Tests\\Fixtures\\Sample', ['fixtures']);

        self::assertSame(Orphan::CONFIDENCE_SUPPRESSED, $withRule->confidence);
        self::assertSame(Orphan::CONFIDENCE_DEAD, $withoutRule->confidence);
    }

    #[Test]
    public function no_suppress_all_turns_every_rule_off(): void
    {
        self::assertSame([], $this->scan(['all'])->suppressed());
    }

    #[Test]
    public function suppressed_symbols_are_kept_in_the_result_rather_than_dropped(): void
    {
        $result = $this->scan();

        self::assertNotSame([], $result->suppressed());
        self::assertGreaterThan(count($result->all()), count($result->entries()));
    }

    #[Test]
    public function only_the_tiers_named_in_fail_on_gate_the_run(): void
    {
        $result = $this->scan();

        self::assertFalse($result->fails(new OrphanConfiguration(failOn: [])));
        self::assertTrue($result->fails(new OrphanConfiguration(failOn: [OrphanConfiguration::TIER_DEAD])));
        self::assertTrue($result->fails(new OrphanConfiguration(failOn: [OrphanConfiguration::TIER_PLANNED])));
    }

    #[Test]
    public function pointing_the_scan_at_a_fixture_directory_makes_it_ordinary_code(): void
    {
        // The rule judges paths relative to the roots, so a scan aimed straight
        // at a fixture tree reports its contents instead of suppressing them.
        $result = Orphans::detect(self::PROJECT . '/tests/unit/Fixtures');

        self::assertContains('Demo\\App\\Tests\\Fixtures\\Sample', $this->fqns($result->definite()));
    }

    /** @param list<string> $noSuppress */
    private function scan(array $noSuppress = []): OrphanResult
    {
        return Orphans::detect(self::PROJECT, noSuppress: $noSuppress);
    }

    /** @param list<string> $noSuppress */
    private function find(string $fqn, array $noSuppress = []): Orphan
    {
        foreach ($this->scan($noSuppress)->entries() as $entry) {
            if ($entry->symbol->fqn === $fqn) {
                return $entry;
            }
        }

        self::fail('No entry for ' . $fqn);
    }

    private function ruleFor(string $fqn): ?string
    {
        return $this->find($fqn)->rule;
    }

    /**
     * @param list<Orphan> $orphans
     * @return list<string>
     */
    private function fqns(array $orphans): array
    {
        return array_map(static fn(Orphan $o): string => $o->symbol->fqn, $orphans);
    }
}
