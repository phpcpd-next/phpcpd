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

use function file_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Log\PMD;
use LucianoPereira\PhpcpdNext\Log\Sarif;
use LucianoPereira\PhpcpdNext\Presentation\Finding;
use LucianoPereira\PhpcpdNext\Presentation\Findings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What the machine-readable reports say about where a clone is, and why it was
 * demoted.
 *
 * The goldens pin whole reports for six scenarios, and neither of the defects
 * here appears in any of them: one needs two occurrences of different lengths,
 * the other needs a finding the ledger demoted and no stratum did.
 */
#[CoversClass(PMD::class)]
#[CoversClass(Sarif::class)]
final class ReporterExtentTest extends TestCase
{
    /**
     * Two occurrences of one clone, the second measured longer than the first.
     *
     * Real files, because PMD embeds the source of the first site in a
     * `codefragment` and a made-up path makes the reporter read a file that is
     * not there.
     */
    private function findings(bool $acknowledged = false): Findings
    {
        $clone = new CodeClone(
            new CodeCloneFile('tests/fixtures/with_clones/Alpha.php', 10, 5, 40),
            new CodeCloneFile('tests/fixtures/with_clones/Beta.php', 3, 8, 40),
            5,
            40,
        );

        $map = new CodeCloneMap();
        $map->add($clone);

        return new Findings($map, [new Finding($clone, [], 1.0, [], $acknowledged)]);
    }

    private function report(Findings $findings, string $format): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phpcpd-report-');

        try {
            $logger = $format === 'pmd' ? new PMD($path) : new Sarif($path);
            $logger->process($findings);

            return (string) file_get_contents($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * Each occurrence ends where *it* ends. Copies need not span the same number
     * of source lines — the matchers compare significant tokens, and comments
     * between them are free — so borrowing the clone's length for every site
     * mis-reports the longer ones.
     */
    #[Test]
    public function pmdGivesEachOccurrenceItsOwnEndLine(): void
    {
        $xml = $this->report($this->findings(), 'pmd');

        self::assertStringContainsString('line="10" endline="14"', $xml);
        self::assertStringContainsString('line="3" endline="10"', $xml);
    }

    #[Test]
    public function sarifGivesEachOccurrenceItsOwnEndLine(): void
    {
        $json = $this->report($this->findings(), 'sarif');

        self::assertStringContainsString('"startLine": 10', $json);
        self::assertStringContainsString('"endLine": 14', $json);
        self::assertStringContainsString('"startLine": 3', $json);
        self::assertStringContainsString('"endLine": 10', $json);
    }

    /**
     * A demotion that cannot say what demoted it is the one thing a demotion
     * has to be able to answer. The ledger is a demote axis of its own, so a
     * finding it demoted has no stratum — and the attribute naming the reason
     * used to be gated on the strata alone.
     */
    #[Test]
    public function pmdNamesTheLedgerAsTheReasonItDemoted(): void
    {
        $xml = $this->report($this->findings(acknowledged: true), 'pmd');

        self::assertStringContainsString('stratum="demoted"', $xml);
        self::assertStringContainsString('demotedBy="acknowledged"', $xml);
    }

    #[Test]
    public function anUndemotedFindingIsNotTaggedWithAReason(): void
    {
        $xml = $this->report($this->findings(), 'pmd');

        self::assertStringContainsString('stratum="asserted"', $xml);
        self::assertStringNotContainsString('demotedBy=', $xml);
    }
}
