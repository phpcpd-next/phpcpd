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

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function str_replace;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Engine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `IGNORED_TOKENS` drops `T_NS_SEPARATOR` so that where a name comes from does
 * not decide whether two files match. On PHP 8 that entry is dead — zero
 * occurrences in 196,795 tokens of php-parser and zero in this project's own
 * `src/` — because the separator stopped being a token of its own:
 * `\App\Support\Money` arrives whole, as one `T_NAME_FULLY_QUALIFIED`, and the
 * qualifier rides along inside it.
 *
 * So the raw view did not match `\App\Support\Money::of($x)` against
 * `Money::of($x)`, and the intent had quietly stopped applying to most of
 * modern PHP. It is the same oversight `TokenNormalizer` already records and
 * fixes for the normalized view; the raw view never got it.
 */
#[CoversClass(DefaultStrategy::class)]
final class QualifiedNameFoldingTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phpcpd-qualified-' . uniqid();

        mkdir($this->directory);

        $qualified = self::source();

        file_put_contents($this->directory . '/A.php', $qualified);
        file_put_contents(
            $this->directory . '/B.php',
            str_replace(['\App\Support\Money', 'class A'], ['Money', 'class B'], $qualified),
        );
    }

    protected function tearDown(): void
    {
        foreach (['A.php', 'B.php'] as $name) {
            @unlink($this->directory . '/' . $name);
        }

        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    #[Test]
    public function qualificationDoesNotDecideWhetherTwoFilesMatch(): void
    {
        $config = new StrategyConfiguration(
            minLines: 5,
            minTokens: 40,
            normalization: Normalization::Raw,
            minSimilarity: 0.7,
        );

        $map = (new Engine($config, 'rabin-karp'))->detect([
            $this->directory . '/A.php',
            $this->directory . '/B.php',
        ]);

        // Raw matching, no --fuzzy: the two differ in nothing but where `Money`
        // is imported from, which is the case the ignore set already asserts is
        // not a difference.
        self::assertGreaterThan(0, $map->count());
    }

    private static function source(): string
    {
        $body = '';

        for ($i = 0; $i < 20; $i++) {
            $body .= '        $total += \App\Support\Money::of($rows[' . $i . '])->cents();' . "\n";
        }

        return "<?php\nfinal class A {\n    public function run(array \$rows): int {\n        \$total = 0;\n"
            . $body
            . "        return \$total;\n    }\n}\n";
    }
}
