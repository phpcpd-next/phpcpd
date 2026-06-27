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

    /** @var array<string, array{hash: string, tokens: FileTokens}> */
    private array $stored = [];

    public function __construct(
        string $dir,
        private readonly string $configFingerprint,
        private readonly StrategyConfiguration $config,
    ) {
        $this->dir = rtrim($dir, '/\\');
    }

    /**
     * Update the index against the current file set and detect clones, re-tokenizing
     * only the files whose content changed. The updated index is persisted before
     * returning.
     *
     * @param list<string> $files
     */
    public function detect(array $files): IndexResult
    {
        $this->load();

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

            $tokens = $this->reuse($file, $hash);

            if ($tokens !== null) {
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
     * Returns the cached tokenization for $file if the index holds an entry whose
     * stored content hash still matches — otherwise null (added or changed), so the
     * file must be re-tokenized.
     */
    private function reuse(string $file, string $hash): ?FileTokens
    {
        $entry = $this->stored[$file] ?? null;

        if ($entry === null || $entry['hash'] !== $hash) {
            return null;
        }

        return $entry['tokens'];
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
