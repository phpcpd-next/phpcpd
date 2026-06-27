<?php

declare(strict_types=1);
/*
 * This file is part of PHP Copy/Paste Detector (PHPCPD).
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LucianoPereira\PhpcpdNext;

use function array_map;
use function array_slice;
use function array_values;
use function file;
use function implode;
use function md5;

final class CodeClone
{
    private int $numberOfLines;
    private int $numberOfTokens;
    /** @var array<string, CodeCloneFile> */
    private array $files = [];
    private string $id;
    private string $lines = '';
    private bool $gapped;

    public function __construct(CodeCloneFile $fileA, CodeCloneFile $fileB, int $numberOfLines, int $numberOfTokens, bool $gapped = false)
    {
        $this->add($fileA);
        $this->add($fileB);

        $this->numberOfLines  = $numberOfLines;
        $this->numberOfTokens = $numberOfTokens;
        $this->gapped         = $gapped;
        $this->id             = md5($this->lines());
    }

    public function add(CodeCloneFile $file): void
    {
        $id = $file->id();

        if (!isset($this->files[$id])) {
            $this->files[$id] = $file;
        }
    }

    /** @return array<string, CodeCloneFile> */
    public function files(): array
    {
        return $this->files;
    }

    public function lines(string $indent = ''): string
    {
        if (empty($this->lines)) {
            $file = array_values($this->files)[0];

            $this->lines = implode(
                '',
                array_map(
                    static fn(string $line) => $indent . $line,
                    array_slice(
                        file($file->name()) ?: [],
                        $file->startLine() - 1,
                        $this->numberOfLines,
                    ),
                ),
            );
        }

        return $this->lines;
    }

    public function id(): string
    {
        return $this->id;
    }

    /**
     * Whether the two copies are NOT token-identical — a gapped (Type-3) clone
     * where the suffix-tree edit-distance budget bridged a difference. Exact
     * (Type-1) clones, and all Rabin-Karp clones, return false.
     */
    public function isGapped(): bool
    {
        return $this->gapped;
    }
    public function numberOfLines(): int
    {
        return $this->numberOfLines;
    }
    public function numberOfTokens(): int
    {
        return $this->numberOfTokens;
    }

    /**
     * Canonical array representation shared by JSON output and the clone cache.
     * Field names: path/line (public-facing convention).
     *
     * @return array{lines:int, tokens:int, gapped:bool, files:list<array{path:string, line:int}>}
     */
    public function toArray(): array
    {
        $files = [];

        foreach ($this->files as $file) {
            $files[] = ['path' => $file->name(), 'line' => $file->startLine()];
        }

        return [
            'lines'  => $this->numberOfLines,
            'tokens' => $this->numberOfTokens,
            'gapped' => $this->gapped,
            'files'  => $files,
        ];
    }
}
