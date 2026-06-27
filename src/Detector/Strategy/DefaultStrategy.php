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

use function array_values;
use function chr;
use function count;
use function crc32;
use function file_get_contents;
use function is_array;
use function md5;
use function pack;
use function substr;
use function substr_count;
use function token_get_all;

use LucianoPereira\PhpcpdNext\CodeClone;
use LucianoPereira\PhpcpdNext\CodeCloneFile;
use LucianoPereira\PhpcpdNext\CodeCloneMap;

final class DefaultStrategy extends AbstractStrategy
{
    /** @var array<string, array{0: string, 1: int}> */
    private array $hashes = [];

    #[\Override]
    public function processFile(string $file, CodeCloneMap $result): void
    {
        $buffer = file_get_contents($file);

        if ($buffer === false) {
            return;
        }

        $this->scan($file, $this->tokenize($buffer), $result);
    }

    /**
     * Tokenize a source buffer into the form the scanner consumes — token_get_all
     * plus signature building, the expensive step the incremental index caches.
     *
     * Pure: depends only on the buffer and the configuration (fuzzy/type-anchored
     * normalization, ignore list), never on cross-file state.
     */
    public function tokenize(string $buffer): FileTokens
    {
        $currentTokenPositions     = [];
        $currentTokenRealPositions = [];
        $currentSignature          = '';
        $tokens                    = token_get_all($buffer);
        $tokenNr                   = 0;
        $lastTokenLine             = 0;
        $numberOfLines             = substr_count($buffer, "\n");

        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (!isset($this->tokensIgnoreList[$token[0]])) {
                    if ($tokenNr === 0) {
                        $currentTokenPositions[$tokenNr] = $token[2] - $lastTokenLine;
                    } else {
                        $currentTokenPositions[$tokenNr] = $currentTokenPositions[$tokenNr - 1] +
                                                           $token[2] - $lastTokenLine;
                    }

                    $currentTokenRealPositions[$tokenNr++] = $token[2];

                    if ($this->config->fuzzy() || $this->config->typeAnchored()) {
                        $token[1] = $this->normalizer->normalize($token[0], $token[1]);
                    }

                    $currentSignature .= chr($token[0] & 255) .
                                         pack('N*', crc32($token[1]));
                }

                $lastTokenLine = $token[2];
            }
        }

        return new FileTokens(
            $numberOfLines,
            $currentSignature,
            array_values($currentTokenPositions),
            array_values($currentTokenRealPositions),
        );
    }

    /**
     * Slide the min-tokens window over an already-tokenized file and record clones
     * against the running hash table. Stateful across files: the first occurrence
     * of each window hash is remembered so later matches resolve back to it.
     *
     * Driving this from cached FileTokens (instead of re-tokenizing) is what lets
     * the per-file incremental index re-scan only the files that changed while
     * producing exactly the same result as a full pass.
     */
    public function scan(string $file, FileTokens $tokens, CodeCloneMap $result): void
    {
        $result->addToNumberOfLines($tokens->numberOfLines);

        $currentTokenPositions     = $tokens->tokenLines;
        $currentTokenRealPositions = $tokens->tokenRealLines;
        $currentSignature          = $tokens->signature;

        $count         = count($currentTokenPositions);
        $firstLine     = 0;
        $firstRealLine = 0;
        $firstHash     = '';
        $firstToken    = 0;
        $found         = false;
        $tokenNr       = 0;

        while ($tokenNr <= $count - $this->config->minTokens()) {
            $line     = $currentTokenPositions[$tokenNr];
            $realLine = $currentTokenRealPositions[$tokenNr];

            $hash = substr(
                md5(
                    substr(
                        $currentSignature,
                        $tokenNr * 5,
                        $this->config->minTokens() * 5,
                    ),
                    true,
                ),
                0,
                8,
            );

            if (isset($this->hashes[$hash])) {
                $found = true;

                if ($firstLine === 0) {
                    $firstLine     = $line;
                    $firstRealLine = $realLine;
                    $firstHash     = $hash;
                    $firstToken    = $tokenNr;
                }
            } else {
                if ($found) {
                    $this->recordCloneIfValid(
                        $result,
                        $file,
                        $firstHash,
                        $tokenNr,
                        $firstLine,
                        $firstRealLine,
                        $firstToken,
                        $currentTokenPositions,
                        $currentTokenRealPositions,
                    );

                    $found     = false;
                    $firstLine = 0;
                }

                $this->hashes[$hash] = [$file, $realLine];
            }

            $tokenNr++;
        }

        if ($found) {
            $this->recordCloneIfValid(
                $result,
                $file,
                $firstHash,
                $tokenNr,
                $firstLine,
                $firstRealLine,
                $firstToken,
                $currentTokenPositions,
                $currentTokenRealPositions,
            );
        }
    }

    /**
     * @param array<int, int> $currentTokenPositions
     * @param array<int, int> $currentTokenRealPositions
     */
    private function recordCloneIfValid(
        CodeCloneMap $result,
        string $file,
        string $firstHash,
        int $tokenNr,
        int $firstLine,
        int $firstRealLine,
        int $firstToken,
        array $currentTokenPositions,
        array $currentTokenRealPositions,
    ): void {
        $fileA        = $this->hashes[$firstHash][0];
        $firstLineA   = $this->hashes[$firstHash][1];
        $lastToken    = ($tokenNr - 1) + $this->config->minTokens() - 1;
        $lastLine     = $currentTokenPositions[$lastToken];
        $lastRealLine = $currentTokenRealPositions[$lastToken];
        $numLines     = $lastLine + 1 - $firstLine;
        $realNumLines = $lastRealLine + 1 - $firstRealLine;

        if ($numLines >= $this->config->minLines() &&
            ($fileA !== $file || $firstLineA !== $firstRealLine)) {
            $result->add(
                new CodeClone(
                    new CodeCloneFile($fileA, $firstLineA),
                    new CodeCloneFile($file, $firstRealLine),
                    $realNumLines,
                    $lastToken + 1 - $firstToken,
                ),
            );
        }
    }
}
