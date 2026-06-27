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

use function file_get_contents;
use function json_decode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Log\Sarif;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Sarif::class)]
final class SarifTest extends TestCase
{
    private const string FILE_A = __DIR__ . '/fixtures/type3/clone_base.php';
    private const string FILE_B = __DIR__ . '/fixtures/type3/clone_gapped.php';
    private const string FILE_C = __DIR__ . '/fixtures/no_clones/Unique.php';

    #[Test]
    public function emits_valid_sarif_2_1_0_with_tool_metadata(): void
    {
        $sarif = $this->render($this->mapWith(gapped: true));

        self::assertIsArray(json_decode($sarif, true), 'output must be valid JSON');
        self::assertStringContainsString('"version": "2.1.0"', $sarif);
        self::assertStringContainsString('sarif-2.1.0', $sarif);
        self::assertStringContainsString('"name": "phpcpd-next"', $sarif);
    }

    #[Test]
    public function a_gapped_clone_is_a_warning(): void
    {
        $sarif = $this->render($this->mapWith(gapped: true));

        self::assertStringContainsString('"ruleId": "inconsistent-clone"', $sarif);
        self::assertStringContainsString('"level": "warning"', $sarif);
    }

    #[Test]
    public function an_exact_clone_is_a_note(): void
    {
        $sarif = $this->render($this->mapWith(gapped: false));

        self::assertStringContainsString('"ruleId": "duplicate-code"', $sarif);
        self::assertStringContainsString('"level": "note"', $sarif);
    }

    private function mapWith(bool $gapped): CodeCloneMap
    {
        $map = new CodeCloneMap();
        $map->add(new CodeClone(
            new CodeCloneFile(self::FILE_A, 1),
            new CodeCloneFile($gapped ? self::FILE_B : self::FILE_C, 1),
            5,
            50,
            gapped: $gapped,
        ));

        return $map;
    }

    private function render(CodeCloneMap $map): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'phpcpd-sarif');

        try {
            (new Sarif($file))->process($map);

            return (string) file_get_contents($file);
        } finally {
            unlink($file);
        }
    }
}
