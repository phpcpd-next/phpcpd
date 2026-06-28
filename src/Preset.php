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

namespace LucianoPereira\PhpcpdNext;

/**
 * A named bundle of framework-aware defaults — file suffixes, exclude patterns,
 * the directories worth scanning, and optional thresholds. A preset is *only*
 * configuration: it pulls in no runtime dependency and changes no detection
 * behaviour, so it stays faithful to the zero-dependency, deterministic core.
 *
 * Presets exist because every framework has predictable noise zones (generated
 * caches, scaffolded CRUD, migration boilerplate) that bury real findings. A
 * preset encodes that knowledge once so users don't re-derive it per project.
 *
 * Explicit CLI flags always win: a preset seeds the defaults, then `--exclude`
 * and `--suffix` append to it and `--min-lines` / `--min-tokens` override it.
 */
final readonly class Preset
{
    /**
     * @param non-empty-string       $name        identifier used with --preset
     * @param string                 $description shown in --help / --list-presets
     * @param list<non-empty-string> $suffixes    file suffixes to include
     * @param list<non-empty-string> $exclude     substring or glob patterns to skip
     * @param list<non-empty-string> $paths       directories scanned when none are given on the CLI
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $suffixes = ['.php'],
        public array $exclude = [],
        public array $paths = [],
        public ?int $minLines = null,
        public ?int $minTokens = null,
    ) {}
}
