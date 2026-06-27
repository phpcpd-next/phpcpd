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
