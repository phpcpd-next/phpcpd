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
use LucianoPereira\PhpcpdNext\Log\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Json::class)]
final class JsonTest extends TestCase
{
    private const string FILE_A = __DIR__ . '/fixtures/type3/clone_base.php';
    private const string FILE_B = __DIR__ . '/fixtures/type3/clone_gapped.php';

    #[Test]
    public function writes_valid_json_with_summary_and_clone_details(): void
    {
        $map = new CodeCloneMap();
        $map->add(new CodeClone(
            new CodeCloneFile(self::FILE_A, 1),
            new CodeCloneFile(self::FILE_B, 1),
            5,
            50,
            gapped: true,
        ));

        $json = $this->render($map);

        self::assertIsArray(json_decode($json, true), 'output must be valid JSON');
        self::assertStringContainsString('"tool": "phpcpd-next"', $json);
        self::assertStringContainsString('"clones": 1', $json);
        self::assertStringContainsString('"inconsistentClones": 1', $json);
        self::assertStringContainsString('"gapped": true', $json);
    }

    #[Test]
    public function an_empty_map_produces_valid_json_with_zero_counts(): void
    {
        $json = $this->render(new CodeCloneMap());

        self::assertIsArray(json_decode($json, true));
        self::assertStringContainsString('"clones": 0', $json);
    }

    private function render(CodeCloneMap $map): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'phpcpd-json');

        try {
            (new Json($file))->process($map);

            return (string) file_get_contents($file);
        } finally {
            unlink($file);
        }
    }
}
