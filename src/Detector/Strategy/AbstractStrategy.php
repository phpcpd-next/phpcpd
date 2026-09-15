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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy;


use function str_ends_with;
use function substr_count;

use LucianoPereira\PhpcpdNext\CodeCloneMap;
use LucianoPereira\PhpcpdNext\Util\CodeLines;

/**
 * What every clone strategy is handed and what every one must answer.
 *
 * A strategy is fed files one at a time and adds what it finds to a shared map;
 * whatever it can only decide once it has seen them all, it decides in
 * `postProcess()`. The token bag needs that phase, since document frequency is
 * a fact about the corpus rather than about a file, and the contiguous matchers
 * do not, which is why it defaults to doing nothing.
 */
abstract class AbstractStrategy
{
    /**
     * Tokens that are not code.
     *
     * Comments, whitespace and the tags around a block carry no program at all;
     * `use` statements and namespace separators carry one that says where names
     * come from rather than what the file does, and two files that differ only
     * in their imports are the same code. Dropping them before matching is what
     * makes a clone a claim about behaviour rather than about layout.
     *
     * Defined once, in {@see CodeLines::IGNORED_TOKENS}, because two questions
     * now ask it — what to match on, and which lines to count as duplicated —
     * and a second copy could drift into a tool that matches one thing and
     * reports another. The provenance of the nine is recorded there.
     *
     * @var array<int, true>
     */
    protected const array IGNORED_TOKENS = CodeLines::IGNORED_TOKENS;

    /**
     * The token types PHP 8 bundles a whole name into.
     *
     * `Foo`, `\`, `Bar` used to arrive as three tokens and now arrive as one,
     * which is why dropping T_NS_SEPARATOR stopped doing anything: there is no
     * separator left to drop. Named here so the encoder can fold them back to
     * the name they end in.
     *
     * @var array<int, true>
     */
    protected const array QUALIFIED_NAMES = [
        T_NAME_QUALIFIED       => true,
        T_NAME_FULLY_QUALIFIED => true,
        T_NAME_RELATIVE        => true,
    ];

    protected readonly TokenNormalizer $normalizer;

    public function __construct(protected readonly StrategyConfiguration $config)
    {
        $this->normalizer = new TokenNormalizer($config->normalization->anchorsTypes());
    }

    /**
     * How many lines a source buffer holds.
     *
     * Counting newlines is one short whenever the last line has none — the file
     * that ends `return $x;` with no final break has all of its lines, and the
     * scan credited it with one fewer. That number is the denominator of the
     * duplicated-line percentage, so the error runs the wrong way: the total
     * shrinks and the share of it that is duplicated grows.
     *
     * Both strategies counted, and both counted the same way; it is written
     * once here so they cannot drift into disagreeing about how big a file is.
     */
    protected static function countLines(string $source): int
    {
        if ($source === '') {
            return 0;
        }

        return substr_count($source, "\n") + (str_ends_with($source, "\n") ? 0 : 1);
    }

    abstract public function processFile(string $file, CodeCloneMap $result): void;

    /** Whatever can only be decided once every file has been seen. */
    public function postProcess(): void {}
}
