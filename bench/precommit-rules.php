#!/usr/bin/env php
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

/*
 * The M5 pre-commitment experiment — rules 8 and 9 as post-hoc measurement
 * filters.
 *
 * The plan's "After M4" section opens M5 **only on a passing pre-commitment
 * experiment**: before any engine change, the two candidate precision rules are
 * prototyped here, in `bench/`, and tested against the recorded evidence. No
 * `src/` file changes, no product behaviour changes, and the outcome decides
 * whether the milestone is built at all. This script is that prototype and that
 * instrument; `docs/research/audit/M5-experiment.md` is its report.
 *
 * ## Rule 8 candidate — order-independence
 *
 * *"A span whose statements are pairwise dataflow-independent single
 * call-expressions is configuration written as statements, not duplicated
 * logic."*
 *
 * A membership test, with no constant, in the shape ruling 5 fixed for this
 * project: name a token class and ask the language a question it already
 * answers. Three questions, all structural:
 *
 *   1. **Is the span made of statements?** Every significant token in it belongs
 *      to a statement; the segmentation below assigns every token to exactly
 *      one, so this is total by construction.
 *   2. **Is each statement a single call-expression?** Its tokens read as a
 *      callee, one argument list, and any number of fluent chain links — and
 *      nothing else. Any statement keyword, any assignment or mutation, any
 *      declaration, any closure disqualifies it outright.
 *   3. **Are they pairwise dataflow-independent?** Two statements share no
 *      variable name.
 *
 * **Why "shares a variable" is the whole dataflow test, stated before the
 * measurement.** At token level a call's effects are invisible: `$b->add(1)` may
 * or may not mutate `$b`, and an argument may or may not be by reference. The
 * only channel that *is* visible is the variable itself, so two statements
 * naming one variable are treated as dependent and the span is kept. `$this`
 * is a variable like any other, which is deliberate: a run of `$this->assertX()`
 * calls in a test is a procedure over one receiver, and this rule is not
 * entitled to silence it. The reading is conservative in the direction the
 * project's cost asymmetry names (interpretation rule 5): a false silence costs
 * a real clone, a false keep costs one noisy finding.
 *
 * ## Rule 9 candidate — literal overlap
 *
 * *"Two shape-matched statement-free tables are the same data only if their
 * literal values substantially overlap."*
 *
 * Scope is a membership test — both spans sit wholly inside a **statement-free
 * array-literal frame**, which is ruling 5's granted definition of *literal*,
 * read from the shipped facts layer ({@see RegionStructure}) rather than
 * reimplemented here. Shape-matching is not tested: the engine reporting the
 * pair *is* the shape match.
 *
 * The statistic is the **Jaccard overlap of the distinct literal values** in the
 * two spans — `T_CONSTANT_ENCAPSED_STRING`, `T_LNUMBER`, `T_DNUMBER`, by their
 * source text — `|A ∩ B| / |A ∪ B|`. Symmetric, bounded, and unweighted by span
 * length, so a long table and a short one are compared on their contents rather
 * than on their sizes.
 *
 * ### The floor is derived, and the derivation rule is pre-registered here
 *
 * The floor is **not** written into this file. It is derived, inside the
 * experiment, from the consensus labels of the two preserved worksheets, and it
 * is passed in as an argument to every mode that applies rule 9.
 *
 * The derivation rule, fixed before any number was looked at, follows the
 * project's own precedent — `Winnower::NORMALIZED_DIVERSITY_FLOOR`, which is
 * *"the smallest floor that clears the data case entirely while sitting at the
 * function-body distribution's own 1st percentile … derived from that
 * separation, not tuned"*:
 *
 *   floor = the smallest two-decimal value strictly greater than the **largest**
 *           overlap observed among in-scope **consensus-N** findings,
 *   valid only if that value is **≤ the smallest** overlap observed among
 *           in-scope **consensus-Y** findings.
 *
 * Hugging the N side rather than splitting the gap is the cost asymmetry again:
 * the smallest floor that clears the false positives is the one that silences
 * least. If the two label sets do not separate — if some consensus-Y finding
 * overlaps no more than some consensus-N one — **there is no floor to derive**,
 * and that is reported as a failure of rule 9 rather than as a number chosen to
 * make a criterion pass.
 *
 * ## What "silences a finding" means, also fixed in advance
 *
 * A finding is a clone class with two or more sites, and both rules are read so
 * that any surviving evidence keeps it:
 *
 *   - **rule 8** silences a finding when *every* one of its sites is an
 *     order-independent configuration span;
 *   - **rule 9** silences a finding when *every* pair of its sites is in scope
 *     and *every* pair's overlap is below the floor. A pair the rule cannot
 *     speak about keeps the finding.
 *
 * ## The corpus is never named
 *
 * Every path is an argument, never defaulted and never recorded here. The
 * worksheets and the relocated site tables live outside every repository at mode
 * 600; this script reads them and reports aggregates. Only the public bench
 * corpora are ever quoted by name.
 *
 * Usage:
 *   php bench/precommit-rules.php self-test
 *   php bench/precommit-rules.php measure --corpus=<root> --pin=<manifest> \
 *       --sheet=<label>:<raterA.tsv>:<raterB.tsv>:<key.tsv>:<sites.tsv> [--sheet=…]
 *   php bench/precommit-rules.php corpus <dir> --floor=<f> [--min-tokens=70] [--min-lines=5]
 *   php bench/precommit-rules.php explain --corpus=<root> --pin=<manifest> --sites=<table>
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\Detector\Strategy\DefaultStrategy;
use LucianoPereira\PhpcpdNext\Detector\Strategy\Normalization;
use LucianoPereira\PhpcpdNext\Detector\Strategy\StrategyConfiguration;
use LucianoPereira\PhpcpdNext\Facts\RegionStructure;

// ---------------------------------------------------------------------------
// Token-level analysis — statements, call shape, variables, literals
// ---------------------------------------------------------------------------

/**
 * One statement, as this script segments them: the significant-token range it
 * covers, whether it reads as a single call expression, and the variables it
 * names. Mutable, because a statement is decided as the scan crosses it — a
 * closure met late makes it a block.
 */
final class PrStatement
{
    public bool $singleCall = true;

    /** @var array<string, true> */
    public array $vars = [];

    public function __construct(public int $first, public int $last) {}
}

/**
 * One file's analysis. `stmtOf` is indexed by significant token, and is total:
 * every token the encoder numbers belongs to exactly one statement.
 */
final class PrFileFacts
{
    /**
     * @param list<PrStatement> $statements
     * @param list<int>         $stmtOf     significant index => statement index
     * @param list<?string>     $literals   significant index => literal text, or null
     */
    public function __construct(
        public array $statements,
        public array $stmtOf,
        public array $literals,
        public int $count,
    ) {}
}


/**
 * The token classes the two rules name, resolved once against this PHP version.
 *
 * Several of these tokens only exist on some versions, so the lists are written
 * as names and asked for with `defined()` — a missing token is a token this
 * version cannot produce, not a reason for the file to refuse to parse.
 */
// phpcpd-ignore-start
//
// The statement segmentation and the single-call shape test below are this
// project's own work, and they were MOVED from here into
// `src/Facts/FileStatements.php` when rule 8's membership test was promoted
// out of the experiment. The copy that stayed is the instrument, and it has
// to keep running exactly as `docs/research/audit/M5-experiment.md` recorded
// it — an experiment whose apparatus is edited afterwards has stopped being
// evidence for its own result. So this is duplication that is correct to
// leave and wrong to fix, which is what the region notation is for.
//
// Declared on this side only, deliberately: the live class in `src/` must
// stay visible to the scan. A clone is dropped when any of its copies sits
// in a declared range, so tagging the frozen copy is enough to silence the
// pair without hiding the code that is still maintained.

