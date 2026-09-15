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

use function copy;
use function getmypid;
use function is_file;
use function rmdir;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function str_replace;
use function sys_get_temp_dir;
use function unlink;

use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use LucianoPereira\PhpcpdNext\Presentation\AcknowledgmentLedger;
use LucianoPereira\PhpcpdNext\Presentation\Findings;
use LucianoPereira\PhpcpdNext\Presentation\Presenter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The acknowledgment ledger: the five properties the M5 charter fixes, each
 * asserted rather than described.
 *
 *   1. it demotes and never suppresses — the finding stays in the report and in
 *      every count;
 *   2. it is keyed to the content of **every** side, so an edit to either copy
 *      expires the entry;
 *   3. an expired entry re-asserts the finding rather than silently persisting;
 *   4. an entry nothing matches is reported by name;
 *   5. it is counted whenever a ledger is consulted, zeroes included.
 *
 * The instrument is also made to fail on demand, which this project asks of a
 * gate: the same fixture is scanned before and after a one-line edit inside the
 * clone, and the verdict flips. A ledger that could not expire would pass every
 * other assertion here.
 */
#[CoversClass(AcknowledgmentLedger::class)]
final class AcknowledgmentLedgerTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phpcpd-ledger-' . getmypid();

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o700, true);
        }

        foreach (['service_a.php', 'service_b.php'] as $name) {
            copy(__DIR__ . '/fixtures/strata/' . $name, $this->directory . '/' . $name);
        }
    }

    protected function tearDown(): void
    {
        foreach (['service_a.php', 'service_b.php', 'ledger.tsv'] as $name) {
            if (is_file($this->directory . '/' . $name)) {
                unlink($this->directory . '/' . $name);
            }
        }
    }

    private function present(?AcknowledgmentLedger $ledger): Findings
    {
        $config = new StrategyConfiguration(5, 70, Normalization::Raw, 0.7);
        $files  = [$this->directory . '/service_a.php', $this->directory . '/service_b.php'];

        return (new Presenter(ledger: $ledger))->present((new Engine($config, 'unified'))->detect($files));
    }

    #[Test]
    public function anAcknowledgedFindingIsDemotedAndStillReported(): void
    {
        $before = $this->present(null);
        self::assertSame(1, $before->count());
        self::assertSame(1, $before->asserted());
        self::assertFalse($before->ledger);

        $path = $this->directory . '/ledger.tsv';
        file_put_contents($path, AcknowledgmentLedger::render($before));

        $after = $this->present(AcknowledgmentLedger::load($path));

        // Demote, never suppress: same count, same clone, different posture.
        self::assertSame(1, $after->count());
        self::assertSame(1, $after->acknowledged());
        self::assertSame(1, $after->demoted());
        self::assertSame(0, $after->asserted());
        self::assertSame($before->findings[0]->clone->id(), $after->findings[0]->clone->id());
        self::assertStringContainsString('acknowledged', $after->findings[0]->tag());
        self::assertSame([], $after->stale);

        // An acknowledgment is not one of the pre-registered strata, and does not
        // contaminate their counts.
        self::assertSame([], $after->findings[0]->strata);
    }

    #[Test]
    public function editingEitherCopyExpiresTheEntryAndTheFindingIsAssertedAgain(): void
    {
        $path = $this->directory . '/ledger.tsv';
        file_put_contents($path, AcknowledgmentLedger::render($this->present(null)));

        // One line, inside the clone, in the SECOND copy — the side a
        // first-file-only key would never have looked at.
        $source = file_get_contents($this->directory . '/service_b.php');
        self::assertIsString($source);
        file_put_contents(
            $this->directory . '/service_b.php',
            str_replace("'largest' => \$largest,", "'biggest' => \$largest,", $source),
        );

        $after = $this->present(AcknowledgmentLedger::load($path));

        self::assertSame(1, $after->count());
        self::assertSame(0, $after->acknowledged());
        self::assertSame(1, $after->asserted());
        self::assertCount(1, $after->stale);
        self::assertStringContainsString('service_a.php', $after->stale[0]);
    }

    #[Test]
    public function anEntryMatchingNothingIsReportedRatherThanKept(): void
    {
        $path = $this->directory . '/ledger.tsv';
        file_put_contents($path, "# a ledger\n0000000000000000\t9 lines · gone.php:1 ↔ also-gone.php:1\n");

        $findings = $this->present(AcknowledgmentLedger::load($path));

        self::assertTrue($findings->ledger);
        self::assertSame(0, $findings->acknowledged());
        self::assertSame(['9 lines · gone.php:1 ↔ also-gone.php:1'], $findings->stale);
    }

    #[Test]
    public function aMissingLedgerIsAnEmptyLedgerRatherThanAnError(): void
    {
        $findings = $this->present(AcknowledgmentLedger::load($this->directory . '/never-written.tsv'));

        self::assertTrue($findings->ledger);
        self::assertSame(0, $findings->acknowledged());
        self::assertSame(1, $findings->asserted());
        self::assertSame([], $findings->stale);
    }

    #[Test]
    public function theKeyIsTheContentAndNotThePath(): void
    {
        $original = AcknowledgmentLedger::keyOf($this->present(null)->findings[0]->clone);

        // Move both files: same content, new location, same key. Re-acknowledging
        // a decision after a rename would be noise.
        $moved = $this->directory . '/moved';

        if (!is_dir($moved)) {
            mkdir($moved, 0o700, true);
        }

        foreach (['service_a.php', 'service_b.php'] as $name) {
            copy($this->directory . '/' . $name, $moved . '/' . $name);
        }

        $config = new StrategyConfiguration(5, 70, Normalization::Raw, 0.7);
        $clones = (new Engine($config, 'unified'))->detect([$moved . '/service_a.php', $moved . '/service_b.php']);
        $there  = (new Presenter())->present($clones);

        self::assertSame($original, AcknowledgmentLedger::keyOf($there->findings[0]->clone));

        foreach (['service_a.php', 'service_b.php'] as $name) {
            unlink($moved . '/' . $name);
        }

        rmdir($moved);
    }

    #[Test]
    public function theLedgerFileIsDiffableAndCarriesItsOwnExplanation(): void
    {
        $rendered = AcknowledgmentLedger::render($this->present(null));

        self::assertStringContainsString('# phpcpd-next acknowledgment ledger', $rendered);
        self::assertStringContainsString('DEMOTED, never hidden', $rendered);
        self::assertStringContainsString("\t", $rendered);

        // Rendering is stable: a ledger that reordered itself between runs would
        // produce a diff on every commit.
        self::assertSame($rendered, AcknowledgmentLedger::render($this->present(null)));
    }
}
