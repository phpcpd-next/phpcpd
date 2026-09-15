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

namespace LucianoPereira\PhpcpdNext\Facts;

use function array_pop;
use function count;
use function is_array;
use function token_get_all;

use const T_ARRAY;
use const T_ATTRIBUTE;
use const T_CLOSE_TAG;
use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_CURLY_OPEN;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_DOC_COMMENT;
use const T_FN;
use const T_FUNCTION;
use const T_INLINE_HTML;
use const T_NS_SEPARATOR;
use const T_OPEN_TAG;
use const T_OPEN_TAG_WITH_ECHO;
use const T_STRING;
use const T_USE;
use const T_VARIABLE;
use const T_WHITESPACE;

/**
 * What a region of a file *is*, structurally — the facts layer both precision
 * tiers read.
 *
 * Ruling H left exactly one avenue open for the data-table false positive, and
 * closed the other three by measurement: anchor multiplicity, tokens-per-line
 * sparseness and logic share are refuted and are not to be re-proposed. What
 * remains is **structural context** — what a span *is*, not any statistic of it.
 * This class computes that context once per file, so the span discriminator and
 * (from ruling R) the order-free verdict read one description rather than two.
 *
 * ## The one structure it describes: the literal array
 *
 * Frames are opened by `[`, `(`, `{` and closed by their partners, and a frame
 * is an **array-literal frame** when it was opened by `array(` or by a `[` in
 * value position. The `[` distinction is syntactic and total: a `[` directly
 * after a variable, a name, a string, `]`, `)` or `}` is a subscript, and a `[`
 * anywhere else opens an array.
 *
 * A frame is **statement-free** when no token inside it — at any depth — opens a
 * body: no `function`, no `fn`, and no `;`. Everything else an array element can
 * contain is an *expression*, and an array of expressions is a data table. A
 * frame that is not statement-free is **logic-bearing**, and that propagates to
 * every enclosing frame.
 *
 * ## Why *statement-free* and not *computation-free* (ruling 5, granted)
 *
 * This class first defined *literal* as "contains no computation token" — a
 * whitelist of number, string, bare name, `=>` and array punctuation, with a call
 * parenthesis disqualifying the frame. That definition is **struck**. It excluded
 * a whole family of files that every reader and both raters call data tables:
 * a Laravel-shaped `config/*.php` whose rows are `env('KEY', 'default')`, a
 * navigation table whose labels are `trans('…')` and whose targets are
 * `route('…')`. The rows are records built from expressions; the file computes
 * nothing.
 *
 * The granted rule draws the line where the language draws it. An array element
 * is an expression; the only way a *statement* gets inside an array literal is a
 * closure or arrow-function body. So:
 *
 * - `['IT', 39, env('X', 'y'), strtoupper('first')]` — a data table. Its
 *   elements are expressions, however they are spelled.
 * - `['handler' => function () { return $this->x(); }]` — logic-bearing, and
 *   never silenced. The body holds statements, and a second copy of it is a
 *   second copy of behaviour.
 *
 * ## Why this is still a membership test, and still carries no constant
 *
 * The boundary is the presence or absence of a token class — `T_FUNCTION`,
 * `T_FN`, `;` — exactly as it was before, with a different class named. There is
 * no value to derive and no curve to sit on. The refuted discriminators were all
 * ratios in need of a threshold; this is not one, which is the difference ruling
 * H drew and the reason ruling 5 could be granted as an *extension* of the rule
 * rather than a replacement of its kind.
 *
 * `match` is deliberately **not** in the class. The ruling names "a closure or
 * statement", a `match` is an expression, and extending past the ruling's words
 * to catch it would be the executor widening a granted rule on its own judgement.
 * Measured on the pool: it changes nothing either way.
 *
 * ## Alignment with the encoder
 *
 * Positions are **significant-token indices**: the same numbering
 * {@see \LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy::tokenize()}
 * produces, which skips whitespace, comments, tags, `use` and `\`, and drops
 * single-character tokens entirely. This class must skip exactly what the encoder
 * skips or every index it reports is wrong by an offset, so the ignore list below
 * is a deliberate duplicate and a test pins the two together.
 */