final class PrTokens
{
    /** @var ?array<int, true> */
    private static ?array $disqualifying = null;

    /** @var ?array<int, true> */
    private static ?array $callee = null;

    /** @var ?array<int, true> */
    private static ?array $chain = null;

    /**
     * What ends any claim that a statement is a single call expression. Three
     * families, and every one of them is a *statement* or a *binding* rather
     * than an expression:
     *
     *   - control flow and other statement keywords, including `T_MATCH` (an
     *     expression, but one that holds arms this rule has no reading of) and
     *     the two closure openers ruling 5 already named;
     *   - every assignment and in-place mutation, because a statement that
     *     writes is a statement another statement can depend on;
     *   - every declaration keyword, because a declaration is not a call.
     *
     * @return array<int, true>
     */
    public static function disqualifying(): array
    {
        return self::$disqualifying ??= self::resolve([
            // statements and bindings
            'T_IF', 'T_ELSE', 'T_ELSEIF', 'T_ENDIF', 'T_FOR', 'T_ENDFOR', 'T_FOREACH', 'T_ENDFOREACH',
            'T_WHILE', 'T_ENDWHILE', 'T_DO', 'T_SWITCH', 'T_ENDSWITCH', 'T_CASE', 'T_DEFAULT',
            'T_BREAK', 'T_CONTINUE', 'T_GOTO', 'T_RETURN', 'T_THROW', 'T_TRY', 'T_CATCH', 'T_FINALLY',
            'T_ECHO', 'T_PRINT', 'T_YIELD', 'T_YIELD_FROM', 'T_MATCH', 'T_UNSET', 'T_GLOBAL',
            'T_INCLUDE', 'T_INCLUDE_ONCE', 'T_REQUIRE', 'T_REQUIRE_ONCE', 'T_EXIT', 'T_LIST',
            'T_FUNCTION', 'T_FN', 'T_NEW', 'T_CLONE',
            // declarations
            'T_CLASS', 'T_INTERFACE', 'T_TRAIT', 'T_ENUM', 'T_NAMESPACE', 'T_USE', 'T_CONST',
            'T_VAR', 'T_PUBLIC', 'T_PROTECTED', 'T_PRIVATE', 'T_ABSTRACT', 'T_FINAL', 'T_STATIC',
            'T_READONLY', 'T_DECLARE', 'T_INSTEADOF', 'T_EXTENDS', 'T_IMPLEMENTS',
            // assignment and in-place mutation
            'T_PLUS_EQUAL', 'T_MINUS_EQUAL', 'T_MUL_EQUAL', 'T_DIV_EQUAL', 'T_MOD_EQUAL',
            'T_CONCAT_EQUAL', 'T_AND_EQUAL', 'T_OR_EQUAL', 'T_XOR_EQUAL', 'T_SL_EQUAL', 'T_SR_EQUAL',
            'T_POW_EQUAL', 'T_COALESCE_EQUAL', 'T_INC', 'T_DEC',
        ]);
    }

    /**
     * The tokens a callee may be spelled with: a name, a variable, and the
     * three ways PHP joins them.
     *
     * @return array<int, true>
     */
    public static function callee(): array
    {
        return self::$callee ??= self::resolve([
            'T_STRING', 'T_VARIABLE', 'T_NS_SEPARATOR', 'T_DOUBLE_COLON', 'T_OBJECT_OPERATOR',
            'T_NULLSAFE_OBJECT_OPERATOR', 'T_NAME_QUALIFIED', 'T_NAME_FULLY_QUALIFIED',
            'T_NAME_RELATIVE',
        ]);
    }

    /** @return array<int, true> the three operators that open a fluent chain link */
    public static function chain(): array
    {
        return self::$chain ??= self::resolve(['T_DOUBLE_COLON', 'T_OBJECT_OPERATOR', 'T_NULLSAFE_OBJECT_OPERATOR']);
    }

    /**
     * @param  list<string>     $names
     * @return array<int, true>
     */
    public static function resolve(array $names): array
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

/**
 * One file, analysed once: which statement every significant token belongs to,
 * what each statement is, and which significant tokens are literal values.
 *
 * Significant-token numbering is the encoder's — the same numbering
 * {@see DefaultStrategy::tokenize()} and {@see RegionStructure} use — so a span
 * expressed in one is a span in all three. Whitespace and comments are dropped
 * before anything else happens; everything else the encoder ignores is still
 * *seen* here (it carries structure) and simply does not advance the index.
 *
 */
function pr_analyze(string $source): PrFileFacts
{
    $ignored = [];

    foreach (['T_INLINE_HTML', 'T_COMMENT', 'T_DOC_COMMENT', 'T_OPEN_TAG', 'T_OPEN_TAG_WITH_ECHO',
        'T_CLOSE_TAG', 'T_WHITESPACE', 'T_USE', 'T_NS_SEPARATOR'] as $name) {
        $ignored[(int) constant($name)] = true;
    }

    $literalTypes = [T_CONSTANT_ENCAPSED_STRING => true, T_LNUMBER => true, T_DNUMBER => true];

    $tagTokens = [
        T_OPEN_TAG            => true,
        T_OPEN_TAG_WITH_ECHO  => true,
        T_CLOSE_TAG           => true,
        T_INLINE_HTML         => true,
    ];

    $curlyExpr = [];

    foreach (['T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES'] as $name) {
        if (defined($name)) {
            $curlyExpr[(int) constant($name)] = true;
        }
    }

    // A `{` directly after one of these opens a *name* — `$this->{$p}`,
    // `Foo::{$m}()` — not a block. Mistaking one for a block would end a
    // statement in the middle of an expression.
    $nameBraceAfter = PrTokens::chain();

    /** @var list<PrStatement> $statements */
    $statements = [];
    /** @var list<int> $stmtOf */
    $stmtOf = [];
    /** @var list<?string> $literals */
    $literals = [];

    // One frame per open bracket of any kind. `open` is the statement currently
    // being collected at that frame; only `brace` frames collect statements,
    // every other kind is nesting a statement passes through.
    /** @var list<array{kind: string, open: ?int}> $frames */
    $frames = [['kind' => 'brace', 'open' => null]];

    /** @var list<list<array{0: int|string, 1: string}>> $pending token lists, parallel to $statements */
    $pending = [];

    $sig = 0;

    /**
     * Open (or reuse) the statement collecting at the innermost brace frame.
     */
    $current = static function () use (&$frames, &$statements, &$pending, &$sig): int {
        for ($i = count($frames) - 1; $i >= 0; $i--) {
            if ($frames[$i]['kind'] !== 'brace') {
                continue;
            }

            if ($frames[$i]['open'] === null) {
                $frames[$i]['open'] = count($statements);
                $statements[]       = new PrStatement($sig, $sig - 1);
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

        // Tags are not part of any expression. They are seen (they close no
        // frame and open none) and simply do not reach the shape test.
        if (!isset($tagTokens[$type])) {
            $pending[$statement][] = [$type, $text];
        }

        if ($type === T_VARIABLE) {
            $statements[$statement]->vars[$text] = true;
        }

        // --- structure: what this token does to the frame stack -------------
        $isBlockBrace = false;

        if ($type === '{') {
            $isBlockBrace = !isset($nameBraceAfter[$previous]);
            $frames[]     = ['kind' => $isBlockBrace ? 'brace' : 'name', 'open' => null];
        } elseif (isset($curlyExpr[$type])) {
            $frames[] = ['kind' => 'name', 'open' => null];
        } elseif ($type === '(' || $type === '[' || (defined('T_ATTRIBUTE') && $type === T_ATTRIBUTE)) {
            $frames[] = ['kind' => $type === '(' ? 'paren' : 'bracket', 'open' => null];
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
                    $holder = $current();
                    $statements[$holder]->singleCall = false;
                    $pending[$holder][] = [$type, $text];

                    if ($frames[count($frames) - 1]['kind'] === 'brace') {
                        $close();
                    }
                }
            }
        } elseif ($type === ';') {
            // A `;` ends the statement only where a statement can end: at the
            // innermost brace frame, with no expression bracket open above it.
            if ($frames[count($frames) - 1]['kind'] === 'brace') {
                $close();
            }
        }

        $previous = $type;

        // --- the encoder's numbering ---------------------------------------
        if (!is_array($token) || isset($ignored[$type])) {
            continue;
        }

        $stmtOf[]   = $statement;
        $literals[] = isset($literalTypes[$type]) ? $text : null;
        $statements[$statement]->last = $sig;
        $sig++;
    }

    foreach ($statements as $index => $statement) {
        if ($statement->singleCall) {
            $statement->singleCall = pr_is_single_call($pending[$index]);
        }
    }

    return new PrFileFacts($statements, $stmtOf, $literals, $sig);
}

/**
 * Does this statement's token list read as one call expression?
 *
 * `callee ( args ) [ (-> | ?-> | ::) name ( args ) ]* ;`
 *
 * and nothing else. The argument lists are skipped as balanced regions rather
 * than parsed: what is inside an argument does not change whether the statement
 * is a call, and the disqualifying-token sweep has already refused anything
 * that could hide a statement in there.
 *
 * @param list<array{0: int|string, 1: string}> $tokens
 */
function pr_is_single_call(array $tokens): bool
{
    $disqualifying = PrTokens::disqualifying();
    $callee        = PrTokens::callee();
    $chain         = PrTokens::chain();

    // Trailing `;` is punctuation, not part of the expression.
    while ($tokens !== [] && $tokens[count($tokens) - 1][0] === ';') {
        array_pop($tokens);
    }

    if ($tokens === []) {
        return false;
    }

    /** @var int|string $previous */
    $previous = '';

    foreach ($tokens as $token) {
        $type = $token[0];

        if (is_int($type) && isset($disqualifying[$type])) {
            // `Foo::class` is a name, not a class declaration, and the
            // tokenizer spells its second half `T_CLASS`. It is the one member
            // of the list with an expression reading, and only directly after
            // `::`.
            if ($type !== T_CLASS || $previous !== T_DOUBLE_COLON) {
                return false;
            }
        }

        if ($type === '=' || $type === '&') {
            return false;
        }

        $previous = $type;
    }

    /**
     * Skip the balanced region opened at $from; the index just past its close,
     * or null if it never closes.
     */
    $balanced = static function (int $from) use ($tokens): ?int {
        $depth = 0;

        for ($i = $from; isset($tokens[$i]); $i++) {
            $type = $tokens[$i][0];

            if ($type === '(' || $type === '[' || $type === '{') {
                $depth++;
            } elseif ($type === ')' || $type === ']' || $type === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i + 1;
                }

                if ($depth < 0) {
                    return null;
                }
            }
        }

        return null;
    };

