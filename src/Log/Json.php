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

namespace LucianoPereira\PhpcpdNext\Log;

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Version;

/**
 * JSON report. A modern, script-friendly projection of the same CodeCloneMap that
 * PMD/SARIF read — for custom dashboards and pipelines that don't speak PMD XML.
 */
final class Json extends AbstractJsonLogger
{
    #[\Override]
    public function process(CodeCloneMap $clones): void
    {
        $cloneList = [];

        foreach ($clones as $clone) {
            $cloneList[] = $clone->toArray();
        }

        $report = [
            'tool'    => 'phpcpd-next',
            'version' => Version::NUMBER,
            'summary' => [
                'clones'             => $clones->count(),
                'inconsistentClones' => $clones->numberOfGappedClones(),
                'duplicatedLines'    => $clones->numberOfDuplicatedLines(),
                'filesWithClones'    => $clones->numberOfFilesWithClones(),
                'percentage'         => $clones->percentage(),
            ],
            'clones'  => $cloneList,
        ];

        $this->write($report);
    }
}
