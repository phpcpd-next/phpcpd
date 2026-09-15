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
use function is_file;
use function ob_get_clean;
use function ob_start;
use function unlink;

use LucianoPereira\PhpcpdNext\Application;
use LucianoPereira\PhpcpdNext\Settings;
use LucianoPereira\PhpcpdNext\Triage\Stage0;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The two triage postures, each pinned to its own name rather than to whichever
 * one happens to be the default.
 *
 * `label`'s claim is an **identity**, not a tendency: a run in that posture
 * reports exactly what a run with `--no-triage` reports. Same findings, same
 * counts, same exit code, byte for byte. That is asserted here the way
 * `PresentationTest` asserts the presentation tier removes nothing — by comparing
 * the two outputs rather than by trusting the code that produces them, because a
 * posture that quietly changed a report would be a suppression mechanism wearing
 * a label's clothes.
 *
 * The comparison runs over the machine-readable report rather than the console
 * text, for the obvious reason that the console text *should* differ: the
 * labelling announces itself, as a stage that runs without being asked must.
 *
 * And the instrument is shown able to fail: the same fixture under
 * `--triage-posture=discard` produces a **different** report, so the identity
 * above is a measurement and not a tautology about an empty label set.
 *
 * Every case names the posture it is about rather than relying on the default,
 * which is free to change without these properties changing with it.
 */
#[CoversClass(Application::class)]
#[CoversClass(Settings::class)]
#[CoversClass(Stage0::class)]
final class TriagePostureTest extends TestCase
{
    use BuildsAFixtureProject;

    protected function setUp(): void
    {
        $this->makeFixtureRoot('posture');

        $this->write('composer.json', '{"name":"acme/app","autoload":{"psr-4":{"Acme\\\\":"src/"}}}');

        // A wired pair: referenced from the entry point, duplicated. Its finding
        // survives every posture, so the report is never empty.
        $this->write('src/Live/Alpha.php', $this->duplicatedClass('Acme\\Live', 'Alpha'));
        $this->write('src/Live/Beta.php', $this->duplicatedClass('Acme\\Live', 'Beta'));
        $this->write('src/Boot.php', $this->entryPoint());

        // An unwired pair: nothing references either, and they duplicate each
        // other. Their finding is the one the `discard` posture takes away and
        // the `label` posture must not.
        $this->write('src/Dead/Ghost.php', $this->duplicatedClass('Acme\\Dead', 'Ghost'));
        $this->write('src/Dead/Wraith.php', $this->duplicatedClass('Acme\\Dead', 'Wraith'));
    }

    protected function tearDown(): void
    {
        $this->delete($this->root);
    }

    /**
     * The whole claim of the posture, in one assertion per thing it promises to
     * leave alone.
     */
    #[Test]
    public function theLabelPostureChangesNoFindingNoFileAndNoExitCode(): void
    {
        [$offExit, $offReport]     = $this->report(['--no-triage']);
        [$labelExit, $labelReport] = $this->report(['--triage-posture=label']);

        self::assertSame($offExit, $labelExit, 'the exit code a CI job gates on must not move');
        self::assertSame($offReport, $labelReport, 'the report must be identical, byte for byte');
    }

    /** The labelling posture runs, says what it decided, and removes nothing. */
    #[Test]
    public function theLabelPostureLabelsAndSaysSoWithoutRemovingAnything(): void
    {
        [, , $console] = $this->console(['--triage-posture=label']);

        self::assertStringContainsString('labelled (', $console);
        self::assertStringContainsString('none removed', $console);
        self::assertStringContainsString('unwired', $console);
        self::assertStringContainsString('--no-triage skips the stage', $console);
        self::assertStringNotContainsString(', removed (', $console);
    }

    /**
     * A default that changes what a scan reports has to announce itself, and
     * name the way out. Asserted on the console rather than on the report,
     * because "it said so" is the property.
     */
    #[Test]
    public function theDefaultRunDiscardsAndSaysSo(): void
    {
        [, , $console] = $this->console([]);

        self::assertStringContainsString(', removed (', $console);
        self::assertStringContainsString('unwired', $console);
        self::assertStringNotContainsString('none removed', $console);
    }

