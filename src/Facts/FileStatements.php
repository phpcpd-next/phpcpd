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
use function constant;
use function count;
use function defined;
use function in_array;
use function is_array;
use function is_int;
use function token_get_all;

use const T_CLASS;
use const T_COMMENT;
use const T_DOC_COMMENT;
use const T_FN;
use const T_FUNCTION;
use const T_STATIC;
use const T_DOUBLE_ARROW;
use const T_DOUBLE_COLON;
use const T_VARIABLE;
use const T_WHITESPACE;

/**
 * A file's statements — where each one starts and ends, what shape it has, and
 * which of them are at the file's top level.
 *
 * The second half of the facts layer. {@see RegionStructure} answers *what a
 * region is* (a data table, a function body); this answers *what a file is made
 * of*, which is the question a **role** is asked of. Both are computed from the
 * token stream alone: no path, no filename, no directory — ruling K's ban holds
 * here by construction, because there is nothing in this class that could break
 * it.
 *
 * ## Numbering
 *
 * Positions are significant-token indices, the numbering
 * {@see \LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy::tokenize()}
 * produces and {@see RegionStructure} shares. The ignore list below is therefore
 * a deliberate third copy, and `FileStatementsTest` pins all three together: an
 * off-by-one here would silently misattribute every span.
 *
 * ## Segmentation
 *
 * One frame per open bracket. Only **brace** frames collect statements; a
 * parenthesis, a bracket and a `{` that opens a *name* (`$this->{$p}`,
 * `Foo::{$m}()`) are nesting a statement passes through. A `;` ends a statement
 * only at a brace frame with no expression bracket open above it, and closing a
 * real block ends the statement that held it. The segmentation is **total**:
 * every significant token belongs to exactly one statement, which the test
 * asserts rather than assumes.
 *
 * A statement is **top-level** when the brace frame collecting it is the file's
 * outermost one — so a `namespace Foo { … }` in braced form has no top-level
 * statements inside it, and a file written that way is simply never a
 * registration role. Conservative in the direction that keeps findings asserted.
 *
 * ## Provenance
 *
 * The segmentation and the single-call shape test are this project's own work,
 * written for `bench/precommit-rules.php` during the M5 pre-commitment
 * experiment and moved here when the M5 charter reused the shape test as half of
 * a role definition. Nothing was consulted to write either.
 */
final class FileStatements
{
    /**
     * What the encoder drops — the third copy of this list, pinned to the other
     * two by test rather than shared, for the reason
     * {@see RegionStructure::IGNORED} records.
     *
     * @var ?array<int, true>
     */
    private static ?array $ignored = null;

    /** @var ?array<int, true> */
    private static ?array $disqualifying = null;

    /** @var ?array<int, true> */
    private static ?array $callee = null;

    /** @var ?array<int, true> */
    private static ?array $chain = null;

    /** @var ?array<int, true> */
    private static ?array $preamble = null;

    /** @var ?array<int, true> */
    private static ?array $literalTypes = null;

    /**
     * @param list<Statement> $statements in source order
     * @param list<int>       $statementOf significant index => statement index; total
     * @param list<bool>      $literal     significant index => is it a literal value
     */
    private function __construct(
        private readonly array $statements,
        private readonly array $statementOf,
        private readonly array $literal,
        private readonly int $count,
    ) {}

    /** @return list<Statement> */
    public function all(): array
    {
        return $this->statements;
    }

    /**
     * The statements at the file's outermost brace frame, in source order.
     *
     * @return list<Statement>
     */
    public function topLevel(): array
    {
        $top = [];

        foreach ($this->statements as $statement) {
            if ($statement->topLevel) {
                $top[] = $statement;
            }
        }

        return $top;
    }

    /**
     * How many of the significant tokens in a range are literal values —
     * a string, an integer, a float, or the text inside an interpolated string.
     *
     * Recorded on this pass rather than on a fourth one: the token stream is
     * already being walked, the numbering is already the encoder's, and the
     * alternative was for the presentation tier to tokenize every file a fourth
     * time to ask one question about it. The token class is the one the retired
     * Stage 0 classifier counted, unchanged, so a span's literal share and a
     * file's literal mass are the same measurement asked at two scales.
     */
    public function literals(int $start, int $length): int
    {
        $literals = 0;
        $end      = $start + $length;

        for ($index = $start; $index < $end; $index++) {
            $literals += ($this->literal[$index] ?? false) ? 1 : 0;
        }

        return $literals;
    }

