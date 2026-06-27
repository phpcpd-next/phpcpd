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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy;

use function file_get_contents;
use function is_array;
use function substr_count;
use function token_get_all;
use function token_name;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree\AbstractToken;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree\ApproximateCloneDetectingSuffixTree;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree\Sentinel;
use LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree\Token;

final class SuffixTreeStrategy extends AbstractStrategy
{
    /** @var list<AbstractToken> */
    private array $word = [];
    private ?CodeCloneMap $result = null;

    #[\Override]
    public function processFile(string $file, CodeCloneMap $result): void
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return;
        }

        $result->addToNumberOfLines(substr_count($content, "\n"));

        $tokens = token_get_all($content);

        foreach ($tokens as $token) {
            if (is_array($token) && !isset($this->tokensIgnoreList[$token[0]])) {
                $content = ($this->config->fuzzy() || $this->config->typeAnchored())
                    ? $this->normalizer->normalize($token[0], $token[1])
                    : $token[1];

                $this->word[] = new Token(
                    $token[0],
                    token_name($token[0]),
                    $token[2],
                    $file,
                    $content,
                );
            }
        }

        $this->result = $result;
    }

    #[\Override]
    public function postProcess(): void
    {
        if ($this->result === null) {
            return;
        }

        $this->word[] = new Sentinel();

        $cloneInfos = (new ApproximateCloneDetectingSuffixTree($this->word))->findClones(
            $this->config->minTokens(),
            $this->config->editDistance(),
            $this->config->headEquality(),
        );

        foreach ($cloneInfos as $cloneInfo) {
            foreach ($cloneInfo->otherClones->extractFirstList() as $otherStart) {
                $t = $this->word[$otherStart];

                // Last token of the FIRST occurrence. position + length can overshoot the
                // occurrence and cross a Sentinel into the NEXT file, whose line numbers
                // restart — producing a zero or even negative span. Walk back until the
                // last index sits on a real token in the SAME file as the first token, so
                // the line span is always within one file (and never negative).
                $start     = $cloneInfo->token;
                $lastIndex = $cloneInfo->position + $cloneInfo->length - 1;

                while ($lastIndex > $cloneInfo->position && (
                    $this->word[$lastIndex] instanceof Sentinel ||
                    $this->word[$lastIndex]->file !== $start->file
                )) {
                    $lastIndex--;
                }

                $lines = $this->word[$lastIndex]->line - $start->line;

                // A clone whose in-file span collapses to zero lines — its matched
                // token run lay almost entirely beyond a file boundary — is degenerate
                // noise rather than a usable finding, so skip it.
                if ($lines < 1) {
                    continue;
                }

                $this->result->add(
                    new CodeClone(
                        new CodeCloneFile($cloneInfo->token->file, $cloneInfo->token->line),
                        new CodeCloneFile($t->file, $t->line),
                        $lines,
                        $otherStart + 1 - $cloneInfo->position,
                        $this->occurrencesDiffer($cloneInfo->position, $otherStart, $cloneInfo->length),
                    ),
                );
            }
        }
    }

    /**
     * Classifies a clone as gapped (Type-3 / inconsistent) vs exact (Type-1).
     *
     * Engine B matches on raw token content, so the two occurrences are identical
     * for an exact clone and differ only where the edit-distance budget bridged a
     * gap. Any difference in the aligned token windows — or one side ending early —
     * means the copies are not identical: a gapped, inconsistent clone.
     */
    private function occurrencesDiffer(int $startA, int $startB, int $length): bool
    {
        for ($i = 0; $i < $length; $i++) {
            $a = $this->word[$startA + $i] ?? null;
            $b = $this->word[$startB + $i] ?? null;

            if ($a === null || $b === null || $a instanceof Sentinel || $b instanceof Sentinel) {
                return $a !== $b;
            }

            if ($a->content !== $b->content) {
                return true;
            }
        }

        return false;
    }
}