    $i    = 0;
    $seen = 0;

    while (isset($tokens[$i]) && is_int($tokens[$i][0]) && isset($callee[$tokens[$i][0]])) {
        $i++;
        $seen++;
    }

    if ($seen === 0 || !isset($tokens[$i]) || $tokens[$i][0] !== '(') {
        return false;
    }

    $next = $balanced($i);

    if ($next === null) {
        return false;
    }

    $i = $next;

    // Fluent chain links: `->name(…)`, `?->name(…)`, `::name(…)`.
    while (isset($tokens[$i])) {
        $type = $tokens[$i][0];

        if (!is_int($type) || !isset($chain[$type])) {
            return false;
        }

        $i++;

        if (!isset($tokens[$i]) || !is_int($tokens[$i][0]) || !isset($callee[$tokens[$i][0]])) {
            return false;
        }

        $i++;

        if (!isset($tokens[$i]) || $tokens[$i][0] !== '(') {
            return false;
        }

        $next = $balanced($i);

        if ($next === null) {
            return false;
        }

        $i = $next;
    }

    return true;
}

// phpcpd-ignore-end

// ---------------------------------------------------------------------------
// The two candidate rules
// ---------------------------------------------------------------------------

/**
 * Rule 8 — is this span configuration written as statements?
 */
