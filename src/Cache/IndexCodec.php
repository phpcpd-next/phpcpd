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
 * The index is dominated by two kinds of per-token data: the signature (8 bytes
 * per token of crc32 hashes — high entropy, stored raw) and two line-number arrays
 * (monotonic non-decreasing — stored as deltas under unsigned LEB128 varints, so
 * each typically costs one byte). Storing the signature raw rather than base64'd,
 * and the positions delta+varint rather than as JSON integers, makes the index
 * roughly a third the size of the equivalent JSON with no dependency beyond the
 * core pack()/ord()/chr() primitives.
 *
 * Since version 3 an entry also carries what the unified engine needs to replay a
 * file without touching it: the *selected fingerprints* of the raw view, and the
 * whole second (normalized) view — its signature and its own fingerprints. All of
 * them are pure functions of the file's bytes and the configuration, so an
 * unchanged file's cannot have changed either, and recomputing them is the entire
 * per-token cost an incremental run would otherwise still pay. The normalized
 * view needs no second pair of line arrays: normalization rewrites token *text*
 * and never changes how many tokens there are or which line each sits on.
 * Rabin-Karp entries carry none of it, at a cost of three bytes.
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
 *     fingerprintCount (F)
 *     F × 8 raw bytes  fingerprints, in ascending position order
 *     F × varint       delta-encoded token positions
 *     normSigLen | normalized signature (normSigLen raw bytes)
 *     normFingerprintCount (G)
 *     G × 8 raw bytes  normalized-view fingerprints
 *     G × varint       delta-encoded token positions
 *
 * decode() is total: any malformation (bad magic/version, truncation, a hash that
 * is not 32 bytes) yields an empty map, so a corrupt index — or one written by an
 * older version — degrades to a full re-scan rather than a fatal error.
 */
final class IndexCodec
{
    private const string MAGIC   = 'PXI1';

    /**
     * Held at 5 through {@see AnchorSet::SEED_PAIR_CAP} (M3 audit ruling N), by
     * the M3 close audit (ruling Q), reverting a version-6 bump that briefly
     * existed on this branch. The cap applies after the index, to the pairs a
     * posting list is enumerated into, so no cached fingerprint changes and a
     * version-5 entry decodes to exactly what a fresh run selects; the seeding
     * rule is applied at run time either way, so the hazard the bumps below
     * guard against — replaying entries a current run would not have selected —
     * cannot occur here. Invalidating every user's warm cache for a change that
     * alters no stored byte and no selection rule buys nothing. A version-6
     * entry left behind by the bump's brief window is rejected by the mismatch
     * and rescanned once, which is harmless and correct.
     *
     * Bumped to 5 for {@see FingerprintIndex::PER_FILE_CAP} (M2 audit ruling D):
     * a fingerprint that repeats inside one file now contributes at most that
     * many occurrences, so the fingerprint list a file stores is no longer the
     * same list a version-4 run produced from the same bytes. Replaying one would
     * hand the index the runaway occurrences the cap exists to keep out, and do
     * it invisibly, since a cached entry carries no record of which rule selected
     * it.
     *
     * Bumped to 4 for the M2 normalized-seed diversity guard: which normalized
     * k-grams get fingerprinted became a function of {@see Winnower}'s
     * diversity floor as well as file content and config, so a version-3 index
     * carried fingerprints a version-4 run would not have selected. Reading it
     * back would silently reintroduce exactly the false seeds the guard exists
     * to remove; a version bump forces the clean rescan that formula change
     * needs, the same way any other change to what a cached entry means would.
     */
    private const int VERSION    = 7;
    private const int HASH_BYTES = 32;

    /** Raw bytes of one xxh3 fingerprint, as {@see Winnower} produces them. */
    private const int FINGERPRINT_BYTES = 8;

    /**
     * @param array<string, array{hash: string, tokens: FileTokens, fingerprints?: list<array{0: string, 1: int}>, normalized?: string, normalizedFingerprints?: list<array{0: string, 1: int}>}> $stored
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

            $out .= self::fingerprintBlock($entry['fingerprints'] ?? []);

            $normalized = $entry['normalized'] ?? '';
            $out .= self::varint(strlen($normalized)) . $normalized;
            $out .= self::fingerprintBlock($entry['normalizedFingerprints'] ?? []);
        }

        return $out;
    }

    /** @param list<array{0: string, 1: int}> $fingerprints */
    private static function fingerprintBlock(array $fingerprints): string
    {
        $positions = [];
        $raw       = '';

        foreach ($fingerprints as [$fingerprint, $position]) {
            $raw .= $fingerprint;
            $positions[] = $position;
        }

        return self::varint(count($fingerprints)) . $raw . self::deltaVarints($positions);
    }

    /**
     * @return array<string, array{hash: string, tokens: FileTokens, fingerprints: list<array{0: string, 1: int}>, normalized: string, normalizedFingerprints: list<array{0: string, 1: int}>}>
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

            [$path, $hash, $tokens, $fingerprints, $normalized, $normalizedFingerprints] = $entry;
            $stored[$path]                                                               = [
                'hash'                   => $hash,
                'tokens'                 => $tokens,
                'fingerprints'           => $fingerprints,
                'normalized'             => $normalized,
                'normalizedFingerprints' => $normalizedFingerprints,
            ];
        }

        return $stored;
    }

    /**
     * @return array{0: string, 1: string, 2: FileTokens, 3: list<array{0: string, 1: int}>, 4: string, 5: list<array{0: string, 1: int}>}|null
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

        $fingerprints = self::readFingerprintBlock($blob, $len, $pos);

        if ($fingerprints === null) {
            return null;
        }

        $normalizedLength = self::readVarint($blob, $len, $pos);

        if ($normalizedLength === null || $pos + $normalizedLength > $len) {
            return null;
        }

        $normalized = substr($blob, $pos, $normalizedLength);
        $pos += $normalizedLength;

        $normalizedFingerprints = self::readFingerprintBlock($blob, $len, $pos);

        if ($normalizedFingerprints === null) {
            return null;
        }

        return [
            $path,
            $hash,
            new FileTokens($numberOfLines, $signature, $tokenLines, $tokenRealLines),
            $fingerprints,
            $normalized,
            $normalizedFingerprints,
        ];
    }

    /** @return list<array{0: string, 1: int}>|null */
    private static function readFingerprintBlock(string $blob, int $len, int &$pos): ?array
    {
        $count = self::readVarint($blob, $len, $pos);

        if ($count === null || $pos + $count * self::FINGERPRINT_BYTES > $len) {
            return null;
        }

        $raw = [];

        for ($i = 0; $i < $count; $i++) {
            $raw[] = substr($blob, $pos, self::FINGERPRINT_BYTES);
            $pos += self::FINGERPRINT_BYTES;
        }

        $positions = self::readDeltaVarints($blob, $len, $pos, $count);

        if ($positions === null) {
            return null;
        }

        $fingerprints = [];

        foreach ($raw as $i => $fingerprint) {
            $fingerprints[] = [$fingerprint, $positions[$i]];
        }

        return $fingerprints;
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