    /** The statement a significant token belongs to, or null past the end. */
    public function at(int $significantIndex): ?Statement
    {
        $index = $this->statementOf[$significantIndex] ?? null;

        return $index === null ? null : $this->statements[$index];
    }

    /**
     * How many significant tokens this description covers — equal, by
     * construction, to the encoder's count for the same source.
     */
    public function tokenCount(): int
    {
        return $this->count;
    }

    public static function fromSource(string $source): self
    {
        $ignored     = self::ignored();
        $literalTypes = self::literalTypes();
        $curlyExpr  = self::resolve(['T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES']);
        $tagTokens  = self::resolve(['T_OPEN_TAG', 'T_OPEN_TAG_WITH_ECHO', 'T_CLOSE_TAG', 'T_INLINE_HTML']);
        $attribute  = self::resolve(['T_ATTRIBUTE']);
        $preambleAt = self::preamble();

        // A `{` directly after one of these opens a *name*, not a block.
        $nameBraceAfter = self::chain();

        // Parallel lists rather than a list of shapes, for the reason
        // {@see RegionStructure} records: a statement is mutated as the scan
        // proceeds — a closure seen late makes it a block — and six flat lists
        // say that plainly.
        /** @var list<int> $first */
        $first = [];
        /** @var list<int> $last */
        $last = [];
        /** @var list<bool> $block */
        $block = [];
        /** @var list<bool> $top */
        $top = [];
        /** @var list<bool> $preamble */
        $preamble = [];
        /** @var list<array<string, true>> $vars */
        $vars = [];
        /** @var array<int, list<array{0: int, 1: int}>> $bodies holder => closure-argument statement ranges */
        $bodies = [];
        /** @var list<list<array{0: int|string, 1: string}>> $pending */
        $pending = [];

        /** @var list<int> $statementOf */
        $statementOf = [];
        /** @var list<bool> $literal */
        $literal = [];

        // One frame per open bracket. Only brace frames collect statements;
        // every other kind is nesting a statement passes through.
        /** @var list<array{kind: string, open: ?int, from: int}> $frames */
        $frames = [['kind' => 'brace', 'open' => null, 'from' => 0]];

        $significant = 0;

        /**
         * Open (or reuse) the statement collecting at the innermost brace
         * frame. A statement opened at frame 0 is top-level, which is the whole
         * of what "top level" means here.
         */
        $current = static function () use (
            &$frames,
            &$first,
            &$last,
            &$block,
            &$top,
            &$preamble,
            &$vars,
            &$pending,
            &$significant,
        ): int {
            for ($i = count($frames) - 1; $i >= 0; $i--) {
                if ($frames[$i]['kind'] !== 'brace') {
                    continue;
                }

                if ($frames[$i]['open'] === null) {
                    $frames[$i]['open'] = count($first);
                    $first[]            = $significant;
                    $last[]             = $significant - 1;
                    $block[]            = false;
                    $top[]              = $i === 0;
                    $preamble[]         = false;
                    $vars[]             = [];
                    $pending[]          = [];
                }

                return $frames[$i]['open'];
            }

            // Unreachable: frame 0 is a brace frame and is never popped.
            return 0;
        };

        /** Close the statement at the innermost brace frame, if one is open. */
        $close = static function () use (&$frames): void {
            for ($i = count($frames) - 1; $i >= 0; $i--) {
                if ($frames[$i]['kind'] === 'brace') {
                    $frames[$i]['open'] = null;

                    return;
                }
            }
        };

        /** @var int|string $previous */
        $previous = '';

        foreach (token_get_all($source) as $token) {
            $type = is_array($token) ? $token[0] : $token;
            $text = is_array($token) ? $token[1] : $token;

            if ($type === T_WHITESPACE || $type === T_COMMENT || $type === T_DOC_COMMENT) {
                continue;
            }

            $statement = $current();

            // Tags are not part of any expression. They are seen — they open and
            // close no frame — and simply do not reach the shape test.
            if (!isset($tagTokens[$type])) {
                $pending[$statement][] = [$type, $text];
            }

            if ($type === T_VARIABLE) {
                $vars[$statement][$text] = true;
            }

            if (is_int($type) && isset($preambleAt[$type]) && $pending[$statement] === [[$type, $text]]) {
                $preamble[$statement] = true;
            }

            if ($type === '{') {
                $frames[] = ['kind' => isset($nameBraceAfter[$previous]) ? 'name' : 'brace', 'open' => null, 'from' => count($first)];
            } elseif (is_int($type) && isset($curlyExpr[$type])) {
                $frames[] = ['kind' => 'name', 'open' => null, 'from' => count($first)];
            } elseif ($type === '(' || $type === '[' || (is_int($type) && isset($attribute[$type]))) {
                $frames[] = ['kind' => $type === '(' ? 'paren' : 'bracket', 'open' => null, 'from' => count($first)];
            } elseif ($type === ')' || $type === ']') {
                if (count($frames) > 1) {
                    array_pop($frames);
                }
            } elseif ($type === '}') {
                if (count($frames) > 1) {
                    $closed = array_pop($frames);

                    // Closing a real block ends the statement that held it — the
                    // `function f() {` header, the `if (…) {` head — and that
                    // statement is a block, never a call. A block in *expression*
                    // position (a closure body inside an argument list) marks its
                    // holder without ending it: the holder's own `;` is still to
                    // come, and splitting there would leave two half-statements
                    // where the source has one.
                    if ($closed['kind'] === 'brace') {
                        $holder             = $current();
                        $pending[$holder][] = [$type, $text];

                        // Only a *real* block makes its holder a block. A brace
                        // in expression position is a closure passed as an
                        // argument — a value the call receives, not a statement
                        // the holder executes — and marking the holder for it
                        // refused every `Route::group(…, function () { … })`
                        // there is. The body's own statements are collected
                        // separately either way, so nothing is lost by reading
                        // the holder as what it is: one call.
                        if ($frames[count($frames) - 1]['kind'] === 'brace') {
                            $block[$holder] = true;
                            $close();
                        } elseif (count($first) > $closed['from']) {
                            // Where the closure's own statements are, so anyone
                            // asking what it holds can look rather than guess.
                            $bodies[$holder][] = [$closed['from'], count($first) - 1];
                        }
                    }
                }
            } elseif ($type === ';') {
                // A `;` ends a statement only where a statement can end: at the
                // innermost brace frame, with no expression bracket open above it.
                if ($frames[count($frames) - 1]['kind'] === 'brace') {
                    $close();
                }
            }

            $previous = $type;

            // Everything the encoder's signature holds, and nothing else —
            // single-character tokens included, since it now numbers them too.
            // `FileStatementsTest` asserts this layer, the region layer and the
            // encoder agree on the count, because a statement's span is quoted
            // in token positions and compared against a clone's.
            if (is_array($token) && isset($ignored[$type])) {
                continue;
            }

            $statementOf[]    = $statement;
            $literal[]        = isset($literalTypes[$type]);
            $last[$statement] = $significant;
            $significant++;
        }

        $built = [];

        foreach ($first as $index => $begin) {
            // Both questions are asked of the statement without its closure
            // arguments, and for one reason: a closure passed as an argument is
            // a value. Its body is already a statement of its own, and its
            // parameters are its own too — `Breadcrumbs::for('x', function
            // (Generator $b) { … })` does not put `$b` into the dataflow of the
            // file's top level, and counting it there made 135 breadcrumb
            // registrations look like one coupled procedure.
            $shape = $block[$index] ? [] : self::withoutClosureArguments($pending[$index]);

            $built[] = new Statement(
                $begin,
                $last[$index],
                !$block[$index] && self::isSingleCall($pending[$index]),
                !$block[$index] && self::isLiteralAppend($pending[$index]),
                $top[$index],
                $preamble[$index],
                $block[$index] ? $vars[$index] : self::variablesOf($shape),
                $bodies[$index] ?? [],
                self::isClosingRun($pending[$index]),
            );
        }

        return new self($built, $statementOf, $literal, $significant);
    }