final class RegionStructure
{
    /**
     * What the encoder drops. Duplicated from
     * {@see \LucianoPereira\PhpcpdNext\Detector\Strategy\AbstractStrategy}
     * rather than shared, because this class must not depend on a strategy to
     * describe a file; `RegionStructureTest` asserts the two agree.
     *
     * @var array<int, true>
     */
    private const array IGNORED = [
        T_INLINE_HTML        => true,
        T_COMMENT            => true,
        T_DOC_COMMENT        => true,
        T_OPEN_TAG           => true,
        T_OPEN_TAG_WITH_ECHO => true,
        T_CLOSE_TAG          => true,
        T_WHITESPACE         => true,
        T_USE                => true,
        T_NS_SEPARATOR       => true,
    ];

    /**
     * What makes a frame logic-bearing. A blacklist of three, and the inverse of
     * the struck whitelist: an array element is an expression, so the only way a
     * statement reaches inside an array literal is through a closure body.
     *
     * `;` is here as a total guard rather than because a second mechanism is
     * expected — inside a balanced array frame it can only have come from a
     * function body — and it costs one comparison to be certain.
     *
     * @var array<int|string, true>
     */
    private const array LOGIC = [
        T_FUNCTION => true,
        T_FN       => true,
        ';'        => true,
    ];

    /**
     * A `[` directly after one of these is a subscript, not an array literal.
     *
     * @var array<int|string, true>
     */
    private const array SUBSCRIPTED = [
        T_STRING                   => true,
        T_VARIABLE                 => true,
        T_CONSTANT_ENCAPSED_STRING => true,
        ']'                        => true,
        ')'                        => true,
        '}'                        => true,
    ];

    /**
     * Everything that opens a bracket, including the two the tokenizer gives a
     * name to inside an interpolated string. Balance matters more than meaning
     * here: an unmatched push would misattribute every frame after it.
     *
     * @var array<int|string, true>
     */
    private const array OPENS = [
        '['                          => true,
        '('                          => true,
        '{'                          => true,
        T_ATTRIBUTE                  => true,
        T_CURLY_OPEN                 => true,
        T_DOLLAR_OPEN_CURLY_BRACES   => true,
    ];

    /**
     * Parallel arrays rather than an array of shapes: a frame is mutated as the
     * scan proceeds (a closure seen late makes it logic-bearing), and four flat
     * lists say that plainly.
     *
     * @param list<int>        $innermost significant index => innermost array-literal frame, or -1
     * @param array<int, int>  $parent    frame => enclosing array-literal frame, or -1
     * @param array<int, int>  $first     frame => first significant index inside it
     * @param array<int, int>  $last      frame => last significant index inside it
     * @param array<int, bool> $statementFree frame => statement-free at any depth (no closure, no `;`)
     * @param list<array{0: int, 1: int}> $functions function bodies, as significant-index ranges
     * @param list<?string> $functionNames parallel to $functions; null where the function has no name
     */
    private function __construct(
        private readonly array $innermost,
        private readonly array $parent,
        private readonly array $first,
        private readonly array $last,
        private readonly array $statementFree,
        private readonly array $functions,
        private readonly array $functionNames = [],
    ) {}

