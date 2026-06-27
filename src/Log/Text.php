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

namespace LucianoPereira\PhpcpdNext\Log;

use function count;
use function printf;
use function sprintf;
use function str_contains;

use const PHP_EOL;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneMap;

final class Text
{
    public function printResult(CodeCloneMap $clones, bool $verbose): void
    {
        if (count($clones) > 0) {
            $gapped           = $clones->numberOfGappedClones();
            $inconsistentNote = $gapped > 0 ? sprintf(' (%d inconsistent)', $gapped) : '';

            printf(
                'Found %d code clones%s with %d duplicated lines in %d files:' . PHP_EOL . PHP_EOL,
                count($clones),
                $inconsistentNote,
                $clones->numberOfDuplicatedLines(),
                $clones->numberOfFilesWithClones(),
            );
        }

        foreach ($clones as $clone) {
            $firstOccurrence = true;

            foreach ($clone->files() as $file) {
                $suffix = '';

                if ($firstOccurrence) {
                    $suffix = ' (' . $clone->numberOfLines() . ' lines)';

                    if ($clone->isGapped()) {
                        $suffix .= ' [inconsistent]';
                    }
                }

                printf(
                    '  %s%s:%d-%d%s' . PHP_EOL,
                    $firstOccurrence ? '- ' : '  ',
                    $file->name(),
                    $file->startLine(),
                    $file->startLine() + $clone->numberOfLines(),
                    $suffix,
                );

                $firstOccurrence = false;
            }

            printf('    → %s' . PHP_EOL, $this->suggestion($clone));

            if ($verbose) {
                print PHP_EOL . $clone->lines('    ');
            }

            print PHP_EOL;
        }

        if ($clones->isEmpty()) {
            print 'No code clones found.' . PHP_EOL . PHP_EOL;

            return;
        }

        printf(
            '%s duplicated lines out of %d total lines of code.' . PHP_EOL .
            'Average code clone size is %d lines, the largest code clone has %d lines' . PHP_EOL . PHP_EOL,
            $clones->percentage(),
            $clones->numberOfLines(),
            $clones->averageSize(),
            $clones->largestSize(),
        );
    }

    private function suggestion(CodeClone $clone): string
    {
        if ($clone->isGapped()) {
            return 'Near-miss clone — consider parameterizing the diverging part or aligning both copies.';
        }

        $inTests = false;

        foreach ($clone->files() as $file) {
            if (str_contains($file->name(), 'test') || str_contains($file->name(), 'Test')) {
                $inTests = true;
                break;
            }
        }

        if ($inTests) {
            return 'Duplicate test scaffold — consider a shared base class or a @dataProvider.';
        }

        if ($clone->numberOfLines() >= 50) {
            return 'Large block — extract into a method, class, or trait.';
        }

        return 'Consider extracting the shared lines into a reusable method or constant.';
    }
}