function pr_rule8(PrFileFacts $facts, int $start, int $length): bool
{
    if ($length <= 0 || !isset($facts->stmtOf[$start])) {
        return false;
    }

    $end = $start + $length - 1;

    if (!isset($facts->stmtOf[$end])) {
        return false;
    }

    /** @var array<int, true> $ids */
    $ids = [];

    for ($i = $start; $i <= $end; $i++) {
        $ids[$facts->stmtOf[$i]] = true;
    }

    if (count($ids) < 2) {
        return false;
    }

    /** @var list<array<string, true>> $variableSets */
    $variableSets = [];

    foreach (array_keys($ids) as $id) {
        if (!$facts->statements[$id]->singleCall) {
            return false;
        }

        $variableSets[] = $facts->statements[$id]->vars;
    }

    $count = count($variableSets);

    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            if (array_intersect_key($variableSets[$i], $variableSets[$j]) !== []) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Rule 9 — the literal overlap of two spans, or `null` when the rule has
 * nothing to say about them (either span is not wholly inside a statement-free
 * array-literal frame, or one of them holds no literal at all).
 *
 */
function pr_rule9_overlap(
    PrFileFacts $factsA,
    RegionStructure $shapeA,
    int $startA,
    int $lengthA,
    PrFileFacts $factsB,
    RegionStructure $shapeB,
    int $startB,
    int $lengthB,
): ?float {
    if ($shapeA->literalTable($startA, $lengthA) === null || $shapeB->literalTable($startB, $lengthB) === null) {
        return null;
    }

    $a = pr_literal_set($factsA->literals, $startA, $lengthA);
    $b = pr_literal_set($factsB->literals, $startB, $lengthB);

    if ($a === [] || $b === []) {
        return null;
    }

    $union = count($a + $b);

    return count(array_intersect_key($a, $b)) / $union;
}

/**
 * The same Jaccard statistic with the scope test removed, for reporting a
 * named regression's number even when the rule declines to speak about it.
 * Never used to silence anything.
 */
function pr_overlap_ignoring_scope(PrFile $a, int $startA, int $lengthA, PrFile $b, int $startB, int $lengthB): ?float
{
    $x = pr_literal_set($a->facts->literals, $startA, $lengthA);
    $y = pr_literal_set($b->facts->literals, $startB, $lengthB);

    if ($x === [] || $y === []) {
        return null;
    }

    return count(array_intersect_key($x, $y)) / count($x + $y);
}

/**
 * @param  list<?string>          $literals
 * @return array<string, true>
 */
function pr_literal_set(array $literals, int $start, int $length): array
{
    $set = [];
    $end = $start + $length - 1;

    for ($i = $start; $i <= $end; $i++) {
        $value = $literals[$i] ?? null;

        if ($value !== null) {
            $set[$value] = true;
        }
    }

    return $set;
}

// ---------------------------------------------------------------------------
// Files, spans, and the cache both measurement modes share
// ---------------------------------------------------------------------------

/**
 * Everything one file contributes: the shipped facts layer, the encoder's
 * line-per-token table, and this script's own statement analysis.
 */
final class PrFile
{
    /** @param list<int> $lines significant index => real source line */
    public function __construct(
        public RegionStructure $shape,
        public array $lines,
        public PrFileFacts $facts,
    ) {}
}

/**
 * One file, read and analysed once. A file that cannot be read is `null`, and
 * both measurement modes treat that as evidence they do not have rather than
 * as evidence of anything.
 */
function pr_file(string $absolute): ?PrFile
{
    /** @var array<string, ?PrFile> $cache */
    static $cache = [];

    if (array_key_exists($absolute, $cache)) {
        return $cache[$absolute];
    }

    $source = @file_get_contents($absolute);

    if ($source === false) {
        return $cache[$absolute] = null;
    }

    // The encoder's own configuration: only its tokenizer is used, and only
    // for the line-per-token table, so the detection knobs are inert here.
    $encoder = new DefaultStrategy(new StrategyConfiguration(5, 70, Normalization::Raw, 1.0));

    return $cache[$absolute] = new PrFile(
        RegionStructure::fromSource($source),
        $encoder->tokenize($source)->tokenRealLines,
        pr_analyze($source),
    );
}

/**
 * A reported site is a first line and a line count; both rules ask about token
 * indices. The span is every significant token whose real line falls in the
 * range — the same mapping `bench/triage.php`'s span tier uses, so the two
 * tiers are scored against the same spans.
 *
 * @param  list<int> $tokenLines
 * @return ?array{0: int, 1: int}
 */
function pr_span(array $tokenLines, int $startLine, int $lineCount): ?array
{
    $last  = $startLine + $lineCount - 1;
    $first = null;
    $end   = null;

    foreach ($tokenLines as $index => $line) {
        if ($line >= $startLine && $line <= $last) {
            $first ??= $index;
            $end = $index;
        }
    }

    return $first === null || $end === null ? null : [$first, $end - $first + 1];
}

/**
 * Both rules over one finding's sites.
 *
 * @param  list<array{0: string, 1: int, 2: int}> $sites  [absolute path, start line, line count]
 * @return array{rule8: bool, rule9: ?float, pairs: int, inScope: int}
 */
function pr_apply(array $sites): array
{
    /** @var list<array{file: PrFile, span: array{0: int, 1: int}}> $resolved */
    $resolved = [];

    foreach ($sites as [$path, $startLine, $lineCount]) {
        $file = pr_file($path);

        if ($file === null) {
            continue;
        }

        $span = pr_span($file->lines, $startLine, $lineCount);

        if ($span === null) {
            continue;
        }

        $resolved[] = ['file' => $file, 'span' => $span];
    }

    if (count($resolved) < 2) {
        return ['rule8' => false, 'rule9' => null, 'pairs' => 0, 'inScope' => 0];
    }

    // Rule 8 — every site, or the finding stands.
    $rule8 = true;

    foreach ($resolved as $site) {
        if (!pr_rule8($site['file']->facts, $site['span'][0], $site['span'][1])) {
            $rule8 = false;

            break;
        }
    }

    // Rule 9 — the *largest* overlap over the site pairs, and only when every
    // pair is in scope. The most generous pair decides, so silencing needs all
    // of them below the floor.
    $pairs   = 0;
    $inScope = 0;
    $best    = null;
    $count   = count($resolved);

    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $pairs++;

            $overlap = pr_rule9_overlap(
                $resolved[$i]['file']->facts,
                $resolved[$i]['file']->shape,
                $resolved[$i]['span'][0],
                $resolved[$i]['span'][1],
                $resolved[$j]['file']->facts,
                $resolved[$j]['file']->shape,
                $resolved[$j]['span'][0],
                $resolved[$j]['span'][1],
            );

            if ($overlap === null) {
                continue;
            }

            $inScope++;
            $best = $best === null ? $overlap : max($best, $overlap);
        }
    }

    return [
        'rule8'   => $rule8,
        'rule9'   => $pairs > 0 && $inScope === $pairs ? $best : null,
        'pairs'   => $pairs,
        'inScope' => $inScope,
    ];
}

// ---------------------------------------------------------------------------
// Reading the recorded evidence
// ---------------------------------------------------------------------------

/**
 * The engine attribution kept beside the worksheet.
 *
 * The manifest, the worksheets and the site table are read through `lib.php`,
 * which is where the format lives; this column is this script's alone.
 *
 * @return array<string, string>
 */
function pr_read_key(string $path): array
{
    $rows = [];

    foreach (bcb_tsv_rows($path) as $columns) {
        $rows[$columns[0]] = $columns[1] ?? '';
    }

    return $rows;
}

/**
 * One rated finding, with both raters' verdicts and both rules' verdicts on it.
 */
final class PrFinding
{
    /**
     * @param string  $consensus 'Y', 'N', or '' where the two raters disagree
     * @param bool    $scorable  false when the evidence is incomplete; such a
     *                           finding is held as surviving, which is the
     *                           conservative direction
     * @param ?float  $rule9     the pair overlap, or null where rule 9 has
     *                           nothing to say about this finding
     * @param int     $pairs     site pairs this finding has
     * @param int     $inScope   of those, how many are two statement-free
     *                           tables — rule 9 speaks only when all of them are
     */
    public function __construct(
        public string $id,
        public string $a,
        public string $b,
        public string $consensus,
        public string $engines,
        public bool $scorable,
        public bool $rule8,
        public ?float $rule9,
        public string $why,
        public int $pairs = 0,
        public int $inScope = 0,
    ) {}

    public function verdict(string $rater): string
    {
        return $rater === 'a' ? $this->a : $this->b;
    }
}

/**
 * One worksheet, resolved into the shape both rules are scored over.
 *
 * @param  array<string, string> $pinned
 * @return list<PrFinding>
 */
function pr_load_sheet(string $corpus, array $pinned, string $raterA, string $raterB, string $key, string $sites): array
{
    $verdictsA = bcb_read_worksheet($raterA);
    $verdictsB = bcb_read_worksheet($raterB);
    $engines   = pr_read_key($key);
    $siteTable = bcb_read_site_table($sites);

    $rows = [];

    foreach ($verdictsA as $id => $rowA) {
        $a         = $rowA['verdict'];
        $b         = $verdictsB[$id]['verdict'] ?? '';
        $consensus = ($a !== '' && $a === $b) ? $a : '';

        $resolved = $siteTable[$id] ?? [];
        $why      = '';
        $absolute = [];

        foreach ($resolved as [$relative, $startLine, $lineCount]) {
            if (!isset($pinned[$relative])) {
                $why = 'a site is outside the pinned manifest';

                break;
            }

            $file = $corpus . '/' . $relative;

            if (!is_file($file) || hash('sha256', (string) file_get_contents($file)) !== $pinned[$relative]) {
                $why = 'a site has drifted from the pin';

                break;
            }

            $absolute[] = [$file, $startLine, $lineCount];
        }

        // Every site or none: a finding whose evidence is partly missing cannot
        // be shown to be silenced, and is held as surviving.
        if ($why === '' && count($resolved) !== $rowA['sites']) {
            $why = sprintf('%d of %d sites relocated', count($resolved), $rowA['sites']);
        }

        $scorable = $why === '' && count($absolute) >= 2;

        if (!$scorable && $why === '') {
            $why = 'fewer than two sites';
        }

        $applied = $scorable ? pr_apply($absolute) : ['rule8' => false, 'rule9' => null, 'pairs' => 0, 'inScope' => 0];

        $rows[] = new PrFinding(
            $id,
            $a,
            $b,
            $consensus,
            $engines[$id] ?? '',
            $scorable,
            $applied['rule8'],
            $applied['rule9'],
            $why,
            $applied['pairs'],
            $applied['inScope'],
        );
    }

    return $rows;
}

// ---------------------------------------------------------------------------
// Reporting
// ---------------------------------------------------------------------------

function pr_ratio(int $yes, int $no): string
{
    $total = $yes + $no;

    return $total === 0 ? '  n/a' : sprintf('%.3f', $yes / $total);
}

/**
 * Wilson score interval, as the audit instrument reports it.
 *
 * @return array{0: float, 1: float}
 */