    /**
     * Every function or method **body**, as a [first, length] range of
     * significant token indices, in source order.
     *
     * Ruling R's third seed channel is *per-function*, and this is why the
     * boundary belongs in the facts layer rather than in the channel: a bag is a
     * claim about a unit, and the unit has to be the same one the recall curves
     * and the permutation injectors move statements inside of. Bodies only —
     * a signature is shared vocabulary and carries no evidence about behaviour.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function functions(): array
    {
        return $this->functions;
    }

    /**
     * The names of the functions a token range touches, in source order.
     *
     * A reported extent is a token run, and a token run need not begin or end
     * where a function does. Measured at the shipped default, it often does:
     * 20% of php-parser's sites start at a body start and 22% end at a body
     * end, 26% and 27% on symfony/string, 60% and 66% on symfony-console.
     *
     * Those figures replace "0% and 2%", which this comment carried and which
     * was an artifact of the boundary it measured against rather than a fact
     * about the engine — a body was recorded as starting at its opening brace,
     * one token before any match can start, so every site in every corpus
     * missed it by exactly one. Snapping the extent to those boundaries is
     * still refused, and still costs too much to take: outward invents 33.5% to
     * 69.5% of tokens that never matched, inward drops 25.4% to 33.6% that did.
     * So the extent stays as the matcher found it and the reader is told which
     * functions it lands in instead.
     *
     * Touching, not containment: a run through the tail of one function and the
     * head of the next names both, which is the thing a reader cannot otherwise
     * see from a line range. Anonymous bodies are skipped rather than named
     * `{closure}`, because a name nobody wrote is not a name.
     *
     * @return list<string>
     */
    public function functionsTouching(int $start, int $length): array
    {
        $end   = $start + $length - 1;
        $names = [];

        foreach ($this->functions as $at => [$first, $bodyLength]) {
            if ($first + $bodyLength - 1 < $start || $first > $end) {
                continue;
            }

            $name = $this->functionNames[$at] ?? null;

            if ($name !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    public static function fromSource(string $source): self
    {
        $tokens = token_get_all($source);

        /** @var array<int, int> $frameParent */
        $frameParent = [];
        /** @var array<int, int> $frameFirst */
        $frameFirst = [];
        /** @var array<int, int> $frameLast */
        $frameLast = [];
        /** @var array<int, bool> $frameStatementFree */
        $frameStatementFree = [];
        /** @var list<int> $innermost */
        $innermost = [];

        // Every open bracket is pushed, array literal or not: purity has to
        // propagate out of a closure body, and nesting has to stay balanced
        // through parentheses that open no frame of interest.
        /** @var list<int> $stack frame id, or -1 for a bracket that is not an array literal */
        $stack = [];

        /** @var list<array{0: int, 1: int}> $functions */
        $functions = [];
        // Depth in $stack at which a pending `function` keyword expects its body,
        // and the index its body started at. A declaration without a body (an
        // interface method, an abstract one) clears the pending state at its `;`.
        $pendingFunction = false;
        $pendingName     = null;
        /** @var array<int, int> $bodyStart stack depth => first index of the body */
        $bodyStart = [];
        /** @var array<int, ?string> $bodyName stack depth => the body's function name */
        $bodyName = [];
        /** @var list<?string> $functionNames parallel to $functions, null when anonymous */
        $functionNames = [];

        $index = 0;
        // The last token that is neither whitespace nor a comment. Starts as the
        // empty string — a key no token can carry, so the first `[` in a file is
        // an array literal, which is what a file that is nothing but a table needs.
        /** @var int|string $previous */
        $previous = '';

        foreach ($tokens as $token) {
            $type = is_array($token) ? $token[0] : $token;

            if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT) {
                continue;
            }

            $opens  = isset(self::OPENS[$type]);
            $closes = $type === ']' || $type === ')' || $type === '}';

            $isArray = ($type === '[' && !isset(self::SUBSCRIPTED[$previous]))
                || ($type === '(' && $previous === T_ARRAY);

            // A closure or a statement anywhere inside a frame makes it
            // logic-bearing, and that reaches every frame enclosing it — the row
            // holding the closure is not the only thing disqualified.
            if (isset(self::LOGIC[$type])) {
                foreach ($stack as $frame) {
                    if ($frame >= 0 && isset($frameStatementFree[$frame])) {
                        $frameStatementFree[$frame] = false;
                    }
                }
            }

            if ($opens) {
                if ($type === '{' && $pendingFunction) {
                    // The token *after* the brace. A body is what the braces
                    // contain, which is what this class's own docblock promises
                    // — "bodies only, a signature is shared vocabulary" — and
                    // the brace was inside the range while its partner was not.
                    //
                    // The asymmetry was invisible until something compared a
                    // reported extent against it. A match runs from the first
                    // token inside the body, so *every* site sat exactly one
                    // token after the recorded start, and the boundary-alignment
                    // measurement in NEXT-TASKS item 1 read that as total drift:
                    // "0% of sites start at a body start". Measured against the
                    // corrected boundary, 239 of symfony-console's 368 sites
                    // start exactly at one, and 243 end exactly at one.
                    //
                    // The length formula is unchanged and still right: from
                    // `{`+1 to `}`-1 inclusive is `$index - $start` either way,
                    // and an empty body now has $index === $start, which the
                    // guard below already refuses.
                    $bodyStart[count($stack)] = $index + 1;
                    $bodyName[count($stack)]  = $pendingName;
                    $pendingFunction          = false;
                    $pendingName              = null;
                }

                $id = -1;

                if ($isArray) {
                    $id     = count($frameParent);
                    $parent = -1;

                    for ($i = count($stack) - 1; $i >= 0; $i--) {
                        if ($stack[$i] !== -1) {
                            $parent = $stack[$i];

                            break;
                        }
                    }

                    $frameParent[$id] = $parent;
                    $frameFirst[$id]  = $index;
                    $frameLast[$id]   = $index - 1;
                    $frameStatementFree[$id]   = true;
                }

                $stack[] = $id;
            } elseif ($closes) {
                $frame = array_pop($stack);

                if ($frame !== null && $frame >= 0 && isset($frameLast[$frame])) {
                    $frameLast[$frame] = $index - 1;
                }

                if ($type === '}' && isset($bodyStart[count($stack)])) {
                    $start = $bodyStart[count($stack)];
                    $named = $bodyName[count($stack)] ?? null;
                    unset($bodyStart[count($stack)], $bodyName[count($stack)]);

                    if ($index > $start) {
                        $functions[]     = [$start, $index - $start];
                        $functionNames[] = $named;
                    }
                }
            }

            if ($type === T_FUNCTION || $type === T_FN) {
                $pendingFunction = true;
                $pendingName     = null;
            } elseif ($type === ';') {
                $pendingFunction = false;
            } elseif ($pendingFunction && $pendingName === null && $type === T_STRING) {
                // The first identifier after `function` is its name. An anonymous
                // function or an arrow function reaches its `{` with none, and
                // stays unnamed rather than borrowing the next thing it sees.
                $pendingName = $token[1];
            }

            $previous = $type;

            // Everything the encoder's signature holds, and nothing else. That
            // is every token but the ones its ignore list drops — including the
            // single-character ones, which it skipped for as long as they had no
            // line number to record and which it now numbers like any other.
            //
            // These two counts must agree or nothing downstream lines up: a
            // stratum's span is stated in token positions and compared against a
            // clone's, and `RegionStructureTest` asserts the agreement directly
            // for exactly that reason.
            if (is_array($token) && isset(self::IGNORED[$type])) {
                continue;
            }

            $enclosing = -1;

            for ($i = count($stack) - 1; $i >= 0; $i--) {
                if ($stack[$i] !== -1) {
                    $enclosing = $stack[$i];

                    break;
                }
            }

            $innermost[] = $enclosing;
            $index++;
        }

        return new self($innermost, $frameParent, $frameFirst, $frameLast, $frameStatementFree, $functions, $functionNames);
    }

    /**
     * Are these two ranges two runs of elements of **one and the same**
     * statement-free literal array?
     *
     * This is the span discriminator's whole question, and the identity is the
     * point. A literal array is not by itself evidence of anything — the
     * generated parser tables in php-parser's `Php7.php`/`Php8.php` are literal
     * arrays and they are the corpus's flagship *true* positive, because
     * they are two tables that a grammar change regenerates together. What
     * carries no information is a table matching **itself**: the second run is
     * not another copy anyone could edit out, it is the regularity that makes it
     * a table. Every consensus-N data-table finding on the audited corpus is of
     * that shape, and no consensus-Y finding is.
     */
    public function sameLiteralTable(int $startA, int $lengthA, int $startB, int $lengthB): bool
    {
        $a = $this->literalTable($startA, $lengthA);

        return $a !== null && $a === $this->literalTable($startB, $lengthB);
    }

    /**
     * How many significant tokens this description covers. Equal, by
     * construction, to the encoder's token count for the same source — which is
     * the property `RegionStructureTest` pins, because an off-by-one here would
     * silently misattribute every span in the file.
     */
    public function tokenCount(): int
    {
        return count($this->innermost);
    }

    /**
     * The smallest **statement-free** array-literal frame that contains a whole
     * token range, or `null` when the range is not entirely inside one.
     *
     * @param int $start  first significant token index
     * @param int $length token count
     */
    public function literalTable(int $start, int $length): ?int
    {
        if ($length <= 0 || !isset($this->innermost[$start])) {
            return null;
        }

        $end   = $start + $length - 1;
        $frame = $this->innermost[$start];

        while ($frame !== -1 && isset($this->parent[$frame])) {
            if ($this->first[$frame] <= $start && $this->last[$frame] >= $end) {
                return $this->statementFree[$frame] ? $frame : null;
            }

            $frame = $this->parent[$frame];
        }

        return null;
    }
}
