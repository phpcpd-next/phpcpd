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

use function array_values;

use LucianoPereira\PhpcpdNext\Presentation\Findings;
use LucianoPereira\PhpcpdNext\Version;

/**
 * JSON report. A modern, script-friendly projection of the same CodeCloneMap that
 * PMD/SARIF read — for custom dashboards and pipelines that don't speak PMD XML.
 */
final class Json extends AbstractJsonLogger
{
    #[\Override]
    public function process(Findings $findings): void
    {
        $clones    = $findings->clones;
        $cloneList = [];

        foreach ($findings->visible() as $finding) {
            $clone = $finding->clone->toArray();
            $sites = array_values($finding->clone->files());

            // `toArray()` is the canonical shape, shared with the clone cache,
            // and names files as the scan found them. A report names them as a
            // reader will look for them.
            //
            // The extent is restated for the same reason: `toArray()` is what
            // the *cache* stores, so it carries the engine's token-run
            // boundaries, and a report states them in whole lines. `line` and
            // `lines` move together or a consumer adding them lands past the
            // end. `tokens` is left alone — it counts what matched, which the
            // snap does not change.
            foreach ($clone['files'] as $at => $file) {
                $clone['files'][$at]['path'] = $this->path->of($file['path']);

                if (!isset($sites[$at])) {
                    continue;
                }

                [$from, $to] = $finding->span($sites[$at]);

                $clone['files'][$at]['line'] = $from;

                if (isset($clone['files'][$at]['lines'])) {
                    $clone['files'][$at]['lines'] = $to + 1 - $from;
                }
            }

            // Always present, and always both halves: a consumer that wants only
            // what the tool asserts can filter on one key, and a consumer that
            // wants everything gets the tag rather than a silently shorter list.
            $clone['stratum'] = $finding->demoted() ? 'demoted' : 'asserted';

            if ($finding->strata !== []) {
                $clone['demotedBy'] = $finding->strata;
            }

            if ($finding->acknowledged) {
                $clone['acknowledged'] = true;
            }

            // Only when there are any, so the shape does not grow a zero on
            // every clone: a consumer reading this key is being told the copies
            // carry different constants, which `gapped` does not say because a
            // folded literal leaves no gap behind.
            if ($finding->literalDivergences > 0) {
                $clone['literalDivergences'] = $finding->literalDivergences;
            }

            // Where the lead site's range lands. A consumer reading a token
            // run's line numbers cannot tell what it is looking at; these are
            // the names that say so, and they are absent rather than empty when
            // the range touches no named function.
            if ($finding->functions !== []) {
                $clone['functions'] = $finding->functions;
            }

            // The rank travels with the finding, terms included: a consumer that
            // sorts by it can also say why it sorted that way.
            $clone['confidence'] = ['logOdds' => $finding->confidence, 'terms' => $finding->terms];

            $cloneList[] = $clone;
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
                'settled'            => $clones->numberOfSettledClones(),
                'unfounded'          => $clones->numberOfUnfoundedClones(),
                'asserted'           => $findings->asserted(),
                'demoted'            => $findings->demoted(),
                'demotedBy'          => $findings->perStratum(),
                'acknowledged'       => $findings->acknowledged(),
                'staleAcknowledgments' => $findings->stale,
            ],
            'clones'  => $cloneList,
        ];

        $this->write($report);
    }
}
