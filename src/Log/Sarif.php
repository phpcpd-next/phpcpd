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

use function count;
use function sprintf;

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Version;

/**
 * SARIF 2.1.0 report — the OASIS standard for static-analysis results, ingested
 * natively by GitHub Code Scanning (PR annotations, Security tab). Gapped (Type-3)
 * clones map to "warning", exact clones to "note", so the inconsistent clones that
 * carry bug risk surface at a higher severity.
 */
final class Sarif extends AbstractJsonLogger
{
    #[\Override]
    public function process(CodeCloneMap $clones): void
    {
        $results = [];

        foreach ($clones as $clone) {
            $locations = [];

            foreach ($clone->files() as $file) {
                $locations[] = [
                    'physicalLocation' => [
                        'artifactLocation' => ['uri' => $file->name()],
                        'region'           => ['startLine' => $file->startLine()],
                    ],
                ];
            }

            $results[] = [
                'ruleId'  => $clone->isGapped() ? 'inconsistent-clone' : 'duplicate-code',
                'level'   => $clone->isGapped() ? 'warning' : 'note',
                'message' => [
                    'text' => sprintf(
                        '%s clone: %d lines, %d tokens duplicated across %d locations.',
                        $clone->isGapped() ? 'Inconsistent (gapped)' : 'Exact',
                        $clone->numberOfLines(),
                        $clone->numberOfTokens(),
                        count($clone->files()),
                    ),
                ],
                'locations' => $locations,
            ];
        }

        $sarif = [
            '$schema' => 'https://json.schemastore.org/sarif-2.1.0.json',
            'version' => '2.1.0',
            'runs'    => [
                [
                    'tool' => [
                        'driver' => [
                            'name'           => 'phpcpd-next',
                            'informationUri' => 'https://github.com/phpcpd-next/phpcpd',
                            'version'        => Version::NUMBER,
                            'rules'          => [
                                [
                                    'id'               => 'duplicate-code',
                                    'name'             => 'DuplicateCode',
                                    'shortDescription' => ['text' => 'Exact duplicated code (Type-1/2).'],
                                ],
                                [
                                    'id'               => 'inconsistent-clone',
                                    'name'             => 'InconsistentClone',
                                    'shortDescription' => ['text' => 'Gapped (Type-3) clone — copies diverge; bug risk.'],
                                ],
                            ],
                        ],
                    ],
                    'results' => $results,
                ],
            ],
        ];

        $this->write($sarif);
    }
}
