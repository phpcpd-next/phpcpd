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

use function array_values;
use function count;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function hash_file;
use function is_array;
use function is_bool;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function mkdir;
use function rtrim;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const LOCK_EX;
use const SORT_STRING;

use LucianoPereira\PhpcpdNext\Arguments;
use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;

/**
 * File-manifest cache for incremental CI runs.
 *
 * Each cache entry lives at {dir}/{configFingerprint}.json and contains the
 * sha256 hash of every scanned file. A hit requires ALL file hashes to match —
 * any changed, added, or removed file is a miss and triggers a full re-scan.
 * Different algorithm/threshold combos produce different fingerprints and
 * coexist in the same cache directory.
 */
final class CloneCache
{
    private const int VERSION = 2;

    private readonly string $dir;

    public function __construct(string $dir, private readonly string $configFingerprint)
    {
        $this->dir = rtrim($dir, '/\\');
    }

    /**
     * Returns a cached CodeCloneMap on hit, null on miss.
     *
     * @param list<string> $files
     */
    public function get(array $files): ?CodeCloneMap
    {
        $path = $this->cachePath();

        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        try {
            $data = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data) || ($data['v'] ?? null) !== self::VERSION) {
            return null;
        }

        if (!$this->manifestMatches($files, $data)) {
            return null;
        }

        return $this->hydrate($data);
    }

    /**
     * Persists a CodeCloneMap to the cache directory. Silently no-ops on IO error.
     *
     * @param list<string> $files
     */
    public function put(array $files, CodeCloneMap $clones): void
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, recursive: true);
        }

        $clonesData = [];

        foreach ($clones->clones() as $clone) {
            $clonesData[] = $clone->toArray();
        }

        try {
            $json = json_encode([
                'v'              => self::VERSION,
                'files'          => $this->buildManifest($files),
                'totalLines'     => $clones->numberOfLines(),
                'duplicatedLines' => $clones->numberOfDuplicatedLines(),
                'clones'         => $clonesData,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        file_put_contents($this->cachePath(), $json, LOCK_EX);
    }

    /**
     * Stable fingerprint for a detection configuration. Used as the cache filename
     * so different algorithm/threshold combos coexist in the same cache directory.
     */
    public static function configFingerprint(Arguments $args): string
    {
        return hash('sha256', json_encode([
            'algorithm'    => $args->algorithm() ?? 'rabin-karp',
            'minTokens'    => $args->tokensThreshold(),
            'minLines'     => $args->linesThreshold(),
            'fuzzy'        => $args->fuzzy(),
            'editDistance' => $args->editDistance(),
            'headEquality' => $args->headEquality(),
            'similarity'   => $args->similarity(),
        ], JSON_THROW_ON_ERROR));
    }

    private function cachePath(): string
    {
        return $this->dir . '/' . $this->configFingerprint . '.json';
    }

    /**
     * @param list<string> $files
     * @param array<int|string, mixed> $data
     */
    private function manifestMatches(array $files, array $data): bool
    {
        $cachedFiles = $data['files'] ?? null;

        if (!is_array($cachedFiles)) {
            return false;
        }

        $current = $this->buildManifest($files);

        if (count($cachedFiles) !== count($current)) {
            return false;
        }

        foreach ($current as $path => $hash) {
            if (($cachedFiles[$path] ?? null) !== $hash) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $files
     * @return array<string, string>
     */
    private function buildManifest(array $files): array
    {
        $manifest = [];

        foreach ($files as $file) {
            $hash = hash_file('sha256', $file);

            if ($hash !== false) {
                $manifest[$file] = $hash;
            }
        }

        ksort($manifest, SORT_STRING);

        return $manifest;
    }

    /** @param array<int|string, mixed> $data */
    private function hydrate(array $data): CodeCloneMap
    {
        $map = new CodeCloneMap();

        $totalLines = $data['totalLines'] ?? 0;

        if (is_int($totalLines)) {
            $map->addToNumberOfLines($totalLines);
        }

        $clonesRaw = $data['clones'] ?? [];

        if (is_array($clonesRaw)) {
            foreach ($clonesRaw as $cloneRaw) {
                if (!is_array($cloneRaw)) {
                    continue;
                }

                $clone = $this->hydrateClone($cloneRaw);

                if ($clone !== null) {
                    $map->add($clone);
                }
            }
        }

        // Restore the exact duplicated-line count. map->add() computes it as
        // lines*(files-1) per call, which is correct for 2-file clones but
        // diverges for 3+-file clones built incrementally by the strategies.
        $duplicatedLines = $data['duplicatedLines'] ?? null;

        if (is_int($duplicatedLines)) {
            $map->setNumberOfDuplicatedLines($duplicatedLines);
        }

        return $map;
    }

    /** @param array<string|int, mixed> $data */
    private function hydrateClone(array $data): ?CodeClone
    {
        $filesRaw = $data['files'] ?? [];

        if (!is_array($filesRaw)) {
            return null;
        }

        $filesRaw = array_values($filesRaw);

        if (count($filesRaw) < 2) {
            return null;
        }

        $fileA = $this->hydrateFile($filesRaw[0]);
        $fileB = $this->hydrateFile($filesRaw[1]);

        if ($fileA === null || $fileB === null) {
            return null;
        }

        $lines  = $data['lines'] ?? 0;
        $tokens = $data['tokens'] ?? 0;
        $gapped = $data['gapped'] ?? false;

        if (!is_int($lines) || !is_int($tokens) || !is_bool($gapped)) {
            return null;
        }

        $clone = new CodeClone($fileA, $fileB, $lines, $tokens, $gapped);

        for ($i = 2, $n = count($filesRaw); $i < $n; $i++) {
            $extra = $this->hydrateFile($filesRaw[$i]);

            if ($extra !== null) {
                $clone->add($extra);
            }
        }

        return $clone;
    }

    /**  */
    private function hydrateFile(mixed $data): ?CodeCloneFile
    {
        if (!is_array($data)) {
            return null;
        }

        $path      = $data['path'] ?? null;
        $startLine = $data['line'] ?? null;

        if (!is_string($path) || $path === '' || !is_int($startLine)) {
            return null;
        }

        return new CodeCloneFile($path, $startLine);
    }
}