    /**
     * Is this token list nothing but delimiters closing something already open?
     *
     * `}`, `)`, `]`, `;` and `,` cannot begin a statement, so a run made only
     * of them never was one — it is what the previous statement ends with.
     *
     * @param list<array{0: int|string, 1: string}> $tokens
     */
    private static function isClosingRun(array $tokens): bool
    {
        if ($tokens === []) {
            return true;
        }

        foreach ($tokens as [$type, $text]) {
            if (!in_array($type, ['}', ')', ']', ';', ','], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Does this statement's token list read as one call expression whose value
     * is discarded?
     *
     *     callee ( args ) [ (-> | ?-> | ::) name ( args ) ]*
     *
     * Argument lists are skipped as balanced regions rather than parsed: what is
     * inside an argument does not change whether the statement is a call, and
     * the disqualifying sweep above has already refused anything that could hide
     * a statement in there.
     *
     * @param list<array{0: int|string, 1: string}> $tokens
     */
    private static function isSingleCall(array $tokens): bool
    {
        $disqualifying = self::disqualifying();
        $callee        = self::callee();
        $chain         = self::chain();

        while ($tokens !== [] && $tokens[count($tokens) - 1][0] === ';') {
            array_pop($tokens);
        }

        if ($tokens === []) {
            return false;
        }

        /** @var int|string $previous */
        $previous = '';
        $count    = count($tokens);
        $depth    = 0;

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];
            $type  = $token[0];

            if ($type === '(' || $type === '[') {
                $depth++;
            } elseif ($type === ')' || $type === ']') {
                $depth--;
            }

            // A closure passed as an argument is a value. Its body is already a
            // statement of its own and is not in this list; what is left of it
            // here is the header the scan kept — `static function (…): T` and
            // the `}` that closed it — and reading those as declarations is
            // reading a value as a statement.
            if ($depth > 0 && self::opensClosure($tokens, $index)) {
                $index    = self::pastClosureArgument($tokens, $index);
                $previous = '';

                continue;
            }

            if (is_int($type) && isset($disqualifying[$type])
                && !self::nameAfterDoubleColon($tokens, $index)) {
                return false;
            }

            if ($type === '=' || $type === '&') {
                return false;
            }

            $previous = $type;
        }

        $tokens = self::withoutClosureArguments($tokens);
        $index  = 0;
        $seen   = 0;

        while (isset($tokens[$index]) && is_int($tokens[$index][0])
            && (isset($callee[$tokens[$index][0]]) || self::nameAfterDoubleColon($tokens, $index))) {
            $index++;
            $seen++;
        }

        if ($seen === 0 || ($tokens[$index][0] ?? null) !== '(') {
            return false;
        }

        $next = self::balanced($tokens, $index);

        if ($next === null) {
            return false;
        }

        $index = $next;

        while (isset($tokens[$index])) {
            $type = $tokens[$index][0];

            if (!is_int($type) || !isset($chain[$type])) {
                return false;
            }

            $index++;

            if (!isset($tokens[$index]) || !is_int($tokens[$index][0])
                || (!isset($callee[$tokens[$index][0]]) && !self::nameAfterDoubleColon($tokens, $index))) {
                return false;
            }

            $index++;

            if (($tokens[$index][0] ?? null) !== '(') {
                return false;
            }

            $next = self::balanced($tokens, $index);

            if ($next === null) {
                return false;
            }

            $index = $next;
        }

        return true;
    }

    /**
     * The index just past the balanced region opened at $from, or null if it
     * never closes.
     *
     * @param list<array{0: int|string, 1: string}> $tokens
     */
    private static function balanced(array $tokens, int $from): ?int
    {
        $depth = 0;

        for ($index = $from; isset($tokens[$index]); $index++) {
            $type = $tokens[$index][0];

            if ($type === '(' || $type === '[' || $type === '{') {
                $depth++;
            } elseif ($type === ')' || $type === ']' || $type === '}') {
                $depth--;

                if ($depth === 0) {
                    return $index + 1;
                }

                if ($depth < 0) {
                    return null;
                }
            }
        }

        return null;
    }

    /** @return array<int, true> */
    private static function ignored(): array
    {
        return self::$ignored ??= self::resolve([
            'T_INLINE_HTML', 'T_COMMENT', 'T_DOC_COMMENT', 'T_OPEN_TAG', 'T_OPEN_TAG_WITH_ECHO',
            'T_CLOSE_TAG', 'T_WHITESPACE', 'T_USE', 'T_NS_SEPARATOR',
        ]);
    }

    /**
     * What ends any claim that a statement is a single call expression: every
     * statement keyword, every declaration keyword, every assignment and
     * in-place mutation. Each is a *statement* or a *binding* rather than an
     * expression, and a statement that writes is one another statement can
     * depend on.
     *
     * @return array<int, true>
     */
    private static function disqualifying(): array
    {
        return self::$disqualifying ??= self::resolve([
            'T_IF', 'T_ELSE', 'T_ELSEIF', 'T_ENDIF', 'T_FOR', 'T_ENDFOR', 'T_FOREACH', 'T_ENDFOREACH',
            'T_WHILE', 'T_ENDWHILE', 'T_DO', 'T_SWITCH', 'T_ENDSWITCH', 'T_CASE', 'T_DEFAULT',
            'T_BREAK', 'T_CONTINUE', 'T_GOTO', 'T_RETURN', 'T_THROW', 'T_TRY', 'T_CATCH', 'T_FINALLY',
            'T_ECHO', 'T_PRINT', 'T_YIELD', 'T_YIELD_FROM', 'T_MATCH', 'T_UNSET', 'T_GLOBAL',
            'T_INCLUDE', 'T_INCLUDE_ONCE', 'T_REQUIRE', 'T_REQUIRE_ONCE', 'T_EXIT', 'T_LIST',
            'T_FUNCTION', 'T_FN', 'T_NEW', 'T_CLONE',
            'T_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM', 'T_NAMESPACE', 'T_USE', 'T_CONST',
            'T_VAR', 'T_PUBLIC', 'T_PROTECTED', 'T_PRIVATE', 'T_ABSTRACT', 'T_FINAL', 'T_STATIC',
            'T_READONLY', 'T_DECLARE', 'T_INSTEADOF', 'T_EXTENDS', 'T_IMPLEMENTS',
            'T_PLUS_EQUAL', 'T_MINUS_EQUAL', 'T_MUL_EQUAL', 'T_DIV_EQUAL', 'T_MOD_EQUAL',
            'T_CONCAT_EQUAL', 'T_AND_EQUAL', 'T_OR_EQUAL', 'T_XOR_EQUAL', 'T_SL_EQUAL', 'T_SR_EQUAL',
            'T_POW_EQUAL', 'T_COALESCE_EQUAL', 'T_INC', 'T_DEC',
        ]);
    }

    /**
     * The variables a statement names, its closure arguments already removed.
     *
     * @param  list<array{0: int|string, 1: string}> $tokens
     * @return array<string, true>
     */
    private static function variablesOf(array $tokens): array
    {
        $names = [];

        foreach ($tokens as $token) {
            if ($token[0] === T_VARIABLE) {
                $names[$token[1]] = true;
            }
        }

        return $names;
    }

    /**
     * Does a closure begin at this index, allowing the `static` a framework
     * callback is usually written with?
     *
     * @param list<array{0: int|string, 1: string}> $tokens
     */
    private static function opensClosure(array $tokens, int $index): bool
    {
        $type = $tokens[$index][0];

        if ($type === T_STATIC) {
            $type = $tokens[$index + 1][0] ?? null;
        }

        return $type === T_FUNCTION || $type === T_FN;
    }

    /**
     * The index of the last token belonging to a closure argument beginning at
     * $index — its header, and the `}` the scan left behind for its body.
     *
     * A braced closure ends at that `}`. An arrow function has no braces and its
     * body is an expression that stayed in this list, so it ends where its
     * argument does: the first `,` or `)` at the depth it started from.
     *
     * @param list<array{0: int|string, 1: string}> $tokens
     */
    private static function pastClosureArgument(array $tokens, int $index): int
    {
        $count = count($tokens);
        $depth = 0;

        for ($at = $index; $at < $count; $at++) {
            $type = $tokens[$at][0];

            if ($type === '}') {
                return $at;
            }

            if ($type === '(' || $type === '[') {
                $depth++;

                continue;
            }

            if ($type === ')' || $type === ']') {
                if ($depth === 0) {
                    return $at - 1;
                }

                $depth--;

                continue;
            }

            if ($depth === 0 && $type === ',' && $at > $index) {
                return $at - 1;
            }
        }

        return $count - 1;
    }

    /**
     * The statement with every closure argument taken out of it, so the callee
     * walk sees the call the source wrote.
     *
     * @param  list<array{0: int|string, 1: string}> $tokens
     * @return list<array{0: int|string, 1: string}>
     */
    private static function withoutClosureArguments(array $tokens): array
    {
        $kept  = [];
        $count = count($tokens);
        $depth = 0;

        for ($index = 0; $index < $count; $index++) {
            $type = $tokens[$index][0];

            if ($type === '(' || $type === '[') {
                $depth++;
            } elseif ($type === ')' || $type === ']') {
                $depth--;
            }

            if ($depth > 0 && self::opensClosure($tokens, $index)) {
                $index = self::pastClosureArgument($tokens, $index);

                continue;
            }

            $kept[] = $tokens[$index];
        }

        return $kept;
    }

    /**
     * Does this statement read as `$name[] = <literal>;` — one element of a
     * table written as statements?
     *
     * Data does not have to be spelled as an array literal. A seeder writes the
     * same table as a run of appends, and {@see RegionStructure::literalTable()}
     * cannot see it: that frame has to be **statement-free**, and a run of
     * appends is nothing but statements. The two spellings are the same thing to
     * a reader, so they are the same thing here.
     *
     * The right-hand side must be literal all the way down — strings, numbers,
     * and the punctuation an array is built from. One call, one variable, one
     * constant on the right and this is not a table element any more, it is code
     * that computes something, and the whole point of the table scope is that it
     * names data rather than measuring how literal something is.
     *
     * @param list<array{0: int|string, 1: string}> $tokens
     */
    private static function isLiteralAppend(array $tokens): bool
    {
        while ($tokens !== [] && $tokens[count($tokens) - 1][0] === ';') {
            array_pop($tokens);
        }

        // `$name` `[` `]` `=` and at least one token of value.
        if (count($tokens) < 5
            || $tokens[0][0] !== T_VARIABLE
            || $tokens[1][0] !== '['
            || $tokens[2][0] !== ']'
            || $tokens[3][0] !== '=') {
            return false;
        }

        $literals = self::literalTypes();

        foreach (array_slice($tokens, 4) as $token) {
            $type = $token[0];

            if (is_int($type)) {
                if (isset($literals[$type]) || $type === T_DOUBLE_ARROW) {
                    continue;
                }

                return false;
            }

            if ($type === '[' || $type === ']' || $type === ',' || $type === '-') {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * The run of literal appends to one array that wholly contains this token
     * range, identified by where it begins — or null when the range is not
     * inside one.
     *
     * The identity is the point, and it is the same one
     * {@see RegionStructure::sameLiteralTable()} makes for array literals: a
     * table matching **itself** is the regularity that makes it a table, and its
     * second run is not a copy anybody could edit out. Two *different* seeders
     * agreeing is something else, and the M5 rating round called that shape
     * genuine duplication, so it must not answer the same way. A run is
     * therefore named by its own first statement, and a caller comparing two
     * sites compares the names.
     *
     * @param int $start  first significant token index
     * @param int $length how many significant tokens the range covers
     */
    public function literalAppendRun(int $start, int $length): ?int
    {
        if ($length <= 0) {
            return null;
        }

        $end   = $start + $length - 1;
        $first = $this->statementOf[$start] ?? null;
        $last  = $this->statementOf[$end] ?? null;

        if ($first === null || $last === null) {
            return null;
        }

        // A statement the span only clips is not in the run, because the clone
        // does not contain it. A match begins and ends where the tokens
        // agreed, which is routinely partway through a statement: this clone
        // starts at the `]` of `$rows = [];`, holding two of that statement's
        // five tokens. Counting it made a table written as statements fail to
        // read as one, for a statement it does not hold.
        if ($this->statements[$first]->first < $start) {
            $first++;
        }

        if (isset($this->statements[$last]) && $this->statements[$last]->last > $end) {
            $last--;
        }

        if ($first > $last || !isset($this->statements[$first], $this->statements[$last])) {
            return null;
        }

        for ($index = $first; $index <= $last; $index++) {
            $statement = $this->statements[$index] ?? null;

            // A run of closing delimiters is how the enclosing construct ends,
            // not a member of the run. Since the encoder began counting
            // single-character tokens a span can reach one, and a table written
            // as statements stopped reading as one for the sake of the `}` that
            // closes the method holding it.
            if ($statement !== null && $statement->closing) {
                continue;
            }

            if (!($statement->literalAppend ?? false)) {
                return null;
            }
        }

        $name = self::soleVariable($this->statements[$first]);

        if ($name === null) {
            return null;
        }

        // Back to the run's own beginning, so two ranges inside one run are
        // named alike however each of them happens to start.
        $begin = $first;

        while ($begin > 0
            && $this->statements[$begin - 1]->literalAppend
            && self::soleVariable($this->statements[$begin - 1]) === $name) {
            $begin--;
        }

        for ($index = $first; $index <= $last; $index++) {
            $statement = $this->statements[$index];

            // Skipped for the reason the loop above skips it, which this loop
            // did not, so that skip never took effect: a run of closing
            // delimiters names no variable, so asking which one it names
            // rejected the very spans the first loop had just admitted.
            if ($statement->closing) {
                continue;
            }

            if (self::soleVariable($statement) !== $name) {
                return null;
            }
        }

        return $begin;
    }

    /** The one variable a statement names, or null when it names none or several. */
    private static function soleVariable(Statement $statement): ?string
    {
        if (count($statement->variables) !== 1) {
            return null;
        }

        return (string) array_key_first($statement->variables);
    }

    /**
     * Is the token at this index a NAME that the tokenizer happened to spell as
     * a keyword?
     *
     * PHP allows every reserved word as a member name. After `->` and `?->` the
     * lexer knows it is looking for a property and hands back `T_STRING`, so
     * nothing is needed there. After `::` it does not, and the keyword arrives
     * as itself: `Foo::class` as `T_CLASS`, `Breadcrumbs::for(…)` as `T_FOR`.
     *
     * Both the disqualifying sweep and the callee walk used to know about
     * `T_CLASS` alone — the sweep by name, the walk not at all — which read
     * `Breadcrumbs::for(…)` as a `for` loop and refused it. That is every
     * statement in firefly-iii's `routes/breadcrumbs.php`, 0 of 136, and with
     * it any chance of {@see FileRole} seeing that file as what it is. `class`
     * was never special; it was the one that came up first, and the position
     * after `::` is what decides.
     *
     * @param list<array{0: int|string, 1: string}> $tokens
     */
    private static function nameAfterDoubleColon(array $tokens, int $index): bool
    {
        return $index > 0 && ($tokens[$index - 1][0] ?? null) === T_DOUBLE_COLON;
    }

    /**
     * The tokens a callee may be spelled with: a name, a variable, and the three
     * ways PHP joins them.
     *
     * @return array<int, true>
     */
    private static function callee(): array
    {
        return self::$callee ??= self::resolve([
            'T_STRING', 'T_VARIABLE', 'T_NS_SEPARATOR', 'T_DOUBLE_COLON', 'T_OBJECT_OPERATOR',
            'T_NULLSAFE_OBJECT_OPERATOR', 'T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED',
            'T_NAME_RELATIVE',
        ]);
    }

    /** @return array<int, true> the three operators that open a fluent chain link */
    private static function chain(): array
    {
        return self::$chain ??= self::resolve([
            'T_DOUBLE_COLON', 'T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR',
        ]);
    }

    /**
     * A literal value: a string, an integer, a float, or the text inside an
     * interpolated string. The set the retired Stage 0 classifier counted,
     * unchanged.
     *
     * @return array<int, true>
     */
    private static function literalTypes(): array
    {
        return self::$literalTypes ??= self::resolve([
            'T_CONSTANT_ENCAPSED_STRING', 'T_LNUMBER', 'T_DNUMBER', 'T_ENCAPSED_AND_WHITESPACE',
        ]);
    }

    /**
     * The three keywords that open a declaration a file needs in order to
     * compile rather than a thing the file does: `declare`, `namespace`, `use`.
     *
     * @return array<int, true>
     */
    private static function preamble(): array
    {
        return self::$preamble ??= self::resolve(['T_DECLARE', 'T_NAMESPACE', 'T_USE']);
    }

    /**
     * Token classes resolved by name against this PHP version: a token this
     * version cannot produce is a token that cannot appear, not a reason to
     * refuse the file.
     *
     * @param  list<string>     $names
     * @return array<int, true>
     */
    private static function resolve(array $names): array
    {
        $set = [];

        foreach ($names as $name) {
            if (!defined($name)) {
                continue;
            }

            $id = constant($name);

            if (is_int($id)) {
                $set[$id] = true;
            }
        }

        return $set;
    }
}