function pr_wilson(int $successes, int $trials): array
{
    if ($trials === 0) {
        return [0.0, 0.0];
    }

    $z      = 1.959963984540054;
    $p      = $successes / $trials;
    $centre = ($p + ($z ** 2) / (2 * $trials)) / (1 + ($z ** 2) / $trials);
    $spread = $z / (1 + ($z ** 2) / $trials)
        * sqrt($p * (1 - $p) / $trials + ($z ** 2) / (4 * $trials ** 2));

    return [max(0.0, $centre - $spread), min(1.0, $centre + $spread)];
}

/**
 * Precision for one rater over the rows a filter leaves standing.
 *
 * @param  list<PrFinding>              $rows
 * @param  callable(PrFinding): bool    $silenced
 * @return array{y: int, n: int}
 */
function pr_precision(array $rows, string $rater, ?string $engine, callable $silenced): array
{
    $y = 0;
    $n = 0;

    foreach ($rows as $row) {
        if ($engine !== null && !str_contains($row->engines, $engine)) {
            continue;
        }

        $verdict = $row->verdict($rater);

        if ($verdict !== 'Y' && $verdict !== 'N') {
            continue;
        }

        if ($silenced($row)) {
            continue;
        }

        if ($verdict === 'Y') {
            $y++;
        } else {
            $n++;
        }
    }

    return ['y' => $y, 'n' => $n];
}

// ---------------------------------------------------------------------------
// Mode: measure — derive the floor, then score both worksheets
// ---------------------------------------------------------------------------

/**
 * @param  list<string> $arguments
 */
function pr_measure(array $arguments): int
{
    $corpus         = '';
    $pin            = '';
    $counterfactual = null;
    /** @var list<array{0: string, 1: string, 2: string, 3: string, 4: string}> $sheets */
    $sheets = [];

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--corpus=')) {
            $corpus = substr($argument, strlen('--corpus='));
        } elseif (str_starts_with($argument, '--pin=')) {
            $pin = substr($argument, strlen('--pin='));
        } elseif (str_starts_with($argument, '--counterfactual=')) {
            $counterfactual = (float) substr($argument, strlen('--counterfactual='));
        } elseif (str_starts_with($argument, '--sheet=')) {
            $parts = explode(':', substr($argument, strlen('--sheet=')));

            if (count($parts) !== 5) {
                fwrite(STDERR, "--sheet needs <label>:<raterA>:<raterB>:<key>:<sites>\n");

                return 1;
            }

            $sheets[] = [$parts[0], $parts[1], $parts[2], $parts[3], $parts[4]];
        }
    }

    if ($corpus === '' || $pin === '' || $sheets === []) {
        fwrite(STDERR, "measure needs --corpus, --pin and at least one --sheet\n");

        return 1;
    }

    $pinned = bcb_read_pin($pin);
    printf("pin: %d files\n\n", count($pinned));

    /** @var array<string, list<PrFinding>> $loaded */
    $loaded = [];

    foreach ($sheets as [$label, $a, $b, $key, $sites]) {
        $loaded[$label] = pr_load_sheet($corpus, $pinned, $a, $b, $key, $sites);
    }

    // --- the derivation, over the consensus labels of both sheets ----------
    echo "=== Rule 9 — the floor, derived from the consensus labels ===\n\n";

    /** @var array<string, list<array{0: string, 1: string, 2: float}>> $observed label => [id, consensus, overlap] */
    $observed = [];
    $maxN     = null;
    $minY     = null;

    foreach ($loaded as $label => $rows) {
        $observed[$label] = [];

        foreach ($rows as $row) {
            if ($row->rule9 === null) {
                continue;
            }

            // Findings the two raters disagreed on are listed — "recorded in
            // full" is the instruction — and marked, and take no part in the
            // derivation: a contested label is not a label.
            $observed[$label][] = [$row->id, $row->consensus === '' ? '-' : $row->consensus, $row->rule9];

            if ($row->consensus === '') {
                continue;
            }

            if ($row->consensus === 'N') {
                $maxN = $maxN === null ? $row->rule9 : max($maxN, $row->rule9);
            } else {
                $minY = $minY === null ? $row->rule9 : min($minY, $row->rule9);
            }
        }
    }

    foreach ($observed as $label => $rows) {
        usort($rows, static fn(array $x, array $y): int => $x[2] <=> $y[2]);

        printf(
            "  %s — %d findings in rule 9's scope (%d consensus-labelled, %d contested and excluded)\n",
            $label,
            count($rows),
            count(array_filter($rows, static fn(array $r): bool => $r[1] !== '-')),
            count(array_filter($rows, static fn(array $r): bool => $r[1] === '-')),
        );

        foreach ($rows as [$id, $consensus, $overlap]) {
            printf("    %s   consensus %s   overlap %.4f\n", $id, $consensus, $overlap);
        }

        if ($rows === []) {
            echo "    (none)\n";
        }

        echo "\n";
    }

    // How close the label set came to holding a positive example at all: a
    // floor cannot be derived from one side of a separation, and this is where
    // the other side would have had to come from.
    echo "  rule 9's scope, over consensus-labelled findings\n";
    printf("    %-3s %-9s %-9s %-9s %s\n", '', 'findings', 'no pair', 'some pair', 'every pair (= in scope)');

    foreach ($loaded as $label => $rows) {
        foreach (['Y', 'N'] as $consensus) {
            $labelled = array_values(array_filter($rows, static fn(PrFinding $r): bool => $r->consensus === $consensus && $r->scorable));
            $none     = array_filter($labelled, static fn(PrFinding $r): bool => $r->inScope === 0);
            $some     = array_filter($labelled, static fn(PrFinding $r): bool => $r->inScope > 0 && $r->inScope < $r->pairs);
            $all      = array_filter($labelled, static fn(PrFinding $r): bool => $r->pairs > 0 && $r->inScope === $r->pairs);

            printf(
                "    %s %-1s %6d %10d %9d %13d\n",
                $label,
                $consensus,
                count($labelled),
                count($none),
                count($some),
                count($all),
            );
        }
    }

    echo "\n";

    $floor = null;

    printf(
        "  largest overlap among consensus-N in scope   %s\n",
        $maxN === null ? '(none observed)' : sprintf('%.4f', $maxN),
    );
    printf(
        "  smallest overlap among consensus-Y in scope  %s\n",
        $minY === null ? '(none observed)' : sprintf('%.4f', $minY),
    );

    if ($maxN === null || $minY === null) {
        printf("\n  NO FLOOR: one side of the separation has no observation. Rule 9 is not derivable.\n\n");
    } else {
        $candidate = floor($maxN * 100) / 100 + 0.01;

        if ($candidate <= $minY) {
            $floor = $candidate;
            printf("\n  separation [%.4f, %.4f]  ->  floor = %.2f\n\n", $maxN, $minY, $floor);
        } else {
            printf(
                "\n  NO SEPARATION: the smallest two-decimal value above the N side (%.2f) is above the Y side's"
                . " minimum (%.4f). Rule 9 wants a threshold these labels cannot derive.\n\n",
                $candidate,
                $minY,
            );
        }
    }

    // A floor the derivation did **not** produce, applied only to say what the
    // statistic would do if an anchor for it existed. It decides nothing: the
    // verdict on rule 9 is settled by whether the labels can derive a floor,
    // and this is reported so the residual can be characterised rather than
    // guessed at.
    if ($floor === null && $counterfactual !== null) {
        printf(
            "  COUNTERFACTUAL: the report below also applies a floor of %.2f, which is NOT derived —\n"
            . "  it is the hug-the-N-side value the rule would have taken had the Y side been observed.\n"
            . "  It does not change the verdict on rule 9.\n\n",
            $counterfactual,
        );
        $floor = $counterfactual;
    }

    // --- what each rule silences, per sheet --------------------------------
    $silencer8 = static fn(PrFinding $row): bool => $row->rule8;
    $silencer9 = static fn(PrFinding $row): bool => $floor !== null && $row->rule9 !== null && $row->rule9 < $floor;
    $silencerB = static fn(PrFinding $row): bool => $silencer8($row) || $silencer9($row);

    /** @var array<string, true> $lost */
    $lost = [];

    foreach ($loaded as $label => $rows) {
        printf("=== %s — what the filters silence ===\n\n", $label);
        printf("  %d findings; %d scorable, %d held as surviving\n\n", count($rows), count(array_filter($rows, static fn(PrFinding $r): bool => $r->scorable)), count(array_filter($rows, static fn(PrFinding $r): bool => !$r->scorable)));

        foreach (array_filter($rows, static fn(PrFinding $r): bool => !$r->scorable) as $row) {
            printf("    held  %s  A=%s B=%s  %s\n", $row->id, $row->a, $row->b, $row->why);
        }

        echo "\n";

        foreach (['rule 8' => $silencer8, 'rule 9' => $silencer9] as $name => $silencer) {
            $hit = array_values(array_filter($rows, $silencer));

            printf("  %s silences %d finding(s)\n", $name, count($hit));

            foreach ($hit as $row) {
                printf(
                    "    %s   A=%s B=%s consensus=%-1s engines=%-20s%s\n",
                    $row->id,
                    $row->a,
                    $row->b,
                    $row->consensus === '' ? '-' : $row->consensus,
                    $row->engines,
                    $row->rule9 === null ? '' : sprintf('overlap %.4f', $row->rule9),
                );

                if ($row->consensus === 'Y') {
                    $lost[$label . ' ' . $row->id] = true;
                }
            }

            echo "\n";
        }

        // --- projected precision -------------------------------------------
        printf("  projected precision (a filtered finding is not reported, so it leaves the pool)\n\n");

        foreach ([[null, 'all engines'], ['unified', 'unified only']] as [$engine, $title]) {
            printf("    %s\n", $title);

            foreach (['a' => 'rater A', 'b' => 'rater B'] as $rater => $raterTitle) {
                $before = pr_precision($rows, $rater, $engine, static fn(PrFinding $row): bool => false);
                $after8 = pr_precision($rows, $rater, $engine, $silencer8);
                $after9 = pr_precision($rows, $rater, $engine, $silencer9);
                $both   = pr_precision($rows, $rater, $engine, $silencerB);

                [$low, $high] = pr_wilson($both['y'], $both['y'] + $both['n']);

                printf(
                    "      %s   before Y %2d N %2d %s | rule 8 %s | rule 9 %s | both %s  Wilson [%.3f, %.3f]\n",
                    $raterTitle,
                    $before['y'],
                    $before['n'],
                    pr_ratio($before['y'], $before['n']),
                    pr_ratio($after8['y'], $after8['n']),
                    pr_ratio($after9['y'], $after9['n']),
                    pr_ratio($both['y'], $both['n']),
                    $low,
                    $high,
                );
            }

            echo "\n";
        }
    }

    printf("=== the failure condition ===\n\n");
    printf("  consensus-Y findings silenced on either sheet: %d%s\n\n", count($lost), $lost === [] ? '' : '   ' . implode(', ', array_keys($lost)));

    return 0;
}

