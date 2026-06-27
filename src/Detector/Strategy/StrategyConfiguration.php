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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy;

use LucianoPereira\PhpcpdNext\Arguments;

final readonly class StrategyConfiguration
{
    private int $minLines;
    private int $minTokens;
    private int $editDistance;
    private int $headEquality;
    private bool $fuzzy;
    private bool $typeAnchored;
    private float $similarity;

    public function __construct(Arguments $arguments)
    {
        $this->minLines     = $arguments->linesThreshold();
        $this->minTokens    = $arguments->tokensThreshold();
        $this->fuzzy        = $arguments->fuzzy();
        $this->typeAnchored = $arguments->typeAnchored();
        $this->editDistance = $arguments->editDistance();
        $this->headEquality = $arguments->headEquality();
        $this->similarity   = $arguments->similarity();
    }

    public function minLines(): int
    {
        return $this->minLines;
    }
    public function minTokens(): int
    {
        return $this->minTokens;
    }
    public function fuzzy(): bool
    {
        return $this->fuzzy;
    }
    public function typeAnchored(): bool
    {
        return $this->typeAnchored;
    }
    public function headEquality(): int
    {
        return $this->headEquality;
    }
    public function editDistance(): int
    {
        return $this->editDistance;
    }
    public function similarity(): float
    {
        return $this->similarity;
    }
}
