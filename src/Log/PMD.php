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

use LucianoPereira\PhpcpdNext\CodeCloneMap;

final class PMD extends AbstractXmlLogger
{
    #[\Override]
    public function process(CodeCloneMap $clones): void
    {
        $cpd = $this->document->createElement('pmd-cpd');
        $this->document->appendChild($cpd);

        foreach ($clones as $clone) {
            $duplication = $cpd->appendChild(
                $this->document->createElement('duplication'),
            );

            $duplication->setAttribute('lines', (string) $clone->numberOfLines());
            $duplication->setAttribute('tokens', (string) $clone->numberOfTokens());

            foreach ($clone->files() as $codeCloneFile) {
                $file = $duplication->appendChild(
                    $this->document->createElement('file'),
                );

                $file->setAttribute('path', $codeCloneFile->name());
                $file->setAttribute('line', (string) $codeCloneFile->startLine());
            }

            $codefragment = $duplication->appendChild(
                $this->document->createElement('codefragment'),
            );

            // createTextNode escapes once, natively — no manual htmlspecialchars needed.
            $codefragment->appendChild(
                $this->document->createTextNode($this->sanitizeForXml($clone->lines())),
            );
        }

        $this->flush();
    }
}