// ---------------------------------------------------------------------------
// Mode: explain — why rule 8 declines a span
// ---------------------------------------------------------------------------

/**
 * The first reason rule 8 refuses a span, in words. A rule that reaches one
 * finding of a family of seven owes an account of the other six, and an account
 * a reader cannot reproduce is an assertion.
 */
function pr_rule8_reason(PrFileFacts $facts, int $start, int $length): string
{
    if ($length <= 0 || !isset($facts->stmtOf[$start], $facts->stmtOf[$start + $length - 1])) {
        return 'the span is outside the file';
    }

    /** @var array<int, true> $ids */
    $ids = [];

    for ($i = $start; $i < $start + $length; $i++) {
        $ids[$facts->stmtOf[$i]] = true;
    }

    if (count($ids) < 2) {
        return 'one statement — nothing to be pairwise about';
    }

    $notCall = [];

    foreach (array_keys($ids) as $id) {
        if (!$facts->statements[$id]->singleCall) {
            $notCall[] = $id;
        }
    }

    if ($notCall !== []) {
        return sprintf('%d of %d statements are not single call expressions', count($notCall), count($ids));
    }

    /** @var array<string, int> $seen variable => how many statements name it */
    $seen = [];

    foreach (array_keys($ids) as $id) {
        foreach (array_keys($facts->statements[$id]->vars) as $variable) {
            $seen[$variable] = ($seen[$variable] ?? 0) + 1;
        }
    }

    $shared = array_keys(array_filter($seen, static fn(int $n): bool => $n > 1));

    if ($shared !== []) {
        return 'statements share ' . implode(', ', $shared);
    }

    return 'rule 8 fires';
}

/**
 * @param  list<string> $arguments
 */
function pr_explain(array $arguments): int
{
    $corpus = '';
    $pin    = '';
    $sites  = '';

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--corpus=')) {
            $corpus = substr($argument, strlen('--corpus='));
        } elseif (str_starts_with($argument, '--pin=')) {
            $pin = substr($argument, strlen('--pin='));
        } elseif (str_starts_with($argument, '--sites=')) {
            $sites = substr($argument, strlen('--sites='));
        }
    }

    if ($corpus === '' || $pin === '' || $sites === '') {
        fwrite(STDERR, "explain needs --corpus, --pin and --sites\n");

        return 1;
    }

    $pinned = bcb_read_pin($pin);

    foreach (bcb_read_site_table($sites) as $id => $rows) {
        $lines = [];

        foreach ($rows as [$relative, $startLine, $lineCount]) {
            if (!isset($pinned[$relative])) {
                continue;
            }

            $file = pr_file($corpus . '/' . $relative);

            if ($file === null) {
                continue;
            }

            $span = pr_span($file->lines, $startLine, $lineCount);

            if ($span === null) {
                continue;
            }

            $statements = [];

            for ($i = $span[0]; $i < $span[0] + $span[1]; $i++) {
                $statements[$file->facts->stmtOf[$i]] = true;
            }

            $lines[] = sprintf(
                '    %3d statements over %4d tokens — %s',
                count($statements),
                $span[1],
                pr_rule8_reason($file->facts, $span[0], $span[1]),
            );
        }

        if ($lines === []) {
            continue;
        }

        printf("  %s\n%s\n", $id, implode("\n", $lines));
    }

    return 0;
}

// ---------------------------------------------------------------------------
// Mode: corpus — the public-corpus regressions
// ---------------------------------------------------------------------------

/**
 * Run the shipped unified engine over a public corpus and list, by name, every
 * finding the two filters would remove. A delta is not a report: the standing
 * rule is that what a filter silences is opened and named, never summarised as
 * a count.
 *
 * @param  list<string> $arguments
 */
