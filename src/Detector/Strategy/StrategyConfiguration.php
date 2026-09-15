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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy;

/**
 * The slice of a run's settings the clone engine is allowed to see: thresholds
 * and normalization switches, nothing else. Strategies read these five values
 * and no others, so handing them the whole {@see \LucianoPereira\PhpcpdNext\Settings}
 * would hand them scan paths, log targets, and CLI flags they must never depend
 * on — this record is the boundary that keeps the engine embeddable.
 *
 * How much of a token the matcher sees is one value and not two: see
 * {@see Normalization}, which replaced a `fuzzy`/`typeAnchored` pair whose four
 * combinations covered three behaviours.
 *
 * Deliberately without defaults: every value is required, because the defaults
 * live in exactly one place ({@see \LucianoPereira\PhpcpdNext\Settings} property
 * initializers) and a second copy here is how the two would drift apart.
 * Construct one via {@see \LucianoPereira\PhpcpdNext\Settings::strategy()}.
 */
final readonly class StrategyConfiguration
{
    public function __construct(
        public int $minLines,
        public int $minTokens,
        public Normalization $normalization,
        public float $minSimilarity,
    ) {}
}
