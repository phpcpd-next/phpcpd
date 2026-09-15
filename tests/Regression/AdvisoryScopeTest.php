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

use function json_encode;

use LucianoPereira\PhpcpdNext\Application;
use LucianoPereira\PhpcpdNext\Tests\BuildsAFixtureProject;
use LucianoPereira\PhpcpdNext\Tests\RunsTheBinary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Triage narrows what is reviewed for duplication. It must not narrow what is
 * read for references: a discarded file still calls what it calls, so its
 * references still count, and the orphan advisory must see the same set
 * `--orphans` does or the two disagree about what is reachable.
 */
#[CoversClass(Application::class)]
final class AdvisoryScopeTest extends TestCase
{
    use BuildsAFixtureProject;
    use RunsTheBinary;

    protected function setUp(): void
    {
        $this->makeFixtureRoot('advisory');

        $this->write('composer.json', (string) json_encode([
            'name'     => 'fixture/advisory',
            'autoload' => ['psr-4' => ['Fixture\\' => 'src/']],
        ]));

        $this->write(
            'src/Service.php',
            "<?php\n\nnamespace Fixture;\n\nclass Service\n{\n    public function run(): int\n    {\n        return 1;\n    }\n}\n",
        );

        // Declares a namespace the manifest does not wire, so triage labels it
        // foreign and discards it — and it holds the only reference to Service.
        $this->write(
            'integration/Example.php',
            "<?php\n\nnamespace Outside\\Example;\n\nuse Fixture\\Service;\n\nclass Example\n{\n    public function go(): int\n    {\n        return (new Service())->run();\n    }\n}\n",
        );
    }

    protected function tearDown(): void
    {
        $this->delete($this->root);
    }

    #[Test]
    public function aFileTriageDiscardsStillCountsAsAReference(): void
    {
        [$triaged, , ]  = $this->invoke(['--no-config', '.'], [], $this->root);
        [$explicit, , ] = $this->invoke(['--no-config', '--orphans', '.'], [], $this->root);

        self::assertStringContainsString(
            'removed',
            $triaged,
            'The fixture only tests anything if triage actually discards the foreign file: ' . $triaged,
        );

        self::assertStringNotContainsString(
            'Service',
            $triaged,
            'A class referenced only from a triaged file is still referenced; the advisory '
            . 'must not name it, least of all while telling the reader to run --orphans: ' . $triaged,
        );

        self::assertStringContainsString(
            'No orphaned symbols found',
            $explicit,
            '--orphans is the authority the advisory points at, and it sees the whole set: ' . $explicit,
        );
    }
}
