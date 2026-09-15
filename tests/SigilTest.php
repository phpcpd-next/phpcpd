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

use function bcb_timing_reasons;
use function sigil_bare_numbers;
use function sigil_corroborates;
use function sigil_frozen_numbers;
use function sigil_group_loose;
use function sigil_restatements;
use function sigil_is_frozen;
use function sigil_parse_facts;
use function sigil_render;
use function sigil_resolve;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SigilError;

require_once __DIR__ . '/../bench/sigil-parse.php';
require_once __DIR__ . '/../bench/power.php';

/**
 * Documents that cannot state a stale number, and a gate that cannot assert a
 * timing the machine could not produce.
 *
 * Both halves exist because the same failure happened twice. A number typed
 * into prose was true when it was typed and silently stopped being true: the
 * php-parser file count moved from 342 to 340 the moment file discovery
 * learned to read one more banner, and nothing in the repository noticed. And
 * a wall-clock gate on a battery-throttled CPU reported 0.35x and FAIL — a
 * verdict about the power policy wearing the costume of a verdict about the
 * engine.
 *
 * So the tests here are two-way in the same sense the scan tests are: a
 * document that disagrees with a measurement must fail, a document that agrees
 * must pass, a machine that cannot deliver must be refused, and one that can
 * must not be.
 */
final class SigilTest extends TestCase
{
    private const string FACTS = <<<'TOML'
        # a comment, and a blank line follow

        [scan]
        php_parser_files = "340"
        wordpress_files = "1848"

        [paths]
        fragment = "tests/fixtures/probes/tandem_repeat.php"
        TOML;

    #[Test]
    public function itParsesTheTomlSubset(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');

        self::assertSame(['scan', 'paths'], array_keys($facts));
        self::assertSame('340', $facts['scan']['php_parser_files']);
        self::assertSame('1848', $facts['scan']['wordpress_files']);
    }

