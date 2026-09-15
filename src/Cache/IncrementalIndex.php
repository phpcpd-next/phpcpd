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

use function file_get_contents;
use function file_put_contents;
use function hash_file;
use function is_dir;
use function is_file;
use function mkdir;
use function rtrim;

use const LOCK_EX;

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\FileTokens;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\UnifiedStrategy;
use LucianoPereira\PhpcpdNext\InvalidStrategyException;

/**
 * Hummel's per-file incremental index (ICSM 2010).
 *
 * Where {@see CloneCache} is all-or-nothing — any single changed file invalidates
 * the whole run — this index works at file granularity. It persists each file's
 * tokenization ({@see FileTokens}) keyed by a content hash. On the next run, an
 * unchanged file is replayed straight from the index (no token_get_all), and only
 * the files whose content actually changed are re-tokenized.
 *
 * The detection itself is then replayed by feeding every file's FileTokens — cached
 * or freshly tokenized — through {@see DefaultStrategy::scan()} in the same order a
 * full pass would use. Because the chunk hashes and the merge are identical, the
 * result is byte-for-byte the same map a non-incremental Rabin–Karp run produces;
 * incrementality only changes how much work is skipped, never the answer.
 *
 * The index is stored as a compact binary blob ({@see IndexCodec}) at
 * {dir}/{fingerprint}.idx.bin — one file per configuration, so different configs
 * coexist and the coarse {@see CloneCache} ({fingerprint}.json) never collides.
 */
final class IncrementalIndex
{
    private readonly string $dir;

    /** @var array<string, array{hash: string, tokens: FileTokens, fingerprints?: list<array{0: string, 1: int}>, normalized?: string, normalizedFingerprints?: list<array{0: string, 1: int}>}> */
    private array $stored = [];

    public function __construct(
        string $dir,
        private readonly string $configFingerprint,
        private readonly StrategyConfiguration $config,
        private readonly string $algorithm = 'rabin-karp',
    ) {
        $this->dir = rtrim($dir, '/\\');
    }

    /**
     * Update the index against the current file set and detect clones, re-tokenizing
     * only the files whose content changed. The updated index is persisted before
     * returning.
     *
     * @param list<string> $files
     * @throws InvalidStrategyException
     */
    public function detect(array $files): IndexResult
    {
        $this->load();

        $result = $this->algorithm === 'unified'
            ? $this->detectUnified($files)
            : $this->detectRabinKarp($files);

        // The map is assembled here rather than by `Engine::detect()`, so the
        // settling the engine does has to be asked for. An index is allowed to
        // skip work and never to change the answer — without this the indexed
        // run reported 18 clones where the plain run reported 17, which is the
        // one thing `bench/check-incremental.php` exists to catch.
        $result->clones->settle();

        return $result;
    }

    /** @param list<string> $files */
    private function detectRabinKarp(array $files): IndexResult
    {
        $strategy = new DefaultStrategy($this->config);
        $map      = new CodeCloneMap();
        $reused   = 0;
        $scanned  = 0;
        $manifest = [];

        foreach ($files as $file) {
            $hash = hash_file('sha256', $file);

            if ($hash === false) {
                continue;
            }

            $entry = $this->reuse($file, $hash);

            if ($entry !== null) {
                $tokens = $entry['tokens'];
                $reused++;
            } else {
                $buffer = file_get_contents($file);

                if ($buffer === false) {
                    continue;
                }

                $tokens = $strategy->tokenize($buffer);
                $scanned++;
            }

            $strategy->scan($file, $tokens, $map);
            $manifest[$file] = ['hash' => $hash, 'tokens' => $tokens];
        }

        $this->stored = $manifest;
        $this->save();

        return new IndexResult($map, $reused, $scanned);
    }

    /**
     * The same idea one stage further along.
     *
     * Rabin-Karp's expensive per-file step is tokenization; the unified engine has
     * a second one, winnowing the signature into its selected fingerprints. Both
     * are pure functions of the file's bytes and the configuration, so both are
     * cached together and an unchanged file costs neither. What is left for a warm
     * run is the cross-file work — building the postings table and extending the
     * seeds — which depends on the whole corpus and so cannot be cached per file.
     *
     * @param list<string> $files
     * @throws InvalidStrategyException
     */
    private function detectUnified(array $files): IndexResult
    {
        $strategy = new UnifiedStrategy($this->config);
        $map      = new CodeCloneMap();
        $reused   = 0;
        $scanned  = 0;
        $manifest = [];

        foreach ($files as $file) {
            $hash = hash_file('sha256', $file);

            if ($hash === false) {
                continue;
            }

            $entry            = $this->reuse($file, $hash);
            $fingerprints     = $entry['fingerprints'] ?? null;
            $normalized       = $entry['normalized'] ?? null;
            $normalizedPrints = $entry['normalizedFingerprints'] ?? null;

            if ($entry !== null && $fingerprints !== null && $normalized !== null && $normalizedPrints !== null) {
                $tokens = $entry['tokens'];
                $reused++;
            } else {
                $buffer = file_get_contents($file);

                if ($buffer === false) {
                    continue;
                }

                $tokens           = $strategy->encode($buffer);
                $fingerprints     = $strategy->fingerprint($tokens->signature);
                $normalized       = $strategy->encodeNormalized($buffer);
                $normalizedPrints = $strategy->fingerprint($normalized, normalized: true);
                $scanned++;
            }

            $strategy->add($file, $tokens, $fingerprints, $normalized, $normalizedPrints, $map);
            $manifest[$file] = [
                'hash'                   => $hash,
                'tokens'                 => $tokens,
                'fingerprints'           => $fingerprints,
                'normalized'             => $normalized,
                'normalizedFingerprints' => $normalizedPrints,
            ];
        }

        $strategy->postProcess();

        $this->stored = $manifest;
        $this->save();

        return new IndexResult($map, $reused, $scanned);
    }

    /**
     * The cached entry for $file if the index holds one whose stored content hash
     * still matches — otherwise null (added or changed), so the file must be
     * re-encoded.
     *
     * @return array{hash: string, tokens: FileTokens, fingerprints?: list<array{0: string, 1: int}>, normalized?: string, normalizedFingerprints?: list<array{0: string, 1: int}>}|null
     */
    private function reuse(string $file, string $hash): ?array
    {
        $entry = $this->stored[$file] ?? null;

        if ($entry === null || $entry['hash'] !== $hash) {
            return null;
        }

        return $entry;
    }

    private function load(): void
    {
        $this->stored = [];

        $path = $this->indexPath();

        if (!is_file($path)) {
            return;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return;
        }

        $this->stored = IndexCodec::decode($raw);
    }

    private function save(): void
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, recursive: true);
        }

        file_put_contents($this->indexPath(), IndexCodec::encode($this->stored), LOCK_EX);
    }

    private function indexPath(): string
    {
        return $this->dir . '/' . $this->configFingerprint . '.idx.bin';
    }
}
