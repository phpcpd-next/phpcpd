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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy;

use function hash;

/**
 * The tokenization of a single file: everything the Rabin–Karp scanner needs to
 * find clones, with the source text already discarded.
 *
 * This is the unit the per-file incremental index persists. Re-tokenizing a file
 * (token_get_all + signature building) is the expensive step; caching this object
 * lets an unchanged file skip it entirely on the next run.
 *
 * - $signature   : 5 bytes per significant token (1-byte type + 4-byte crc32 of
 *                  its — possibly fuzz-normalized — text). Window hashes are md5
 *                  substrings of this. Binary.
 * - $tokenLines / $tokenRealLines : per-token line numbers (compressed and real),
 *                  indexed 0..count-1, used to compute a clone's line span. Both
 *                  are monotonic non-decreasing (tokens are in source order).
 *
 * A pure value object: serialization lives in {@see \LucianoPereira\PhpcpdNext\Cache\IndexCodec}.
 */
final readonly class FileTokens
{
    /**
     * How many bytes one significant token occupies in {@see $signature}.
     *
     * One `xxh64` over the token's type and its text together. It was five — a
     * type truncated to a byte plus a 32-bit crc of the text — and 32 bits is
     * narrow enough that two different literals in WordPress hashed alike, so
     * the matcher reported files holding them as an exact copy of one another.
     *
     * Stated once, here, because it was stated in eight places: this class, the
     * five unified-engine classes that slice the signature, and two tests. Every
     * one of them had to be found by hand when the width changed.
     */
    public const int TOKEN_BYTES = 8;

    /**
     * One token's bytes: a single hash over its type and its text together.
     *
     * Type and text are hashed as one value rather than concatenated as two
     * fields, so the width above is the whole identity of a token and nothing
     * reads inside it. Every consumer of a signature treats a token as an
     * opaque fixed-width blob and compares blobs, which is what let the width
     * change at all.
     */
    public static function token(int $type, string $text): string
    {
        return hash('xxh64', $type . "\0" . $text, true);
    }

    /**
     * @param list<int> $tokenLines
     * @param list<int> $tokenRealLines
     */
    public function __construct(
        public int $numberOfLines,
        public string $signature,
        public array $tokenLines,
        public array $tokenRealLines,
    ) {}
}
