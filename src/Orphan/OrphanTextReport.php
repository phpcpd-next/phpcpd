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

namespace LucianoPereira\PhpcpdNext\Orphan;

use function count;
use function printf;
use function ucfirst;

use const PHP_EOL;

/**
 * Human-readable console output for an orphan scan — the orphan counterpart to
 * {@see \LucianoPereira\PhpcpdNext\Log\Text}.
 *
 * Two entry points for the two run modes:
 *   - printResult()   full report, both tiers — used by `--orphans` (gating).
 *   - printAdvisory() definite orphans plus a one-line possible count — used in
 *                     the default combined run, where orphans inform but do not
 *                     fail the build.
 */
final class OrphanTextReport
{
    public function printResult(OrphanResult $result): void
    {
        if ($result->isEmpty()) {
            printf(
                'No orphaned symbols found (%d symbols in %d files).' . PHP_EOL . PHP_EOL,
                $result->symbolsScanned,
                $result->filesScanned,
            );

            return;
        }

        $definite = $result->definite();
        $possible = $result->possible();

        if ($definite !== []) {
            printf('Found %d orphaned symbol(s):' . PHP_EOL . PHP_EOL, count($definite));
            $this->printGroup($definite);
        }

        if ($possible !== []) {
            printf('Found %d possible orphan(s) — review before removing:' . PHP_EOL . PHP_EOL, count($possible));
            $this->printGroup($possible);
        }

        printf(
            '%d symbols scanned in %d files; %d orphaned, %d possible.' . PHP_EOL . PHP_EOL,
            $result->symbolsScanned,
            $result->filesScanned,
            count($definite),
            count($possible),
        );
    }

    /**
     * Compact advisory used when orphan detection rides along with a clone scan:
     * only the safe-to-delete findings, and a pointer to `--orphans` for the
     * rest. Does not affect the process exit code.
     */
    public function printAdvisory(OrphanResult $result): void
    {
        $definite = $result->definite();
        $possible = $result->possible();

        if ($definite === [] && $possible === []) {
            return;
        }

        if ($definite !== []) {
            printf(
                'Orphaned symbols (advisory — does not affect exit code): %d' . PHP_EOL . PHP_EOL,
                count($definite),
            );
            $this->printGroup($definite);
        }

        if ($possible !== []) {
            printf(
                '%d possible orphan(s) not shown — run with --orphans to review them.' . PHP_EOL . PHP_EOL,
                count($possible),
            );
        }
    }

    /** @param list<Orphan> $orphans */
    private function printGroup(array $orphans): void
    {
        foreach ($orphans as $orphan) {
            $symbol = $orphan->symbol;

            printf(
                '  - %s %s' . PHP_EOL . '    %s:%d' . PHP_EOL . '    → %s' . PHP_EOL,
                ucfirst($symbol->kind),
                $symbol->fqn,
                $symbol->file,
                $symbol->line,
                $orphan->reason,
            );

            if ($orphan->entireFileOrphaned) {
                printf('    ⤷ whole file is unwired — no symbol declared here is referenced' . PHP_EOL);
            }

            if ($orphan->duplicateOf !== null) {
                printf('    ⤷ looks like a superseded copy of %s' . PHP_EOL, $orphan->duplicateOf);
            }

            print PHP_EOL;
        }
    }
}
