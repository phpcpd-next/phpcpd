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

namespace LucianoPereira\PhpcpdNext\Cache;

use function bin2hex;
use function chr;
use function count;
use function hex2bin;
use function ord;
use function pack;
use function strlen;
use function substr;

use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;

/**
 * Compact binary serialization for the per-file incremental index.
 *
 * The index is dominated by two kinds of per-token data: the signature (5 bytes
 * per token of crc32 hashes — high entropy, stored raw) and two line-number arrays
 * (monotonic non-decreasing — stored as deltas under unsigned LEB128 varints, so
 * each typically costs one byte). Storing the signature raw rather than base64'd,
 * and the positions delta+varint rather than as JSON integers, makes the index
 * roughly a third the size of the equivalent JSON with no dependency beyond the
 * core pack()/ord()/chr() primitives.
 *
 * Layout (all integers unsigned LEB128 varints unless noted):
 *
 *   magic "PXI1" (4 raw bytes) | version (1 byte) | fileCount
 *   per file:
 *     pathLen | path
 *     32 raw bytes  sha256 of the file content
 *     numberOfLines
 *     tokenCount (T)
 *     sigLen | signature (sigLen raw bytes)
 *     T × varint  delta-encoded tokenLines
 *     T × varint  delta-encoded tokenRealLines
 *
 * decode() is total: any malformation (bad magic/version, truncation, a hash that
 * is not 32 bytes) yields an empty map, so a corrupt index degrades to a full
 * re-scan rather than a fatal error.
 */
final class IndexCodec
{
    private const string MAGIC   = 'PXI1';
    private const int VERSION    = 1;
    private const int HASH_BYTES = 32;

    /**
     * @param array<string, array{hash: string, tokens: FileTokens}> $stored
     */
    public static function encode(array $stored): string
    {
        $out = self::MAGIC . chr(self::VERSION) . self::varint(count($stored));

        foreach ($stored as $path => $entry) {
            $rawHash = hex2bin($entry['hash']);

            if ($rawHash === false || strlen($rawHash) !== self::HASH_BYTES) {
                continue;
            }

            $tokens = $entry['tokens'];

            $out .= self::varint(strlen($path)) . $path;
            $out .= $rawHash;
            $out .= self::varint($tokens->numberOfLines);
            $out .= self::varint(count($tokens->tokenLines));
            $out .= self::varint(strlen($tokens->signature)) . $tokens->signature;
            $out .= self::deltaVarints($tokens->tokenLines);
            $out .= self::deltaVarints($tokens->tokenRealLines);
        }

        return $out;
    }

    /**
     * @return array<string, array{hash: string, tokens: FileTokens}>
     */
    public static function decode(string $blob): array
    {
        $len = strlen($blob);

        if ($len < 5 || substr($blob, 0, 4) !== self::MAGIC || ord($blob[4]) !== self::VERSION) {
            return [];
        }

        $pos       = 5;
        $fileCount = self::readVarint($blob, $len, $pos);

        if ($fileCount === null) {
            return [];
        }

        $stored = [];

        for ($i = 0; $i < $fileCount; $i++) {
            $entry = self::decodeFile($blob, $len, $pos);

            if ($entry === null) {
                return [];
            }

            [$path, $hash, $tokens] = $entry;
            $stored[$path]          = ['hash' => $hash, 'tokens' => $tokens];
        }

        return $stored;
    }

    /**
     * @return array{0: string, 1: string, 2: FileTokens}|null
     */
    private static function decodeFile(string $blob, int $len, int &$pos): ?array
    {
        $pathLen = self::readVarint($blob, $len, $pos);

        if ($pathLen === null || $pos + $pathLen > $len) {
            return null;
        }

        $path = substr($blob, $pos, $pathLen);
        $pos += $pathLen;

        if ($pos + self::HASH_BYTES > $len) {
            return null;
        }

        $hash = bin2hex(substr($blob, $pos, self::HASH_BYTES));
        $pos += self::HASH_BYTES;

        $numberOfLines = self::readVarint($blob, $len, $pos);
        $tokenCount    = self::readVarint($blob, $len, $pos);
        $sigLen        = self::readVarint($blob, $len, $pos);

        if ($numberOfLines === null || $tokenCount === null || $sigLen === null || $pos + $sigLen > $len) {
            return null;
        }

        $signature = substr($blob, $pos, $sigLen);
        $pos += $sigLen;

        $tokenLines     = self::readDeltaVarints($blob, $len, $pos, $tokenCount);
        $tokenRealLines = self::readDeltaVarints($blob, $len, $pos, $tokenCount);

        if ($tokenLines === null || $tokenRealLines === null) {
            return null;
        }

        return [$path, $hash, new FileTokens($numberOfLines, $signature, $tokenLines, $tokenRealLines)];
    }

    /** @param list<int> $values */
    private static function deltaVarints(array $values): string
    {
        $out  = '';
        $prev = 0;

        foreach ($values as $value) {
            $out .= self::varint($value - $prev);
            $prev = $value;
        }

        return $out;
    }

    /** @return list<int>|null */
    private static function readDeltaVarints(string $blob, int $len, int &$pos, int $count): ?array
    {
        $values = [];
        $acc    = 0;

        for ($i = 0; $i < $count; $i++) {
            $delta = self::readVarint($blob, $len, $pos);

            if ($delta === null) {
                return null;
            }

            $acc     += $delta;
            $values[] = $acc;
        }

        return $values;
    }

    private static function varint(int $n): string
    {
        $out = '';

        do {
            $byte = $n & 0x7F;
            $n  >>= 7;

            if ($n !== 0) {
                $byte |= 0x80;
            }

            $out .= chr($byte);
        } while ($n !== 0);

        return $out;
    }

    /** Reads an unsigned LEB128 varint at $pos, advancing it; null if truncated. */
    private static function readVarint(string $blob, int $len, int &$pos): ?int
    {
        $result = 0;
        $shift  = 0;

        while ($pos < $len) {
            $byte = ord($blob[$pos++]);
            $result |= ($byte & 0x7F) << $shift;

            if (($byte & 0x80) === 0) {
                return $result;
            }

            $shift += 7;
        }

        return null;
    }
}
