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

namespace LucianoPereira\PhpcpdNext\Facts;

use function array_key_exists;

/**
 * {@see FileFacts}, memoised per path for the life of one report.
 *
 * Instance state rather than a static cache, deliberately. `CodeClone`'s line
 * cache is static and had to grow a modification stamp so that an embedder
 * editing a file between two scans in one process could not be handed the first
 * scan's bytes; an index that lives exactly as long as the report it serves
 * cannot have that problem at all.
 */
final class FileFactsIndex
{
    /** @var array<string, ?FileFacts> */
    private array $facts = [];

    public function for(string $path): ?FileFacts
    {
        if (!array_key_exists($path, $this->facts)) {
            $this->facts[$path] = FileFacts::read($path);
        }

        return $this->facts[$path];
    }

    /** How many files this report actually had to analyse — reported, never silent. */
    public function analysed(): int
    {
        $count = 0;

        foreach ($this->facts as $facts) {
            $count += $facts === null ? 0 : 1;
        }

        return $count;
    }
}
