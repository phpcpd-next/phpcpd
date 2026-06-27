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
use function simplexml_load_string;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Log\PMD;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PMD::class)]
final class PmdTest extends TestCase
{
    // rename_base.php contains `if ($value > $peak)` — a source `>` to escape.
    private const string FILE_A = __DIR__ . '/fixtures/type2/rename_base.php';
    private const string FILE_B = __DIR__ . '/fixtures/type2/rename_variant.php';

    #[Test]
    public function produces_well_formed_pmd_cpd_xml(): void
    {
        $xml = $this->render();

        self::assertNotFalse(simplexml_load_string($xml), 'output must be well-formed XML');
        self::assertStringContainsString('<pmd-cpd>', $xml);
        self::assertStringContainsString('<duplication lines="6" tokens="50">', $xml);
        self::assertStringContainsString('<file path=', $xml);
        self::assertStringContainsString('<codefragment>', $xml);
    }

    #[Test]
    public function escapes_xml_special_characters_in_the_code_fragment(): void
    {
        // The cleanup (createTextNode) must still escape source `>` as &gt; — and the
        // document must stay well-formed, i.e. no raw `>` leaks into the fragment text.
        $xml = $this->render();

        self::assertStringContainsString('&gt;', $xml);
        self::assertNotFalse(simplexml_load_string($xml));
    }

    private function render(): string
    {
        $map = new CodeCloneMap();
        $map->add(new CodeClone(
            new CodeCloneFile(self::FILE_A, 15),
            new CodeCloneFile(self::FILE_B, 15),
            6,
            50,
        ));

        $file = (string) tempnam(sys_get_temp_dir(), 'phpcpd-pmd');

        try {
            (new PMD($file))->process($map);

            return (string) file_get_contents($file);
        } finally {
            unlink($file);
        }
    }
}
