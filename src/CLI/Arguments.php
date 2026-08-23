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

final readonly class Arguments
{
    /**
     * @param list<non-empty-string> $directories
     * @param list<non-empty-string> $suffixes
     * @param list<non-empty-string> $exclude
     * @param list<non-empty-string> $noSuppress orphan suppression rules to disable by name
     * @param list<non-empty-string> $failOn     result tiers that make the run exit non-zero
     */
    public function __construct(
        private array $directories,
        private array $suffixes,
        private array $exclude,
        private ?string $pmdCpdXmlLogfile,
        private int $linesThreshold,
        private int $tokensThreshold,
        private bool $fuzzy,
        private bool $verbose,
        private bool $help,
        private bool $version,
        private ?string $algorithm,
        private int $editDistance,
        private int $headEquality,
        private ?string $jsonLogfile = null,
        private ?string $sarifLogfile = null,
        private float $similarity = 0.7,
        private ?string $cacheDir = null,
        private bool $typeAnchored = false,
        private bool $incremental = false,
        private bool $orphans = false,
        private bool $defaultExcludes = true,
        private array $noSuppress = [],
        private array $failOn = ['dead'],
        private bool $explain = false,
        private bool $showConfig = false,
    ) {}

    /** @return list<non-empty-string> */
    public function directories(): array
    {
        return $this->directories;
    }
    /** @return list<non-empty-string> */
    public function suffixes(): array
    {
        return $this->suffixes;
    }
    /** @return list<non-empty-string> */
    public function exclude(): array
    {
        return $this->exclude;
    }
    public function pmdCpdXmlLogfile(): ?string
    {
        return $this->pmdCpdXmlLogfile;
    }
    public function jsonLogfile(): ?string
    {
        return $this->jsonLogfile;
    }
    public function sarifLogfile(): ?string
    {
        return $this->sarifLogfile;
    }
    public function linesThreshold(): int
    {
        return $this->linesThreshold;
    }
    public function tokensThreshold(): int
    {
        return $this->tokensThreshold;
    }
    public function fuzzy(): bool
    {
        return $this->fuzzy;
    }
    public function verbose(): bool
    {
        return $this->verbose;
    }
    public function help(): bool
    {
        return $this->help;
    }
    public function version(): bool
    {
        return $this->version;
    }
    public function algorithm(): ?string
    {
        return $this->algorithm;
    }
    public function editDistance(): int
    {
        return $this->editDistance;
    }
    public function headEquality(): int
    {
        return $this->headEquality;
    }
    public function similarity(): float
    {
        return $this->similarity;
    }

    public function cacheDir(): ?string
    {
        return $this->cacheDir;
    }
    public function typeAnchored(): bool
    {
        return $this->typeAnchored;
    }
    public function incremental(): bool
    {
        return $this->incremental;
    }
    public function orphans(): bool
    {
        return $this->orphans;
    }
    public function defaultExcludes(): bool
    {
        return $this->defaultExcludes;
    }
    /** @return list<non-empty-string> */
    public function noSuppress(): array
    {
        return $this->noSuppress;
    }
    /** @return list<non-empty-string> */
    public function failOn(): array
    {
        return $this->failOn;
    }
    public function explain(): bool
    {
        return $this->explain;
    }
    public function showConfig(): bool
    {
        return $this->showConfig;
    }
}