    /**
     * A fact table that half-parses is worse than one that fails, because the
     * missing half renders as an unresolved reference nobody reads.
     *
     * @return list<array{string, string}>
     */
    public static function malformed(): array
    {
        return [
            'key before any table'  => ["orphan = \"1\"\n", 'before any [table]'],
            'table declared twice'  => ["[a]\nx = \"1\"\n[a]\ny = \"2\"\n", 'declared twice'],
            'key set twice'         => ["[a]\nx = \"1\"\nx = \"2\"\n", 'set twice'],
            'unquoted value'        => ["[a]\nx = 1\n", 'not a key = "value" pair'],
            'single quotes'         => ["[a]\nx = '1'\n", 'not a key = "value" pair'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('malformed')]
    public function itRefusesAMalformedFactTable(string $toml, string $expected): void
    {
        $this->expectException(SigilError::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($expected, '/') . '/');

        sigil_parse_facts($toml, 'test');
    }

    #[Test]
    public function itSubstitutesInline(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');
        $doc   = 'the scan finds <!-- [[ $scan.php_parser_files ]] -->999<!--/--> files.';

        self::assertSame(
            'the scan finds <!-- [[ $scan.php_parser_files ]] -->340<!--/--> files.',
            sigil_render($doc, $facts, __DIR__ . '/..', 'test'),
        );
    }

    #[Test]
    public function itSubstitutesAsABlockWhenTheMarkerOwnsItsLine(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');
        $doc   = "before\n<!-- [[ \$scan.wordpress_files ]] -->\nstale\n<!--/-->\nafter\n";

        self::assertSame(
            "before\n<!-- [[ \$scan.wordpress_files ]] -->\n1848\n<!--/-->\nafter\n",
            sigil_render($doc, $facts, __DIR__ . '/..', 'test'),
        );
    }

    #[Test]
    public function itChainsReferencesByConcatenation(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');

        self::assertSame(
            '3401848',
            sigil_resolve('$scan.php_parser_files$scan.wordpress_files', $facts, __DIR__ . '/..', 'test'),
        );
    }

    #[Test]
    public function itReadsAFileForTheAtSigil(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');
        $value = sigil_resolve('@paths.fragment', $facts, dirname(__DIR__), 'test');

        self::assertStringContainsString('Probe 6 fixture', $value);
    }

    /**
     * Rendering must be idempotent, or the gate reports a document as stale on
     * every second run and stops meaning anything.
     */
    #[Test]
    public function itIsIdempotent(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');
        $doc   = 'a <!-- [[ $scan.php_parser_files ]] -->1<!--/--> b';

        $once  = sigil_render($doc, $facts, __DIR__ . '/..', 'test');
        $twice = sigil_render($once, $facts, __DIR__ . '/..', 'test');

        self::assertSame($once, $twice);
    }

    /**
     * The failure that makes the gate a no-op: an opening marker with no
     * terminator substitutes nothing, so a stale value sits there and passes.
     */
    #[Test]
    public function itRefusesAnUnterminatedReference(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');

        $this->expectException(SigilError::class);
        $this->expectExceptionMessageMatches('/1 reference\(s\) opened, 0 terminated/');

        sigil_render('a <!-- [[ $scan.php_parser_files ]] --> b', $facts, __DIR__ . '/..', 'test');
    }

    #[Test]
    public function itRefusesAReferenceToAMissingFact(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');

        $this->expectException(SigilError::class);
        $this->expectExceptionMessageMatches('/has no key "absent"/');

        sigil_render('<!-- [[ $scan.absent ]] -->x<!--/-->', $facts, __DIR__ . '/..', 'test');
    }

    #[Test]
    public function itRefusesAReferenceWithTextOutsideItsLookups(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');

        $this->expectException(SigilError::class);
        $this->expectExceptionMessageMatches('/text outside its lookups/');

        sigil_render('<!-- [[ $scan.php_parser_files files ]] -->x<!--/-->', $facts, __DIR__ . '/..', 'test');
    }

    /**
     * The hole the first version of this gate left open, and said so about: a
     * number typed as bare prose carries no reference, so nothing notices when
     * it stops being true. It is the failure the whole mechanism exists for.
     */
    #[Test]
    public function itFindsAFactStatedInProse(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');
        $bare  = sigil_bare_numbers("a scan finds 340 files\n", $facts);

        self::assertCount(1, $bare);
        self::assertSame('binding', $bare[0]['severity']);
        self::assertSame('$scan.php_parser_files', $bare[0]['ref']);
        self::assertSame(1, $bare[0]['line']);
    }

    #[Test]
    public function itIgnoresAFactThatIsProperlyReferenced(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');
        $bare  = sigil_bare_numbers('a scan finds <!-- [[ $scan.php_parser_files ]] -->340<!--/--> files', $facts);

        self::assertSame([], array_values(array_filter($bare, static fn(array $b): bool => $b['severity'] === 'binding')));
    }

    /**
     * A gate that binds every "0" and "2" in the prose to whichever key happens
     * to hold that value gets ignored, and then it protects nothing. The first
     * run of this check reported a list marker as a measured fact.
     */
    #[Test]
    public function itDoesNotBindSingleDigitsToCoincidingFacts(): void
    {
        $facts = ['scan' => ['skipped' => '2', 'files' => '340']];
        $bare  = sigil_bare_numbers('there are 2 of them, and 340 files', $facts);

        $binding = array_values(array_filter($bare, static fn(array $b): bool => $b['severity'] === 'binding'));

        self::assertCount(1, $binding, 'the 2 is a coincidence; the 340 is a citation');
        self::assertSame('340', $binding[0]['number']);
    }

    #[Test]
    public function itMatchesAThousandsSeparatedFact(): void
    {
        $facts = ['scan' => ['files' => '1848']];
        $bare  = sigil_bare_numbers('WordPress passes at 1,848 files', $facts);

        self::assertSame('binding', $bare[0]['severity']);
        self::assertSame('$scan.files', $bare[0]['ref']);
    }

    /**
     * Some numbers must not move. A table is the record of one sitting on one
     * machine, and re-rendering its cells from today's facts would rewrite what
     * was measured into a claim nobody made.
     */
    #[Test]
    public function itLeavesAFrozenSpanAloneAndExemptsItsNumbers(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');
        $doc   = "<!-- [[ frozen: measured before the fix ]] -->\n    php-parser 342 files\n<!--/-->\n";

        self::assertSame($doc, sigil_render($doc, $facts, __DIR__ . '/..', 'test'), 'a frozen body is never rewritten');
        self::assertSame([], sigil_bare_numbers($doc, $facts), 'and its numbers are not reported as bare');
    }

    /**
     * "This one is special" without saying why is how the hand-typed numbers
     * got here in the first place.
     */
    #[Test]
    public function itRefusesToFreezeWithoutAReason(): void
    {
        $facts = sigil_parse_facts(self::FACTS, 'test');

        $this->expectException(SigilError::class);
        $this->expectExceptionMessageMatches('/must say why it is frozen/');

        sigil_render("<!-- [[ frozen: ]] -->\n    342\n<!--/-->", $facts, __DIR__ . '/..', 'test');
    }

    #[Test]
    public function itRecognisesTheFrozenPrefixRegardlessOfSpacingAndCase(): void
    {
        self::assertTrue(sigil_is_frozen('frozen: why'));
        self::assertTrue(sigil_is_frozen('  FROZEN : why'));
        self::assertFalse(sigil_is_frozen('$scan.files'));
        self::assertFalse(sigil_is_frozen('frozenish.key'));
    }

    /**
     * Grouping on the spelling reported one measurement as two unrelated
     * singletons. "11.9x" in one paragraph and "11.90x" in another are the same
     * ratio, and both survived a review that was looking for exactly this.
     */
    #[Test]
    public function itGroupsTwoSpellingsOfOneValue(): void
    {
        $bare = sigil_bare_numbers("fails at 11.9x here\nand at 11.90x there\n", []);

        $groups = sigil_group_loose($bare);

        self::assertCount(1, $groups);
        self::assertSame(2, $groups[0]['count']);
        self::assertSame(['11.9', '11.90'], $groups[0]['spellings']);
    }

    #[Test]
    public function itRanksByHowManyPlacesStateAValue(): void
    {
        $bare = sigil_bare_numbers("87 once\n64 here\n64 there\n", []);

        $groups = sigil_group_loose($bare);

        self::assertSame('64', $groups[0]['number'], 'copies rank above singletons');
        self::assertSame(2, $groups[0]['count']);
        self::assertSame([2, 3], $groups[0]['lines']);
    }

    /**
     * A comma is a thousands separator and nothing else. Admitting it anywhere
     * turned "at line 13, below" into the token "13,", which the audit then
     * reported as a rounding of 12.74.
     */
    #[Test]
    public function itReadsACommaOnlyAsAThousandsSeparator(): void
    {
        $bare = sigil_bare_numbers('1,024 pairs at line 13, below', []);

        self::assertSame(['1,024', '13'], array_column($bare, 'number'));
    }

    /**
     * The finding no other check produces: one measurement written at two
     * precisions. Neither groups nor compares equal, and both rot separately.
     */
    #[Test]
    public function itFindsARoundedRestatement(): void
    {
        self::assertSame(
            ['38.6' => '38.601', '26' => '26.4'],
            sigil_restatements(['38.6', '26', '87'], ['38.601', '26.4', '87']),
        );
    }

    /**
     * An absolute rounding window alone gives a whole number +/-0.5 and sweeps
     * up every measurement that lands nearby. All three of these were reported
     * as restatements before a relative tolerance was added, and none is one.
     */
    #[Test]
    public function itDoesNotCallANearbyNumberARestatement(): void
    {
        self::assertSame([], sigil_restatements(['8', '3', '0'], ['7.512', '2.509', '0.091']));
    }

    #[Test]
    public function itTakesFrozenNumbersAsSourcesButNeverAsFindings(): void
    {
        $doc = "<!-- [[ frozen: the sitting ]] -->\n    phpunit  38.601s\n<!--/-->\nits 38.6s inside Metadata\n";

        self::assertSame(['38.601'], sigil_frozen_numbers($doc));
        self::assertSame(['38.6'], array_column(sigil_bare_numbers($doc, []), 'number'));
        self::assertSame(['38.6' => '38.601'], sigil_restatements(['38.6'], sigil_frozen_numbers($doc)));
    }

    #[Test]
    public function itCarriesTheLineTextSoAFindingCanBeJudgedWithoutOpeningTheFile(): void
    {
        $bare = sigil_bare_numbers("a banner at line 13, below the imports\n", []);

        self::assertSame('a banner at line 13, below the imports', $bare[0]['text']);
    }

    /**
     * Value equality is a coincidence generator, and it gets worse as the fact
     * table grows: every fact added is another integer for unrelated prose to
     * collide with. Two got through the BINDING class — the class documented as
     * having no judgement call in it — before corroboration existed.
     *
     * The first answer was to freeze each collision as it appeared. That marks
     * the symptom and leaves the detector guessing, so the next one costs
     * another reader another investigation.
     */
    #[Test]
    public function itDoesNotBindANumberInASentenceAboutSomethingElse(): void
    {
        $facts = ['scan' => ['symfony_console_clones' => '117']];
        $bare  = sigil_bare_numbers('117 consensus labels (both worksheets), recorded as the measurement.', $facts);

        self::assertSame('loose', $bare[0]['severity']);
        self::assertNull($bare[0]['ref']);
    }

    #[Test]
    public function itStillBindsWhenTheSentenceIsAboutTheFact(): void
    {
        $facts = ['scan' => ['symfony_console_files' => '359']];
        $bare  = sigil_bare_numbers('symfony-console scans 359 files in a default run', $facts);

        self::assertSame('binding', $bare[0]['severity']);
        self::assertSame('$scan.symfony_console_files', $bare[0]['ref']);
    }

    /**
     * Prose does not always use the identifier's words. `postings_cap` is
     * written as "kept at most 1,000 times across the corpus", which shares
     * nothing with either half of the key, so without an alias a real citation
     * would be demoted to advisory.
     */
    #[Test]
    public function itCorroboratesThroughAnAlias(): void
    {
        self::assertFalse(
            sigil_corroborates('a fingerprint is kept at most 1,000 times across the corpus', '$code.postings_cap', []),
            'neither "postings" nor "cap" appears in that sentence',
        );

        self::assertTrue(
            sigil_corroborates('a fingerprint is kept at most 1,000 times across the corpus', '$code.postings_cap', ['postings_cap' => 'corpus fingerprint posting']),
        );
    }

    #[Test]
    public function itCorroboratesOnAnyWordOfTheKey(): void
    {
        foreach (['symfony-string has 33', 'the string corpus', 'how many files'] as $line) {
            self::assertTrue(sigil_corroborates($line, '$scan.symfony_string_files', []), $line);
        }

        self::assertFalse(sigil_corroborates('app/Services/Billing.php has 21 lines', '$scan.symfony_string_files', []));
    }

    /**
     * The state a publishable timing requires. Synthetic on purpose: asserting
     * against this machine's live sysfs would make the test's verdict depend on
     * the power policy of whoever runs it, which is the exact confusion the
     * predicate exists to end.
     *
     * @return array<string, array{array{governor?: ?string, epp?: ?string, on_battery?: ?bool, mhz?: ?float, max_mhz?: ?float, load?: ?float, cpus?: int}, list<string>}>
     */
    public static function machines(): array
    {
        $good = ['governor' => 'performance', 'epp' => 'performance', 'on_battery' => false, 'mhz' => 3900.0, 'max_mhz' => 4100.0, 'load' => 0.4, 'cpus' => 8];

        return [
            'AC, performance, idle'   => [$good, []],
            'powersave with good EPP' => [['governor' => 'powersave'] + $good, []],
            'on battery'              => [['on_battery' => true] + $good, ['running on battery']],
            'power-biased EPP'        => [['epp' => 'power'] + $good, ['energy_performance_preference is "power"']],
            'balance_power EPP'       => [['epp' => 'balance_power'] + $good, ['energy_performance_preference is "balance_power"']],
            'clock capped'            => [['mhz' => 800.0] + $good, ['clock is 800 MHz against a 4100 MHz maximum (20%)']],
            'a co-tenant process'     => [['load' => 6.0] + $good, ['load average is 6.00 across 8 cpus — something else is running']],
            'nothing readable'        => [['governor' => null, 'epp' => null, 'on_battery' => null, 'mhz' => null, 'max_mhz' => null, 'load' => null, 'cpus' => 1], []],
        ];
    }

    /**
     * @param array{governor?: ?string, epp?: ?string, on_battery?: ?bool, mhz?: ?float, max_mhz?: ?float, load?: ?float, cpus?: int} $state
     * @param list<string>                                                                                                          $expected
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('machines')]
    public function itRefusesToTimeAMachineThatCannotDeliver(array $state, array $expected): void
    {
        self::assertSame($expected, bcb_timing_reasons($state));
    }

    /**
     * Every reason is independent, so a machine that is wrong in four ways says
     * so four times rather than stopping at the first.
     */
    #[Test]
    public function itNamesEveryReasonAtOnce(): void
    {
        $reasons = bcb_timing_reasons([
            'epp' => 'power', 'on_battery' => true, 'mhz' => 800.0, 'max_mhz' => 1900.0, 'load' => 6.0, 'cpus' => 8,
        ]);

        self::assertCount(4, $reasons);
    }
}
