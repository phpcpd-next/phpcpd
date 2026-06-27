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

        return $result;
    }
}
