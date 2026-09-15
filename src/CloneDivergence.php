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
 * One stretch where two copies of a clone stop agreeing.
 *
 * A clone detector's usual answer here is a boolean — `gapped: true`, this
 * clone's copies are not identical — and that boolean is the most bug-predictive
 * signal a clone detector produces (Juergens et al., ICSE 2009: one copy gets
 * patched, its sibling does not). It is what the removed suffix-tree engine
 * reported. But a boolean does not say *where*, so acting on it means diffing the
 * two copies by hand.
 *
 * This is the same signal with the location kept. A divergence names the file,
 * the lines a reader should look at, and the exact token range the alignment
 * found — one of these per side of the clone, so the two can be read against
 * each other.
 *
 * Token positions are indices into the file's significant-token stream, the same
 * unit `--min-tokens` is counted in, and are what a property test can check
 * without re-deriving line numbers. `tokens` is zero for a pure insertion on the
 * other side: the divergence exists here as a point where something is missing,
 * and saying so is more useful than omitting it.
 *
 * A **reordered** clone reuses the same shape for a different fact: not a place
 * the copies stop agreeing, but the block that moved. Two entries — one per
 * side — name where the displaced material sits in each file, so a reader can
 * see which statements swapped without diffing the two copies by hand.
 *
 * ## Inside the clone, or past its edge
 *
 * Most divergences are **internal**: they sit between two exact runs of the
 * clone, and both sides' spans include them. A **bounded edge divergence** (M2
 * audit ruling C) is a different fact wearing the same shape — one copy simply
 * continues past the shared part further than the other does — and it sits
 * *outside* both spans by construction.
 *
 * The distinction is reported rather than left to be inferred, because it cannot
 * be inferred: a reader given only line numbers cannot tell the two apart once a
 * class is sized by its lead member, and neither can a checker. It is `$edge`
 * that lets the probe suite reconstruct each side's true span length from public
 * data and so verify the 0.85 similarity invariant independently.
 */
final readonly class CloneDivergence
{
    /**
     * @param bool $edge a bounded edge divergence (ruling C) — material past the
     *                   end of the shared span rather than a gap inside it, and
     *                   therefore no part of either side's span length
     */
    public function __construct(
        public string $file,
        public int $startLine,
        public int $endLine,
        public int $startToken,
        public int $tokens,
        public bool $edge = false,
    ) {}

    /**
     * The `edge` key appears only when it is true, so every report this tool has
     * ever written for an internal divergence is unchanged byte for byte.
     *
     * @return array{path: string, startLine: int, endLine: int, startToken: int, tokens: int, edge?: bool}
     */
    public function toArray(): array
    {
        $data = [
            'path'       => $this->file,
            'startLine'  => $this->startLine,
            'endLine'    => $this->endLine,
            'startToken' => $this->startToken,
            'tokens'     => $this->tokens,
        ];

        if ($this->edge) {
            $data['edge'] = true;
        }

        return $data;
    }
}
