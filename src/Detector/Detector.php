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

namespace LucianoPereira\PhpcpdNext\Detector;

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;

/**
 * Runs one strategy over one set of files.
 *
 * The whole of it: hand the strategy each file in turn, let it finish once it
 * has seen them all, and give back what it recorded. Everything a strategy
 * cannot decide file by file — the token bag's document frequencies, the
 * unified engine's cross-file chaining — happens in that closing call, which is
 * why the loop cannot simply be inlined at the call site.
 *
 * It used to also apply the suppression markers, which read like a small
 * courtesy and was not one. Suppression is a decision about what to *report*,
 * and the default pipeline runs two of these over the same files: every file
 * carrying a clone was opened and scanned for markers twice, and the policy
 * applied twice, to reach an answer the first pass already had. It belongs to
 * whoever assembles the finished map, which is {@see \LucianoPereira\PhpcpdNext\Engine}.
 */
final readonly class Detector
{
    public function __construct(private AbstractStrategy $strategy) {}

    /**
     * `$onFile` is called once per file processed. It is how a caller that has
     * a console — only the CLI does — can show progress without this class or
     * any strategy knowing what a console is.
     *
     * @param iterable<string> $files
     * @param ?callable(): void $onFile
     */
    public function copyPasteDetection(iterable $files, ?callable $onFile = null): CodeCloneMap
    {
        $clones = new CodeCloneMap();

        foreach ($files as $file) {
            $this->strategy->processFile($file, $clones);

            if ($onFile !== null) {
                $onFile();
            }
        }

        $this->strategy->postProcess();

        return $clones;
    }
}
