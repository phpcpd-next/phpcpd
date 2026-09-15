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

use function count;
use function sort;

use const SORT_STRING;

use LucianoPereira\PhpcpdNext\Detector\CloneSuppressions;
use LucianoPereira\PhpcpdNext\Detector\CoherentClasses;
use LucianoPereira\PhpcpdNext\Detector\Detector;
use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Detector\Strategy\TokenBagStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Unified\UnifiedStrategy;

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
     * `$onProgress` is called as each file is processed, with the name of the
     * pass doing the processing, how many of its files are done, and how many
     * there are. The default run makes two passes over the same files, so a
     * caller that reported one undifferentiated count would show the scan
     * finishing twice; naming the pass is what makes the number readable.
     *
     * Headless by default: pass nothing and this class does no I/O, which is
     * what lets the CLI, the facade and the PHPUnit constraint share it.
     *
     * @param list<string> $files
     * @param ?callable(string, int, int): void $onProgress
     * @throws InvalidStrategyException
     */
    public function detect(array $files, ?callable $onProgress = null): CodeCloneMap
    {
        // The answer depends on which files were given, never on the order they
        // arrived in.
        //
        // Every engine here is order-sensitive by construction, and legitimately
        // so: Rabin-Karp records the first occurrence of a window and reports
        // later ones against it, and the token bag compares each block against
        // those already seen. Hand the same files over in another order and the
        // roles swap, which is not a re-ordering of one report but a different
        // set of pairs. FileFinder already sorts, so a scan started from a
        // directory was stable; a caller that assembled its own list — an
        // embedder, a test, a `--` argument list — got no such guarantee.
        //
        // Sorting here rather than in each engine keeps the one guarantee in the
        // one place all callers pass through.
        sort($files, SORT_STRING);

        if ($this->algorithm === null) {
            // Default: Rabin-Karp (exact) + TokenBag (reordered), merged.
            $clones = (new Detector(new DefaultStrategy($this->config)))
                ->copyPasteDetection($files, $this->counter('exact', $files, $onProgress));

            $clones->mergeFrom(
                (new Detector(new TokenBagStrategy($this->config)))
                    ->copyPasteDetection($files, $this->counter('reordered', $files, $onProgress)),
            );

            // Once, on the merged map, and in the order the single-engine path
            // below uses. `mergeFrom()` drops a clone another already describes,
            // which is only half of settling: the region collapse lives in
            // `settle()` and the merged pipeline never reached it, so the
            // shipped default reported a self-similar run once per period where
            // every single engine reported it once. On phpunit that was 312
            // clones from a pipeline whose two halves find 170 and 37.
            $clones->settle();

            // Suppression is a decision about what to report, and there is one
            // report however many passes produced it.
            return CloneSuppressions::applyTo(
                CoherentClasses::applyTo($clones, $this->config),
            );
        }

        $single = (new Detector($this->strategyFor($this->algorithm)))
            ->copyPasteDetection($files, $this->counter($this->algorithm, $files, $onProgress));

        // Both paths settle, and both settle once. This one had nowhere to do
        // it at all until the map learned how.
        $single->settle();

        return CloneSuppressions::applyTo(
            CoherentClasses::applyTo($single, $this->config),
        );
    }

    /**
     * Turns "a file finished" into "the nth of m files of this pass finished".
     * The count belongs here because this is where the passes are known.
     *
     * @param list<string> $files
     * @param ?callable(string, int, int): void $onProgress
     * @return ?callable(): void
     */
    private function counter(string $phase, array $files, ?callable $onProgress): ?callable
    {
        if ($onProgress === null) {
            return null;
        }

        $total = count($files);
        $done  = 0;

        return static function () use ($phase, $total, &$done, $onProgress): void {
            $onProgress($phase, ++$done, $total);
        };
    }

    /**
     * @throws InvalidStrategyException
     */
    public function strategyFor(string $algorithm): AbstractStrategy
    {
        return match ($algorithm) {
            'rabin-karp' => new DefaultStrategy($this->config),
            'tokenbag'   => new TokenBagStrategy($this->config),
            'unified'    => new UnifiedStrategy($this->config),
            default      => throw new InvalidStrategyException('Unsupported algorithm: ' . $algorithm),
        };
    }
}
