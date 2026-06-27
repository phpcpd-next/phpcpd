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

namespace LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree;

use function array_fill_keys;

use const T_BREAK;
use const T_CASE;
use const T_CATCH;
use const T_CLASS;
use const T_CONTINUE;
use const T_DEFAULT;
use const T_DO;
use const T_ELSE;
use const T_ELSEIF;
use const T_ENUM;
use const T_FINALLY;
use const T_FOR;
use const T_FOREACH;
use const T_FUNCTION;
use const T_GOTO;
use const T_IF;
use const T_INTERFACE;
use const T_MATCH;
use const T_RETURN;
use const T_SWITCH;
use const T_THROW;
use const T_TRAIT;
use const T_TRY;
use const T_WHILE;

/**
 * Type-aware substitution cost for the approximate (edit-distance) matcher.
 *
 * The uniform-cost Levenshtein treats every differing token as cost 1. That spends
 * the --edit-distance budget on cosmetic changes (a renamed identifier, a different
 * literal) at the same rate as structural ones (an `if` turned into a `while`). This
 * weights a control-flow keyword change higher, so the budget reflects how much the
 * *structure* diverged — letting users raise --edit-distance to tolerate cosmetic
 * drift without also accepting structurally different code as a clone.
 *
 * Only keyword/identifier/literal tokens reach here: the suffix tree drops single-char
 * operator/punctuation tokens, so control-flow keywords are the structural signal.
 */
final class SubstitutionCost
{
    private const int COSMETIC   = 1;
    private const int STRUCTURAL = 2;

    /** @var array<int, true> */
    private readonly array $structural;

    public function __construct()
    {
        $this->structural = array_fill_keys(
            [
                T_IF, T_ELSE, T_ELSEIF, T_WHILE, T_DO, T_FOR, T_FOREACH,
                T_SWITCH, T_CASE, T_DEFAULT, T_BREAK, T_CONTINUE, T_RETURN,
                T_THROW, T_TRY, T_CATCH, T_FINALLY, T_MATCH, T_GOTO,
                T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM,
            ],
            true,
        );
    }

    public function between(AbstractToken $a, AbstractToken $b): int
    {
        if ($a->equals($b)) {
            return 0;
        }

        return $this->isStructural($a) || $this->isStructural($b)
            ? self::STRUCTURAL
            : self::COSMETIC;
    }

    private function isStructural(AbstractToken $token): bool
    {
        return isset($this->structural[$token->tokenCode]);
    }
}
