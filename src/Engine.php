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

use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTreeStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBagStrategy;

/**
 * The headless detection core: given a configuration and a list of files, it
 * produces a CodeCloneMap with no I/O, no console output, and no caching side
 * effects. Both the CLI (Application) and embedders (the Phpcpd facade, the
 * PHPUnit constraint, a Laravel command) run detection through this one class,
 * so they can never disagree about what a "clone" is.
 *
 * A null algorithm runs the project default — Rabin-Karp (exact clones) merged
 * with TokenBag (reordered clones) — exactly as the bare `phpcpd <dir>` command
 * does. A named algorithm runs that single engine.
 */
final readonly class Engine
{
    public function __construct(
        private StrategyConfiguration $config,
        private ?string $algorithm = null,
    ) {}

    /**
     * @param list<string> $files
     * @throws InvalidStrategyException
     */
    public function detect(array $files): CodeCloneMap
    {
        if ($this->algorithm === null) {
            // Default: Rabin-Karp (exact) + TokenBag (reordered), merged.
            $clones = (new Detector(new DefaultStrategy($this->config)))->copyPasteDetection($files);
            $clones->mergeFrom((new Detector(new TokenBagStrategy($this->config)))->copyPasteDetection($files));

            return $clones;
        }

        return (new Detector($this->strategyFor($this->algorithm)))->copyPasteDetection($files);
    }

    /**
     * @throws InvalidStrategyException
     */
    public function strategyFor(string $algorithm): AbstractStrategy
    {
        return match ($algorithm) {
            'rabin-karp' => new DefaultStrategy($this->config),
            'suffixtree' => new SuffixTreeStrategy($this->config),
            'tokenbag'   => new TokenBagStrategy($this->config),
            default      => throw new InvalidStrategyException('Unsupported algorithm: ' . $algorithm),
        };
    }
}
