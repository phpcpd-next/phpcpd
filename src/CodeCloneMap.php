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

use function count;
use function max;
use function sprintf;

/** @implements \IteratorAggregate<int, CodeClone> */
final class CodeCloneMap implements \Countable, \IteratorAggregate
{
    /** @var list<CodeClone> */
    private array $clones          = [];
    /** @var array<string, CodeClone> */
    private array $clonesById      = [];
    private int $numberOfDuplicatedLines = 0;
    private int $numberOfLines     = 0;
    private int $largestCloneSize  = 0;
    /** @var array<string, bool> */
    private array $filesWithClones = [];
    /**
     * Tracks (file:startLine) pairs already counted toward $numberOfDuplicatedLines.
     * Prevents the same source location from being counted twice when a function
     * appears in multiple clone pairs (e.g. tokenbag 1:N clones reported as N-1 pairs).
     *
     * @var array<string, bool>
     */
    private array $countedCloneParticipants = [];

    public function add(CodeClone $clone): void
    {
        $id = $clone->id();

        if (!isset($this->clonesById[$id])) {
            $this->clones[]        = $clone;
            $this->clonesById[$id] = $clone;
        } else {
            $existClone = $this->clonesById[$id];

            foreach ($clone->files() as $file) {
                $existClone->add($file);
            }
        }

        $newCount      = 0;
        $existingCount = 0;

        foreach ($clone->files() as $file) {
            $key = $file->name() . ':' . $file->startLine();

            if (!isset($this->countedCloneParticipants[$key])) {
                $this->countedCloneParticipants[$key] = true;
                $newCount++;
            } else {
                $existingCount++;
            }

            if (!isset($this->filesWithClones[$file->name()])) {
                $this->filesWithClones[$file->name()] = true;
            }
        }

        // "Extra copies" counting — compatible with how rabin-karp merges multi-file
        // clones while also correct for tokenbag's pair-per-match output:
        //
        //   All participants new  → subtract 1 for the "original": (new - 1) × lines
        //   Some already counted  → each new participant is one more extra copy: new × lines
        //
        // Example: A cloned to B and C reported as three pairs {A,B},{A,C},{B,C}:
        //   {A,B}: 2 new, 0 existing → 1 extra copy → +N
        //   {A,C}: 1 new, 1 existing → 1 extra copy → +N   (C is a new extra)
        //   {B,C}: 0 new             → 0             → +0
        //   Total: 2N = same as a single 3-file rabin-karp clone (3-1)×N = 2N
        $extraCopies = $existingCount > 0
            ? $newCount
            : max(0, $newCount - 1);

        $this->numberOfDuplicatedLines += $clone->numberOfLines() * $extraCopies;

        $this->largestCloneSize = max($this->largestCloneSize, $clone->numberOfLines());
    }

    /** @return list<CodeClone> */
    public function clones(): array
    {
        return $this->clones;
    }

    public function percentage(): string
    {
        if ($this->numberOfLines > 0) {
            $percent = ($this->numberOfDuplicatedLines / $this->numberOfLines) * 100;
        } else {
            $percent = 0;
        }

        return sprintf('%01.2F%%', $percent);
    }

    public function numberOfLines(): int
    {
        return $this->numberOfLines;
    }
    public function addToNumberOfLines(int $numberOfLines): void
    {
        $this->numberOfLines += $numberOfLines;
    }
    #[\Override]
    public function count(): int
    {
        return count($this->clones);
    }
    public function numberOfFilesWithClones(): int
    {
        return count($this->filesWithClones);
    }

    /**
     * Number of gapped (Type-3 / inconsistent) clones — copies that share a
     * skeleton but diverge. These carry the bug risk: one copy patched, the
     * sibling not. See CodeClone::isGapped().
     */
    public function numberOfGappedClones(): int
    {
        $count = 0;

        foreach ($this->clones as $clone) {
            if ($clone->isGapped()) {
                $count++;
            }
        }

        return $count;
    }
    public function numberOfDuplicatedLines(): int
    {
        return $this->numberOfDuplicatedLines;
    }

    public function setNumberOfDuplicatedLines(int $n): void
    {
        $this->numberOfDuplicatedLines = $n;
    }

    /**
     * Merge all clones from $other into this map.
     * numberOfLines is NOT merged — the same files were already counted by the
     * first strategy that built $this; adding them again would double the total.
     */
    public function mergeFrom(CodeCloneMap $other): void
    {
        foreach ($other->clones() as $clone) {
            $this->add($clone);
        }
    }
    #[\Override]
    public function getIterator(): CodeCloneMapIterator
    {
        return new CodeCloneMapIterator($this);
    }
    public function isEmpty(): bool
    {
        return empty($this->clones);
    }
    public function averageSize(): float
    {
        if ($this->count() === 0) {
            return 0.0;
        }

        return $this->numberOfDuplicatedLines() / $this->count();
    }
    public function largestSize(): int
    {
        return $this->largestCloneSize;
    }
}
