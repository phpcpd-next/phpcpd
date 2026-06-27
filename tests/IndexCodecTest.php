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

use function array_keys;
use function base64_encode;
use function chr;
use function count;
use function hash;
use function json_encode;
use function str_repeat;
use function strlen;
use function substr;

use LucianoPereira\PhpcpdNext\Cache\IndexCodec;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexCodec::class)]
#[CoversClass(FileTokens::class)]
final class IndexCodecTest extends TestCase
{
    #[Test]
    public function round_trips_a_single_file_including_raw_signature_bytes(): void
    {
        // Signature spanning every byte value 0x00..0xFF — the reason base64 was
        // once needed; the binary format must carry these verbatim.
        $signature = '';

        for ($b = 0; $b < 256; $b++) {
            $signature .= chr($b);
        }

        $tokens = new FileTokens(42, $signature, [1, 1, 2, 5, 9], [3, 3, 4, 7, 11]);
        $stored = ['/src/A.php' => ['hash' => hash('sha256', 'A'), 'tokens' => $tokens]];

        $decoded = IndexCodec::decode(IndexCodec::encode($stored));

        self::assertArrayHasKey('/src/A.php', $decoded);
        self::assertSame($stored['/src/A.php']['hash'], $decoded['/src/A.php']['hash']);

        $out = $decoded['/src/A.php']['tokens'];
        self::assertSame(42, $out->numberOfLines);
        self::assertSame($signature, $out->signature, 'every raw byte must survive');
        self::assertSame([1, 1, 2, 5, 9], $out->tokenLines);
        self::assertSame([3, 3, 4, 7, 11], $out->tokenRealLines);
    }

    #[Test]
    public function round_trips_multiple_files_in_order(): void
    {
        $stored = [
            '/a.php' => ['hash' => hash('sha256', 'a'), 'tokens' => $this->tokens([1, 2, 3])],
            '/b.php' => ['hash' => hash('sha256', 'b'), 'tokens' => $this->tokens([1, 4, 4, 8])],
            '/c.php' => ['hash' => hash('sha256', 'c'), 'tokens' => $this->tokens([])],
        ];

        $decoded = IndexCodec::decode(IndexCodec::encode($stored));

        self::assertSame(['/a.php', '/b.php', '/c.php'], array_keys($decoded));
        self::assertSame([1, 4, 4, 8], $decoded['/b.php']['tokens']->tokenLines);
        self::assertSame([], $decoded['/c.php']['tokens']->tokenLines, 'a zero-token file round-trips');
    }

    #[Test]
    public function an_empty_index_round_trips(): void
    {
        self::assertSame([], IndexCodec::decode(IndexCodec::encode([])));
    }

    #[Test]
    public function delta_varints_handle_large_and_repeated_line_numbers(): void
    {
        // Repeats (delta 0) and a jump past one varint byte (>127) exercise both
        // the delta encoding and the multi-byte varint path.
        $lines  = [0, 0, 127, 128, 130, 16384, 16384];
        $tokens = $this->tokens($lines);
        $stored = ['/big.php' => ['hash' => hash('sha256', 'big'), 'tokens' => $tokens]];

        $decoded = IndexCodec::decode(IndexCodec::encode($stored));

        self::assertSame($lines, $decoded['/big.php']['tokens']->tokenLines);
    }

    #[Test]
    public function a_truncated_blob_decodes_to_empty(): void
    {
        $stored = ['/a.php' => ['hash' => hash('sha256', 'a'), 'tokens' => $this->tokens([1, 2, 3])]];
        $blob   = IndexCodec::encode($stored);

        // Drop the final byte: the last varint is now incomplete.
        self::assertSame([], IndexCodec::decode(substr($blob, 0, strlen($blob) - 1)));
    }

    #[Test]
    public function a_bad_magic_or_version_decodes_to_empty(): void
    {
        $blob = IndexCodec::encode(['/a.php' => ['hash' => hash('sha256', 'a'), 'tokens' => $this->tokens([1])]]);

        self::assertSame([], IndexCodec::decode('XXXX' . substr($blob, 4)), 'bad magic');
        self::assertSame([], IndexCodec::decode(substr($blob, 0, 4) . chr(99) . substr($blob, 5)), 'bad version');
        self::assertSame([], IndexCodec::decode(''), 'empty input');
    }

    #[Test]
    public function the_binary_form_is_smaller_than_equivalent_json(): void
    {
        // A realistically sized file: 400 significant tokens (2000-byte signature).
        $lines = [];

        for ($i = 0; $i < 400; $i++) {
            $lines[] = $i;
        }

        $tokens = new FileTokens(500, str_repeat("\x01\x02\x03\x04\x05", 400), $lines, $lines);
        $stored = ['/src/Big.php' => ['hash' => hash('sha256', 'big'), 'tokens' => $tokens]];

        $binary = strlen(IndexCodec::encode($stored));

        // The old JSON form: base64 signature + integer arrays.
        $json = strlen((string) json_encode([
            'v'     => 1,
            'files' => ['/src/Big.php' => [
                'hash'   => hash('sha256', 'big'),
                'tokens' => [
                    'lines' => 500,
                    'sig'   => base64_encode($tokens->signature),
                    'pos'   => $lines,
                    'real'  => $lines,
                ],
            ]],
        ]));

        self::assertLessThan($json, $binary);
        self::assertLessThan($json / 2, $binary, 'binary should be well under half the JSON size');
    }

    /** @param list<int> $lines */
    private function tokens(array $lines): FileTokens
    {
        return new FileTokens(10, str_repeat("\xAB\xCD\xEF\x01\x02", count($lines)), $lines, $lines);
    }
}