    /**
     * Stage 0's first rung *is* the orphan machinery, so a discarding run under
     * `--orphans` would remove exactly the files the report was asked about and
     * hand back an empty answer on a tree full of them. The stage does not run
     * in this mode, in either posture.
     */
    #[Test]
    public function orphansStillSeesTheUnwiredFilesTheDefaultWouldOtherwiseDiscard(): void
    {
        [, , $console] = $this->console(['--orphans']);

        self::assertStringContainsString('Ghost', $console, 'the unwired file must reach the report it is about');
        self::assertStringNotContainsString(', removed (', $console);
    }

    /**
     * The identity above is a measurement, not a tautology: the same fixture in
     * the posture that *does* act on the labels reports something different. An
     * assertion that could not fail would be worth nothing.
     */
    #[Test]
    public function theDiscardPostureDoesChangeTheReportOnTheSameFixture(): void
    {
        [, $labelReport]   = $this->report(['--triage-posture=label']);
        [, $discardReport] = $this->report(['--triage-posture=discard']);

        self::assertNotSame($labelReport, $discardReport);
        self::assertStringContainsString('Ghost.php', $labelReport);
        self::assertStringNotContainsString('Ghost.php', $discardReport);
    }

    /** An unknown posture is refused, never coerced to its nearest neighbour. */
    #[Test]
    public function theRetiredDemotePostureIsRefusedLoudly(): void
    {
        $this->expectException(\LucianoPereira\PhpcpdNext\SettingsException::class);
        $this->expectExceptionMessage('demote');

        \LucianoPereira\PhpcpdNext\Settings::resolve([['triage-posture', 'demote']]);
    }

    /**
     * Run the CLI in-process and return the exit code with the JSON report.
     *
     * @param  list<string> $options
     * @return array{0: int, 1: string}
     */
    private function report(array $options): array
    {
        [$exit, $report] = $this->console($options);

        return [$exit, $report];
    }

    /**
     * @param  list<string> $options
     * @return array{0: int, 1: string, 2: string}
     */
    private function console(array $options): array
    {
        $log = $this->root . '/report.json';

        ob_start();
        $exit = (new Application())->run(['phpcpd', '--min-tokens=40', '--min-lines=3', '--log-json=' . $log, ...$options, $this->root]);
        $console = (string) ob_get_clean();

        $report = is_file($log) ? (string) file_get_contents($log) : '';

        if (is_file($log)) {
            unlink($log);
        }

        return [$exit, $report, $console];
    }

    /**
     * Two of these in one namespace are a clone of each other: the body is long
     * enough to clear `--min-tokens=40` and identical across the pair.
     */
    private function duplicatedClass(string $namespace, string $name): string
    {
        return "<?php\n\nnamespace " . $namespace . ";\n\nfinal class " . $name . "\n{\n"
            . "    public function summarise(array \$rows): array\n    {\n"
            . "        \$total = 0;\n        \$count = 0;\n        \$largest = 0;\n\n"
            . "        foreach (\$rows as \$row) {\n"
            . "            \$value = (int) \$row['value'];\n"
            . "            \$total += \$value;\n            \$count++;\n\n"
            . "            if (\$value > \$largest) {\n                \$largest = \$value;\n            }\n"
            . "        }\n\n"
            . "        return ['total' => \$total, 'count' => \$count, 'largest' => \$largest];\n"
            . "    }\n}\n";
    }

    /** References the live pair and nothing else, so only they are wired. */
    private function entryPoint(): string
    {
        return "<?php\n\nnamespace Acme;\n\nuse Acme\\Live\\Alpha;\nuse Acme\\Live\\Beta;\n\n"
            . "final class Boot\n{\n    public function run(): void\n    {\n"
            . "        (new Alpha())->summarise([]);\n        (new Beta())->summarise([]);\n    }\n}\n";
    }
}
