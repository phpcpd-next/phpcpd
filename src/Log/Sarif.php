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

use LucianoPereira\PhpcpdNext\Presentation\Findings;
use LucianoPereira\PhpcpdNext\Version;

/**
 * SARIF 2.1.0 report — the OASIS standard for static-analysis results, ingested
 * natively by GitHub Code Scanning (PR annotations, Security tab). Gapped (Type-3)
 * clones map to "warning", exact clones to "note", so the inconsistent clones that
 * carry bug risk surface at a higher severity.
 *
 * A **demoted** finding is emitted at `note` whatever its type, and names the
 * strata that demoted it in its properties bag. SARIF's own level is the natural
 * home for "the tool found this and is not asserting it": the result is still
 * there, still navigable, still counted, and a reviewer's attention goes where
 * the tool is confident. Nothing is dropped — a level is not a filter.
 */
final class Sarif extends AbstractJsonLogger
{
    #[\Override]
    public function process(Findings $findings): void
    {
        $results = [];

        foreach ($findings->visible() as $finding) {
            $clone     = $finding->clone;
            $locations = [];

            foreach ($clone->files() as $file) {
                [$from, $to] = $finding->span($file);

                $locations[] = [
                    'physicalLocation' => [
                        'artifactLocation' => ['uri' => $this->path->of($file->name)],
                        'region'           => [
                            'startLine' => $from,
                            'endLine'   => $to,
                        ],
                    ],
                ];
            }

            $result = [
                'ruleId'  => match (true) {
                    $clone->isReordered() => 'reordered-clone',
                    $clone->isGapped()    => 'inconsistent-clone',
                    default               => 'duplicate-code',
                },
                'level'   => $finding->demoted() ? 'note' : ($clone->isGapped() ? 'warning' : 'note'),
                'message' => [
                    'text' => sprintf(
                        '%s clone: %d lines, %d tokens duplicated across %d locations.%s',
                        match (true) {
                            $clone->isReordered() => 'Reordered',
                            $clone->isGapped()    => 'Inconsistent (gapped)',
                            default               => 'Exact',
                        },
                        $clone->numberOfLines(),
                        $clone->numberOfTokens(),
                        count($clone->files()),
                        $finding->demoted() ? ' Demoted: ' . $finding->tag() . '.' : '',
                    ),
                ],
                'locations'  => $locations,
                'properties' => [
                    'stratum'    => $finding->demoted() ? 'demoted' : 'asserted',
                    'confidence' => $finding->confidence,
                ],
            ];

            if ($finding->strata !== []) {
                $result['properties']['demotedBy'] = $finding->strata;
            }

            if ($finding->acknowledged) {
                $result['properties']['acknowledged'] = true;
            }

            $results[] = $result;
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
                                // Declared, because a result may not name a rule
                                // the driver does not carry. Separate from
                                // `inconsistent-clone` because it is a different
                                // claim: the material is all present in another
                                // order, rather than one copy having diverged
                                // from its sibling.
                                [
                                    'id'               => 'reordered-clone',
                                    'name'             => 'ReorderedClone',
                                    'shortDescription' => ['text' => 'Reordered (Type-3) clone — same material, different sequence.'],
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
