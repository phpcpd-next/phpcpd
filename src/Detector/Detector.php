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

namespace LucianoPereira\PhpcpdNext\Detector;

use function array_keys;

use LucianoPereira\PhpcpdNext\CodeCloneMap;

use LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy;

final class Detector
{
    public function __construct(private readonly AbstractStrategy $strategy) {}

    /** @param iterable<string> $files */
    public function copyPasteDetection(iterable $files): CodeCloneMap
    {
        $result = new CodeCloneMap();

        foreach ($files as $file) {
            if ($file === '') {
                continue;
            }

            $this->strategy->processFile($file, $result);
        }

        $this->strategy->postProcess();

        return CloneSuppressions::forFiles($this->filesWithClones($result))->filter($result);
    }

    /**
     * Only files that took part in a clone can carry a marker that matters, and
     * that is normally a small fraction of the scan — so suppression costs one
     * extra read per reported file, not per scanned file.
     *
     * @return list<string>
     */
    private function filesWithClones(CodeCloneMap $result): array
    {
        $files = [];

        foreach ($result->clones() as $clone) {
            foreach ($clone->files() as $file) {
                $files[$file->name()] = true;
            }
        }

        return array_keys($files);
    }
}
