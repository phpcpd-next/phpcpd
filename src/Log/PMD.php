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

use function sprintf;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\Presentation\Finding;
use LucianoPereira\PhpcpdNext\Presentation\Findings;

/**
 * The PMD-CPD report.
 *
 * One `<duplication>` per finding, holding a `<file>` for each place the
 * duplication occurs and one `<codefragment>` of the source. Element and
 * attribute names are the format's, not this tool's: `lines`, `tokens`, `path`,
 * `line`, `endline` are what a PMD-CPD reader looks for, and a report that
 * spelled them differently would parse as nothing.
 *
 * Four attributes are additions, and they are additions rather than changes
 * because the format tolerates them: `stratum` and `demotedBy` say whether this
 * run demoted the finding and what did it, `confidence` is the rank the console
 * prints, and `acknowledged` marks a finding a ledger entry covers. A reader
 * that ignores all four sees exactly the document it saw before.
 */
final class PMD extends AbstractXmlLogger
{
    #[\Override]
    public function process(Findings $findings): void
    {
        $root = $this->document->appendChild(
            $this->document->createElement('pmd-cpd'),
        );

        foreach ($findings->visible() as $finding) {
            $root->appendChild($this->duplication($finding));
        }

        $this->flush();
    }

    /** One finding: what was duplicated, where, and what this run made of it. */
    private function duplication(Finding $finding): \DOMElement
    {
        $clone       = $finding->clone;
        $duplication = $this->document->createElement('duplication');

        $duplication->setAttribute('lines', (string) $clone->numberOfLines());
        $duplication->setAttribute('tokens', (string) $clone->numberOfTokens());

        // Asked once. Two attributes describing one decision have to be gated
        // on that one decision, or they contradict each other — which they did,
        // for a finding the ledger demoted and no stratum did.
        $demoted = $finding->demoted();

        $duplication->setAttribute('stratum', $demoted ? 'demoted' : 'asserted');

        if ($demoted) {
            $duplication->setAttribute('demotedBy', $finding->tag());
        }

        $duplication->setAttribute('confidence', sprintf('%+.4f', $finding->confidence));

        if ($finding->acknowledged) {
            $duplication->setAttribute('acknowledged', 'true');
        }

        foreach ($clone->files() as $occurrence) {
            $file = $duplication->appendChild($this->document->createElement('file'));

            [$from, $to] = $finding->span($occurrence);

            $file->setAttribute('path', $this->path->of($occurrence->name));
            $file->setAttribute('line', (string) $from);

            // Each occurrence's own end, not the class's. Copies need not span
            // equally many source lines, and without this every location is a
            // point — a reader that draws the duplication draws one line of it.
            $file->setAttribute('endline', (string) $to);
        }

        $duplication->appendChild($this->fragment($clone));

        return $duplication;
    }

    /**
     * The duplicated source itself.
     *
     * A text node rather than an escaped string: the document escapes `&` and
     * `<` on its way out, so escaping here as well would double it. What the
     * document cannot do is carry a code point XML forbids outright, which is
     * why the bytes are sanitized first — see {@see AbstractXmlLogger::sanitizeForXml()}.
     */
    private function fragment(CodeClone $clone): \DOMElement
    {
        $fragment = $this->document->createElement('codefragment');

        $fragment->appendChild(
            $this->document->createTextNode($this->sanitizeForXml($clone->lines())),
        );

        return $fragment;
    }
}