function pr_corpus(array $arguments): int
{
    $dir       = '';
    $floor     = null;
    $minTokens = 100;
    $minLines  = 5;

    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--floor=')) {
            $floor = (float) substr($argument, strlen('--floor='));
        } elseif (str_starts_with($argument, '--min-tokens=')) {
            $minTokens = (int) substr($argument, strlen('--min-tokens='));
        } elseif (str_starts_with($argument, '--min-lines=')) {
            $minLines = (int) substr($argument, strlen('--min-lines='));
        } elseif (!str_starts_with($argument, '--')) {
            $dir = $argument;
        }
    }

    if ($dir === '') {
        fwrite(STDERR, "corpus needs a directory\n");

        return 1;
    }

    if (!is_dir($dir)) {
        $dir = __DIR__ . '/corpus/' . $dir;
    }

    $files = bcb_gate_files([$dir]);
    $map   = bcb_detect($files, ['algorithm' => 'unified', 'minTokens' => $minTokens, 'minLines' => $minLines]);
    $clones = $map->clones();

    printf("%s — %d files, %d clones at minTokens=%d minLines=%d\n\n", $dir, count($files), count($clones), $minTokens, $minLines);

    $removed8 = [];
    $removed9 = [];
    /** @var array<string, float> $overlaps clone id => overlap, for the named regressions */
    $overlaps = [];

    foreach ($clones as $clone) {
        $sites = [];

        foreach ($clone->files() as $file) {
            $sites[] = [$file->name, $file->startLine, $clone->numberOfLines()];
        }

        $applied = pr_apply($sites);

        $where = [];

        foreach ($sites as [$name, $startLine]) {
            $where[] = pr_relative($name, $dir) . ':' . $startLine;
        }

        sort($where);
        $label = implode(' <-> ', $where) . sprintf('  %d lines', $clone->numberOfLines());

        if ($applied['rule9'] !== null) {
            $overlaps[$label] = $applied['rule9'];
        }

        if ($applied['rule8']) {
            $removed8[] = $label;
        }

        if ($floor !== null && $applied['rule9'] !== null && $applied['rule9'] < $floor) {
            $removed9[] = $label . sprintf('  overlap %.4f', $applied['rule9']);
        }
    }

    sort($removed8);
    sort($removed9);

    printf("  rule 8 removes %d finding(s):\n", count($removed8));

    foreach ($removed8 as $line) {
        printf("    %s\n", $line);
    }

    printf("\n  rule 9 removes %d finding(s)%s:\n", count($removed9), $floor === null ? ' (no floor given — nothing applied)' : sprintf(' at floor %.2f', $floor));

    foreach ($removed9 as $line) {
        printf("    %s\n", $line);
    }

    // The named regressions, where the corpus carries them.
    $log = [];
    echo "\n";

    $php7php8 = null;

    foreach ($overlaps as $label => $overlap) {
        if (str_contains($label, 'Php7.php') && str_contains($label, 'Php8.php')) {
            $php7php8 = $php7php8 === null ? $overlap : max($php7php8, $overlap);
        }
    }

    $php7php8Reported = false;
    $php7php8Raw      = null;

    foreach ($clones as $clone) {
        $seven = null;
        $eight = null;

        foreach ($clone->files() as $file) {
            if (str_ends_with($file->name, '/Php7.php')) {
                $seven = $file;
            } elseif (str_ends_with($file->name, '/Php8.php')) {
                $eight = $file;
            }
        }

        if ($seven === null || $eight === null) {
            continue;
        }

        $php7php8Reported = true;

        // What the statistic says about the flagship true positive, whether or
        // not the scope test lets rule 9 speak: a regression that survives by
        // falling outside a rule is a different fact from one that survives on
        // its evidence, and both belong in the record.
        $fileSeven = pr_file($seven->name);
        $fileEight = pr_file($eight->name);

        if ($fileSeven === null || $fileEight === null) {
            continue;
        }

        $spanSeven = pr_span($fileSeven->lines, $seven->startLine, $clone->numberOfLines());
        $spanEight = pr_span($fileEight->lines, $eight->startLine, $clone->numberOfLines());

        if ($spanSeven === null || $spanEight === null) {
            continue;
        }

        $raw = pr_overlap_ignoring_scope(
            $fileSeven,
            $spanSeven[0],
            $spanSeven[1],
            $fileEight,
            $spanEight[0],
            $spanEight[1],
        );

        if ($raw !== null) {
            $php7php8Raw = $php7php8Raw === null ? $raw : max($php7php8Raw, $raw);
        }
    }

    if ($php7php8Reported) {
        bcb_check(
            $log,
            $floor === null || $php7php8 === null || $php7php8 >= $floor,
            'Php7.php <-> Php8.php survives rule 9',
            $php7php8 === null
                ? sprintf('out of rule 9 scope, so never silenced; its literals overlap %s anyway', $php7php8Raw === null ? 'n/a' : sprintf('%.4f', $php7php8Raw))
                : sprintf('overlap %.4f', $php7php8),
        );
    }

    $unicodePair = false;

    foreach ($clones as $clone) {
        $names = array_map(static fn(object $file): string => (string) $file->name, array_values($clone->files()));
        $wide  = array_filter($names, static fn(string $n): bool => str_contains($n, 'wcswidth_table_wide'));
        $zero  = array_filter($names, static fn(string $n): bool => str_contains($n, 'wcswidth_table_zero'));

        if ($wide !== [] && $zero !== []) {
            $unicodePair = true;
        }
    }

    if (str_contains($dir, 'symfony-string')) {
        bcb_check($log, !$unicodePair, 'the symfony Unicode table pair stays silent', $unicodePair ? 'reported' : 'not reported');
    }

    if ($log === []) {
        return 0;
    }

    return bcb_check_summary($log, 'corpus regressions');
}

function pr_relative(string $path, string $root): string
{
    $real = realpath($root);

    if ($real !== false && str_starts_with($path, $real . '/')) {
        return substr($path, strlen($real) + 1);
    }

    return $path;
}

// ---------------------------------------------------------------------------
// Mode: self-test — including the paired negative each rule ships with
// ---------------------------------------------------------------------------

/**
 * The fixtures, written before any measurement was taken.
 *
 * Each rule gets a **pair** whose two halves have the same token layout and
 * differ only in the thing the rule names — the shape ruling 5's
 * `RegionStructureTest` fixtures fixed for this project — plus one realistic
 * negative apiece, because a minimal pair proves the axis and a real one proves
 * the rule reaches the code it was written for.
 *
 * @return array<string, string>
 */
function pr_fixtures(): array
{
    return [
        // Rule 8, positive: three registrations against a static receiver.
        // Nothing is written, nothing is read twice, and the order of the three
        // lines carries no information.
        'config-static.php' => <<<'PHP'
            <?php

            Config::set('mail.host', 'smtp.example.com');
            Config::set('mail.port', 587);
            Config::set('mail.user', 'postmaster');
            PHP,

        // Rule 8, the paired negative: the same three lines, token for token,
        // through a receiver variable. Every statement now names `$config`, so
        // the three are dataflow-coupled through it and the span is kept.
        'config-receiver.php' => <<<'PHP'
            <?php

            $config->set('mail.host', 'smtp.example.com');
            $config->set('mail.port', 587);
            $config->set('mail.user', 'postmaster');
            PHP,

        // Rule 8, the realistic negative: a query builder, which is a procedure
        // written as a run of calls. Reordering it changes what it does.
        'builder.php' => <<<'PHP'
            <?php

            $query->select('id', 'name', 'status');
            $query->where('status', 'active');
            $query->orderBy('name', 'asc');
            PHP,

        // Rule 8, refused for a second reason: a closure is a statement, and a
        // statement is not a call expression.
        'closure.php' => <<<'PHP'
            <?php

            Route::get('/a', function () { return 1; });
            Route::get('/b', function () { return 2; });
            PHP,

        // Rule 9: one table, and a byte-identical copy of it in a second file —
        // a genuinely copied seeder. Overlap 1.
        'table-copy-a.php' => <<<'PHP'
            <?php

            return [
                ['code' => 'IT', 'name' => 'Italy', 'dial' => 39],
                ['code' => 'FR', 'name' => 'France', 'dial' => 33],
                ['code' => 'ES', 'name' => 'Spain', 'dial' => 34],
            ];
            PHP,

        'table-copy-b.php' => <<<'PHP'
            <?php

            return [
                ['code' => 'IT', 'name' => 'Italy', 'dial' => 39],
                ['code' => 'FR', 'name' => 'France', 'dial' => 33],
                ['code' => 'ES', 'name' => 'Spain', 'dial' => 34],
            ];
            PHP,

        // Rule 9, the paired negative's other half: the same table shape, token
        // for token, holding different data.
        'table-other.php' => <<<'PHP'
            <?php

            return [
                ['code' => 'JP', 'name' => 'Japan', 'dial' => 81],
                ['code' => 'KR', 'name' => 'Korea', 'dial' => 82],
                ['code' => 'CN', 'name' => 'China', 'dial' => 86],
            ];
            PHP,

        // Rule 9, out of scope: ruling 5's own case. A closure inside the table
        // makes the frame logic-bearing, so the rule has nothing to say and the
        // finding is kept whatever its literals do.
        'table-logic.php' => <<<'PHP'
            <?php

            return [
                ['code' => 'JP', 'name' => 'Japan', 'dial' => function () { return 81; }],
                ['code' => 'KR', 'name' => 'Korea', 'dial' => function () { return 82; }],
                ['code' => 'CN', 'name' => 'China', 'dial' => function () { return 86; }],
            ];
            PHP,
    ];
}

function pr_self_test(): int
{
    $log = [];
    $dir = sys_get_temp_dir() . '/pr-self-test-' . getmypid();
    @mkdir($dir, 0o755, true);

    foreach (pr_fixtures() as $name => $source) {
        file_put_contents($dir . '/' . $name, $source . "\n");
    }

    echo "M5 pre-commitment rules — self-test\n\n";

    /** @param list<string> $names */
    $tokens = static function (string $name) use ($dir): int {
        $file = pr_file($dir . '/' . $name);

        return $file === null ? -1 : $file->facts->count;
    };

    /** Whole-file span, as a site tuple the measurement modes would build. */
    $whole = static function (string $name) use ($dir): array {
        return [$dir . '/' . $name, 1, 1000];
    };

    // --- rule 8 ---------------------------------------------------------
    $positive = pr_apply([$whole('config-static.php'), $whole('config-static.php')]);
    bcb_check($log, $positive['rule8'], 'rule 8 fires on order-independent configuration', 'Config::set × 3');

    $negative = pr_apply([$whole('config-receiver.php'), $whole('config-receiver.php')]);
    bcb_check($log, !$negative['rule8'], 'rule 8 keeps the paired negative — one shared receiver variable', '$config->set × 3');

    bcb_check(
        $log,
        $tokens('config-static.php') === $tokens('config-receiver.php'),
        'the rule 8 pair has equal token counts, so it differs only in the named thing',
        sprintf('%d vs %d tokens', $tokens('config-static.php'), $tokens('config-receiver.php')),
    );

    $builder = pr_apply([$whole('builder.php'), $whole('builder.php')]);
    bcb_check($log, !$builder['rule8'], 'rule 8 keeps a dataflow-coupled builder run', '$query->select/where/orderBy');

    $closure = pr_apply([$whole('closure.php'), $whole('closure.php')]);
    bcb_check($log, !$closure['rule8'], 'rule 8 keeps a span holding a closure', 'a closure body is statements');

    $oneStatement = pr_apply([[$dir . '/config-static.php', 3, 1], [$dir . '/config-static.php', 3, 1]]);
    bcb_check($log, !$oneStatement['rule8'], 'rule 8 declines a span of one statement', 'nothing to be pairwise about');

    // --- rule 9 ---------------------------------------------------------
    // The table rows are lines 4-6 of every fixture; the span has to be inside
    // the frame for the rule to be in scope at all.
    $rows = static fn(string $name): array => [$dir . '/' . $name, 4, 3];

    $copied = pr_apply([$rows('table-copy-a.php'), $rows('table-copy-b.php')]);
    bcb_check(
        $log,
        $copied['rule9'] !== null && abs($copied['rule9'] - 1.0) < 1e-9,
        'rule 9 gives a genuinely copied table pair overlap 1',
        $copied['rule9'] === null ? 'out of scope' : sprintf('%.4f', $copied['rule9']),
    );

    $unrelated = pr_apply([$rows('table-copy-a.php'), $rows('table-other.php')]);
    bcb_check(
        $log,
        $unrelated['rule9'] !== null && $unrelated['rule9'] < 0.5,
        'rule 9 separates two shape-matched tables holding different data',
        $unrelated['rule9'] === null ? 'out of scope' : sprintf('%.4f', $unrelated['rule9']),
    );

    bcb_check(
        $log,
        $tokens('table-copy-a.php') === $tokens('table-other.php'),
        'the rule 9 pair has equal token counts, so it differs only in its literals',
        sprintf('%d vs %d tokens', $tokens('table-copy-a.php'), $tokens('table-other.php')),
    );

    $logicTable = pr_apply([$rows('table-logic.php'), $rows('table-logic.php')]);
    bcb_check(
        $log,
        $logicTable['rule9'] === null,
        'rule 9 declines a logic-bearing table — ruling 5 decides that case, not this one',
        'a closure in a row makes the frame statement-bearing',
    );

    // --- the instrument can fail ----------------------------------------
    // The copied pair survives because of its overlap and not because the code
    // path is inert. The decision the measurement modes make, run at two floors
    // over the same finding: below its overlap it is kept, above it, silenced.
    $decide = static fn(?float $overlap, float $floor): bool => $overlap !== null && $overlap < $floor;

    bcb_check(
        $log,
        !$decide($copied['rule9'], 0.50) && $decide($copied['rule9'], 1.50),
        'the floor decides, not the code path — the copied pair is kept at 0.50 and silenced at 1.50',
        'a gate that cannot fail on demand is not a gate',
    );

    bcb_check(
        $log,
        $decide($unrelated['rule9'], 0.50) && !$decide($unrelated['rule9'], 0.10),
        'and the unrelated pair flips the other way — silenced at 0.50, kept at 0.10',
        sprintf('overlap %.4f', (float) $unrelated['rule9']),
    );

    // --- the span mapping -----------------------------------------------
    $file = pr_file($dir . '/table-copy-a.php');
    $span = $file === null ? null : pr_span($file->lines, 1, 1000);
    bcb_check(
        $log,
        $file !== null && $span !== null && $span[0] === 0 && $span[1] === $file->facts->count,
        'a whole-file span is exactly the encoder token count',
        $file === null || $span === null ? 'no span' : sprintf('%d tokens', $span[1]),
    );

    bcb_check(
        $log,
        $file !== null && count($file->facts->stmtOf) === $file->facts->count,
        'statement segmentation is total — every significant token belongs to one statement',
        $file === null ? '' : sprintf('%d of %d', count($file->facts->stmtOf), $file->facts->count),
    );

    // The facts layer this script reuses must agree with the encoder on the
    // numbering, or every span reported here is off by an offset.
    bcb_check(
        $log,
        $file !== null && $file->shape->tokenCount() === $file->facts->count,
        'RegionStructure and this script number tokens identically',
        $file === null ? '' : sprintf('%d vs %d', $file->shape->tokenCount(), $file->facts->count),
    );

    foreach (array_keys(pr_fixtures()) as $name) {
        @unlink($dir . '/' . $name);
    }

    @rmdir($dir);

    return bcb_check_summary($log, 'M5 pre-commitment rules self-test');
}

// ---------------------------------------------------------------------------

$arguments = array_slice(bcb_argv(), 1);
$mode      = $arguments[0] ?? '';

if ($mode === 'self-test') {
    exit(pr_self_test());
}

if ($mode === 'measure') {
    exit(pr_measure(array_slice($arguments, 1)));
}

if ($mode === 'corpus') {
    exit(pr_corpus(array_slice($arguments, 1)));
}

if ($mode === 'explain') {
    exit(pr_explain(array_slice($arguments, 1)));
}

fwrite(STDERR, "usage: php bench/precommit-rules.php self-test|measure|corpus|explain\n");

exit(1);
