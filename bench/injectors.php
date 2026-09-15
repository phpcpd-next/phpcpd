<?php

declare(strict_types=1);

/*
 * BCB-PHP clone-injection operators.
 *
 * Each operator is a pure function (string $code) => string $variant. They are
 * shared by the inject.php CLI and the in-process E2 runner so the two can never
 * diverge.
 *
 *   type1       — layout perturbation (whitespace/comments): a Type-1 clone.
 *   type2       — consistent variable rename: a Type-2 clone (survives --fuzzy).
 *   type3       — single-statement insertion: a Type-3 (gapped) clone.
 *   ssdiff      — same-shape, different-type: int/float hints rewritten to string.
 *   ssdiff_bool — same-shape, different-type: bool hints rewritten to int.
 *
 * Two further families live below, at *function* granularity and parameterized by
 * density, for measuring recall as a curve rather than a point:
 *
 *   gapped_{insert,delete,substitute}_d{1,2,3}
 *               — d statement edits per clone at controlled spacing, the operator
 *                 family any seed-and-extend detector is weakest against: each
 *                 edit fragments the exact runs a seed can be drawn from, so
 *                 recall falls as d rises and the shape of that fall is the
 *                 measurement.
 *   permute_{adjacent,distant}
 *               — statement swaps at a recorded distance: the Type-3 reordered
 *                 case, where a position-blind detector and a position-aware one
 *                 disagree about what they can localize.
 *
 * These report *what they did*, not just the mutated text: every edit is recorded
 * with its offsets and its exact before/after bytes, so bench/check-manifest.php
 * can re-derive each variant and compare it byte-for-byte against the file on
 * disk. A recall number computed against unverified mutations measures the
 * injector, not the detector.
 *
 * Variants are **parse-valid, not run-valid**. That is the contract, deliberately:
 * a detector reads tokens and never executes anything, so a deleted `return` or a
 * substituted assignment costs a measurement nothing — while restricting the
 * operators to semantically inert statements would move the edit sites away from
 * the evenly spaced positions the density rule specifies, and a density that
 * bends around the code it lands in is no longer a density. Every variant is
 * checked to parse (see bcb_parses()); none is ever run.
 *
 * Both ssdiff variants are NON-clones: a name-blind detector (--fuzzy) collapses
 * the swapped type identifiers and reports a false positive, while a type-aware one
 * (--type-anchored) keeps them distinct and rejects. The bool variant tests whether
 * the type-anchored precision gain generalizes across built-in type kinds, not just
 * int/float.
 *
 * Deliberately NOT included: nullable toggles (the `?` token survives both
 * normalizations, so --fuzzy and --type-anchored do not differ on them) and
 * class/interface substitutions (reliably identifying a class-typed hint needs
 * context the tokenizer alone does not provide — deferred).
 */

/** type1 — whitespace/comment perturbation. */
function bcb_op_type1(string $code): string
{
    $variant = preg_replace('/^(<\?php)/', '$1' . "\n// BCB-PHP Type-1 layout variant", $code) ?? $code;

    return str_replace(";\n", ";\n\n", $variant);
}

/** type2 — rename every variable consistently to $_v0, $_v1, … */
function bcb_op_type2(string $code): string
{
    $tokens  = token_get_all($code);
    $varMap  = [];
    $counter = 0;
    $out     = '';

    foreach ($tokens as $t) {
        if (is_array($t)) {
            if ($t[0] === T_VARIABLE) {
                $varMap[$t[1]] ??= '$_v' . $counter++;
                $out .= $varMap[$t[1]];
            } else {
                $out .= $t[1];
            }
        } else {
            $out .= $t;
        }
    }

    return $out;
}

/** type3 — insert one no-op statement after the first ';' inside a function body. */
function bcb_op_type3(string $code): string
{
    $inserted = false;

    return preg_replace_callback(
        '/(\bfunction\s+\w+\s*\([^)]*\)[^{]*\{[^}]*?)(;)/',
        static function (array $m) use (&$inserted): string {
            if ($inserted) {
                return $m[0];
            }

            $inserted = true;

            return $m[1] . $m[2] . "\n    \$_bcb_noop = null; // BCB-PHP Type-3 insertion";
        },
        $code,
    ) ?? $code;
}

/**
 * Swap built-in scalar type names wherever they appear as bare identifiers (type
 * hints, in practice). Same shape, different type: a name-blind detector collapses
 * the swapped identifiers and reports a false positive, while a type-aware one keeps
 * them distinct and rejects the pair.
 *
 * @param array<string, string> $map source type name => replacement
 */
function bcb_swap_types(string $code, array $map): string
{
    $out = '';

    foreach (token_get_all($code) as $t) {
        if (is_array($t)) {
            $out .= ($t[0] === T_STRING && isset($map[$t[1]])) ? $map[$t[1]] : $t[1];
        } else {
            $out .= $t;
        }
    }

    return $out;
}

/** ssdiff — int/float type hints rewritten to string (same shape, different type). */
function bcb_op_ssdiff(string $code): string
{
    return bcb_swap_types($code, ['int' => 'string', 'float' => 'string']);
}

/** ssdiff_bool — bool type hints rewritten to int (same shape, different type). */
function bcb_op_ssdiff_bool(string $code): string
{
    return bcb_swap_types($code, ['bool' => 'int']);
}

/**
 * True if any of the given built-in type names appears as a bare identifier. Used
 * to skip files that cannot produce a meaningful pair for a given ssdiff operator.
 *
 * @param list<string> $names
 */
function bcb_has_any_type(string $code, array $names): bool
{
    $set = array_flip($names);

    foreach (token_get_all($code) as $t) {
        if (is_array($t) && $t[0] === T_STRING && isset($set[$t[1]])) {
            return true;
        }
    }

    return false;
}

/** int/float hint present? (eligibility for ssdiff) */
function bcb_has_int_type(string $code): bool
{
    return bcb_has_any_type($code, ['int', 'float']);
}

/** bool hint present? (eligibility for ssdiff_bool) */
function bcb_has_bool_type(string $code): bool
{
    return bcb_has_any_type($code, ['bool']);
}

/**
 * The five E2 operators, unchanged.
 *
 * The density-parameterized families are deliberately NOT folded in here: E2's
 * published results were produced by iterating exactly this set, and silently
 * widening it would change what a re-run of an established experiment means. Use
 * {@see bcb_all_operator_names()} to reach everything.
 *
 * @return array<string, callable(string):string> operator name => function
 */
function bcb_operators(): array
{
    return [
        'type1'       => 'bcb_op_type1',
        'type2'       => 'bcb_op_type2',
        'type3'       => 'bcb_op_type3',
        'ssdiff'      => 'bcb_op_ssdiff',
        'ssdiff_bool' => 'bcb_op_ssdiff_bool',
    ];
}

// ---------------------------------------------------------------------------
// Statement segmentation — the unit the density-parameterized families edit
// ---------------------------------------------------------------------------

/**
 * The top-level statements of every function body in a source string, as byte
 * offsets into that string.
 *
 * Function granularity is what a recall curve needs: "one edit per clone" is
 * only a density if the clone is a fixed unit, and a whole file is not one. So
 * edits are placed among the statements of a single function, and the count of
 * those statements is the denominator the spacing is derived from.
 *
 * A statement runs from its first non-whitespace byte to the byte after the `;`
 * or `}` that closes it. Everything else here is one of the four cases where
 * that rule, applied naively, cuts a statement in half — each found by generating
 * variants over a few thousand real files and parsing every one of them:
 *
 *   - a `}` that closes a string interpolation (`"a{$x}b"`) is not a block
 *     ending at all, so braces are tracked as a stack of kinds rather than a
 *     depth counter; popping tells you which kind you just closed.
 *   - `;` and `}` inside parentheses end nothing: `for ($i = 0; $i < $n; $i++)`
 *     carries two semicolons at body level, and splitting there produces a
 *     variant that does not parse.
 *   - `}` followed by `;` is a closure, anonymous class or match assignment;
 *     the `;` is the real terminator.
 *   - `}` followed by `else` / `elseif` / `catch` / `finally`, or by `while`
 *     when the statement opened with `do`, continues into the next arm.
 *
 * @return list<array{name: string, statements: list<array{start: int, end: int}>}>
 *         functions in source order; those without a body are omitted
 */
function bcb_function_statements(string $code): array
{
    $tokens  = token_get_all($code);
    $n       = count($tokens);
    $texts   = [];
    $ids     = [];
    $offsets = [];
    $offset  = 0;

    // token_get_all() reports lines, not offsets, but the concatenation of every
    // token's text is the source byte-for-byte — so a running length is exact.
    foreach ($tokens as $t) {
        $text      = is_array($t) ? $t[1] : $t;
        $texts[]   = $text;
        $ids[]     = is_array($t) ? $t[0] : null;
        $offsets[] = $offset;
        $offset += strlen($text);
    }

    $opensBrace = [];

    foreach (['T_CURLY_OPEN', 'T_DOLLAR_OPEN_CURLY_BRACES'] as $name) {
        if (defined($name)) {
            $opensBrace[(int) constant($name)] = true;
        }
    }

    $skippable  = [T_WHITESPACE => true, T_COMMENT => true, T_DOC_COMMENT => true];
    $continuers = [T_ELSE => true, T_ELSEIF => true, T_CATCH => true, T_FINALLY => true];

    /** Index of the next token that is neither whitespace nor a comment. */
    $nextSignificant = static function (int $from) use ($ids, $n, $skippable): int {
        for ($i = $from; $i < $n; $i++) {
            if ($ids[$i] === null || !isset($skippable[$ids[$i]])) {
                return $i;
            }
        }

        return $n;
    };

    /** Index of the previous token that is neither whitespace nor a comment, or -1. */
    $previousSignificant = static function (int $from) use ($ids, $skippable): int {
        for ($i = $from; $i >= 0; $i--) {
            if ($ids[$i] === null || !isset($skippable[$ids[$i]])) {
                return $i;
            }
        }

        return -1;
    };

    // A `{` right after one of these opens a *name* expression — `$this->{$p}`,
    // `Foo::{$m}()`, `${$name}` — not a block. It reaches token_get_all() as a
    // plain '{' character, indistinguishable from a block opener except by what
    // precedes it, and mistaking one for a block ends a statement mid-expression.
    $nameBraceAfter = [T_OBJECT_OPERATOR => true, T_DOUBLE_COLON => true];

    if (defined('T_NULLSAFE_OBJECT_OPERATOR')) {
        $nameBraceAfter[(int) constant('T_NULLSAFE_OBJECT_OPERATOR')] = true;
    }

    $functions = [];

    for ($i = 0; $i < $n; $i++) {
        if ($ids[$i] !== T_FUNCTION) {
            continue;
        }

        $named = $nextSignificant($i + 1);
        $name  = ($named < $n && $ids[$named] === T_STRING) ? $texts[$named] : '{closure}';

        // Walk to the body '{', bailing at ';' (abstract or interface method).
        $body = null;

        for ($j = $i + 1; $j < $n; $j++) {
            if ($texts[$j] === '{' && $ids[$j] === null) {
                $body = $j;

                break;
            }

            if ($texts[$j] === ';' && $ids[$j] === null) {
                break;
            }
        }

        if ($body === null) {
            continue;
        }

        // A stack of what each open brace was, not just how many are open: the
        // `}` of "{$x}" closes an interpolation and ends no statement, while the
        // `}` of a nested block at body level ends one. A counter cannot tell
        // those apart, and conflating them splits interpolated strings in half.
        $braces     = ['block'];
        $enclosures = 0;
        $statements = [];
        $start      = null;
        $startsDo   = false;

        for ($k = $body + 1; $k < $n; $k++) {
            $id   = $ids[$k];
            $text = $texts[$k];

            if ($start === null && ($id === null || !isset($skippable[$id]))) {
                $start    = $offsets[$k];
                $startsDo = $id === T_DO;
            }

            // Parentheses and brackets, because nothing inside either ends a
            // body-level statement: `for ($i = 0; $i < $n; $i++)` carries two
            // semicolons, and an array literal of closures — `[1 => fn() {...},
            // 2 => fn() {...}]` — carries a `}` before every comma. Attributes
            // open with a single `#[` token whose `]` is an ordinary character,
            // so `#[` has to count as an opener or the depth goes negative.
            if ($id === null && ($text === '(' || $text === '[')) {
                $enclosures++;

                continue;
            }

            if ($id === null && ($text === ')' || $text === ']')) {
                $enclosures--;

                continue;
            }

            if ($id === T_ATTRIBUTE) {
                $enclosures++;

                continue;
            }

            if ($id !== null && isset($opensBrace[$id])) {
                $braces[] = 'interpolation';

                continue;
            }

            if ($text === '{' && $id === null) {
                $previous = $previousSignificant($k - 1);
                $isName   = $previous >= 0 && (
                    ($ids[$previous] !== null && isset($nameBraceAfter[$ids[$previous]]))
                    || ($ids[$previous] === null && $texts[$previous] === '$')
                );

                $braces[] = $isName ? 'name' : 'block';

                continue;
            }

            if ($text === '}' && $id === null) {
                $closed = array_pop($braces);

                if ($braces === []) {
                    break; // end of the function body
                }

                if ($closed !== 'block') {
                    continue; // the end of an interpolation, inside a statement
                }

                if (count($braces) === 1 && $enclosures === 0 && $start !== null) {
                    $after  = $nextSignificant($k + 1);
                    $nextId = $after < $n ? $ids[$after] : null;

                    if ($nextId === null && $after < $n && $texts[$after] === ';') {
                        continue; // a closure, anonymous class or match; the ';' ends it
                    }

                    if ($nextId !== null && (isset($continuers[$nextId]) || ($nextId === T_WHILE && $startsDo))) {
                        continue; // the statement continues into its next arm
                    }

                    $statements[] = ['start' => $start, 'end' => $offsets[$k] + 1];
                    $start        = null;
                    $startsDo     = false;
                }

                continue;
            }

            if ($text === ';' && $id === null && count($braces) === 1 && $enclosures === 0 && $start !== null) {
                $statements[] = ['start' => $start, 'end' => $offsets[$k] + 1];
                $start        = null;
                $startsDo     = false;
            }
        }

        $functions[] = ['name' => $name, 'statements' => $statements];
    }

    return $functions;
}

/**
 * The function an operator will edit: the one with the most top-level
 * statements, earliest on a tie.
 *
 * Biggest-first because the density families need room — d edits at controlled
 * spacing require interior, distinct positions — and earliest-on-a-tie because
 * the choice has to be a rule, not an accident of iteration order, or a variant
 * stops being reproducible from its manifest.
 *
 * @return array{name: string, statements: list<array{start: int, end: int}>}|null
 */
function bcb_target_function(string $code, int $minStatements): ?array
{
    $best = null;

    foreach (bcb_function_statements($code) as $function) {
        if (count($function['statements']) < $minStatements) {
            continue;
        }

        if ($best === null || count($function['statements']) > count($best['statements'])) {
            $best = $function;
        }
    }

    return $best;
}

/**
 * The d statement indices an edit density lands on, evenly spaced and interior.
 *
 * Interior matters: index 0 and index N-1 are left alone so every gapped variant
 * keeps a matching head and tail, which is what lets a seeded detector find an
 * anchor at all. A family that also probes the edges is a separate fixture, not
 * a density.
 *
 * @return list<int> exactly $edits distinct ascending indices, or [] if N is too small
 */
function bcb_edit_positions(int $statementCount, int $edits): array
{
    if ($edits < 1 || $statementCount < $edits + 2) {
        return [];
    }

    $positions = [];

    for ($i = 1; $i <= $edits; $i++) {
        $positions[] = (int) round($i * ($statementCount - 1) / ($edits + 1));
    }

    $positions = array_values(array_unique($positions));

    return count($positions) === $edits ? $positions : [];
}

// ---------------------------------------------------------------------------
// Edit application — every mutation is a byte-range replacement, and says so
// ---------------------------------------------------------------------------

/**
 * The horizontal whitespace between the start of $offset's line and $offset.
 * Inserted statements copy it, so a variant reads like code someone wrote rather
 * than like something a script did to code someone wrote.
 */
function bcb_line_indent(string $code, int $offset): string
{
    $lineStart = strrpos(substr($code, 0, $offset), "\n");
    $lineStart = $lineStart === false ? 0 : $lineStart + 1;
    $prefix    = substr($code, $lineStart, $offset - $lineStart);

    return trim($prefix) === '' ? $prefix : '';
}

/**
 * The byte range a statement deletion should remove: the statement, the
 * indentation in front of it when nothing else shares its line, and the newline
 * behind it. Deleting the bare span would leave a blank indented line, which is
 * a second, unrecorded difference between the two copies.
 *
 * @return array{0: int, 1: int} start (inclusive), end (exclusive)
 */
function bcb_delete_span(string $code, int $start, int $end): array
{
    $lineStart = strrpos(substr($code, 0, $start), "\n");
    $lineStart = $lineStart === false ? 0 : $lineStart + 1;

    if (trim(substr($code, $lineStart, $start - $lineStart)) === '') {
        $start = $lineStart;
    }

    $length = strlen($code);

    while ($end < $length && ($code[$end] === ' ' || $code[$end] === "\t")) {
        $end++;
    }

    if ($end < $length && $code[$end] === "\n") {
        $end++;
    }

    return [$start, $end];
}

/**
 * Apply a set of non-overlapping byte-range replacements and report where each
 * one landed in the result.
 *
 * Applied last-first so an earlier edit's offsets are never invalidated by a
 * later one, and recorded first-last so the manifest reads in source order. The
 * variant offset is derived arithmetically rather than searched for: a search
 * would find the wrong copy of a marker that appears twice.
 *
 * @param list<array{kind: string, function: string, statement_index: int, base_offset: int, base_length: int, base_text: string, variant_text: string, note: string}> $edits
 * @return array{code: string, injections: list<array<string, mixed>>}
 */
function bcb_apply_edits(string $code, array $edits): array
{
    usort($edits, static fn(array $a, array $b): int => $a['base_offset'] <=> $b['base_offset']);

    $previousEnd = -1;

    foreach ($edits as $edit) {
        if ($edit['base_offset'] < $previousEnd) {
            throw new RuntimeException('overlapping injections: the operator is not well defined');
        }

        if (substr($code, $edit['base_offset'], $edit['base_length']) !== $edit['base_text']) {
            throw new RuntimeException('injection does not match the base at its recorded offset');
        }

        $previousEnd = $edit['base_offset'] + $edit['base_length'];
    }

    $result = $code;

    foreach (array_reverse($edits) as $edit) {
        $result = substr_replace($result, $edit['variant_text'], $edit['base_offset'], $edit['base_length']);
    }

    $injections = [];
    $shift      = 0;

    foreach ($edits as $edit) {
        $injections[] = $edit + ['variant_offset' => $edit['base_offset'] + $shift];
        $shift += strlen($edit['variant_text']) - $edit['base_length'];
    }

    return ['code' => $result, 'injections' => $injections];
}

// ---------------------------------------------------------------------------
// Family 1 — gapped edits at controlled density
// ---------------------------------------------------------------------------

/**
 * d statement edits inside one function, evenly spaced.
 *
 * This is the operator family the unified engine's seeding stage is structurally
 * weakest against: every edit breaks the exact run a k-gram seed is drawn from,
 * so the surviving runs shorten as d rises, and below the seed length the clone
 * becomes invisible however similar the two copies still are. Measuring that as
 * a curve — recall against edits per 100 tokens — is the point of the family;
 * asserting a single number would hide exactly the part that matters.
 *
 * @param string $kind insert, delete or substitute
 * @param int    $edits d, the number of edits per clone
 * @return array{code: string, injections: list<array<string, mixed>>, eligible: bool, reason: string}
 */
function bcb_apply_gapped(string $code, string $kind, int $edits): array
{
    $ineligible = static fn(string $why): array => [
        'code' => $code, 'injections' => [], 'eligible' => false, 'reason' => $why,
    ];

    if (!in_array($kind, ['insert', 'delete', 'substitute'], true)) {
        return $ineligible('unknown gapped kind: ' . $kind);
    }

    // Insertions need only somewhere to sit; deletions and substitutions consume
    // a statement each, so the function must have more than it loses.
    $target = bcb_target_function($code, $edits + 2);

    if ($target === null) {
        return $ineligible(sprintf('no function with at least %d top-level statements', $edits + 2));
    }

    $positions = bcb_edit_positions(count($target['statements']), $edits);

    if ($positions === []) {
        return $ineligible('no evenly spaced interior positions for d=' . $edits);
    }

    $plan = [];
    $nth  = 0;

    foreach ($positions as $index) {
        $nth++;
        $statement = $target['statements'][$index];
        $text      = substr($code, $statement['start'], $statement['end'] - $statement['start']);
        $indent    = bcb_line_indent($code, $statement['start']);

        $plan[] = match ($kind) {
            'insert' => [
                'kind'            => 'insert',
                'function'        => $target['name'],
                'statement_index' => $index,
                'base_offset'     => $statement['end'],
                'base_length'     => 0,
                'base_text'       => '',
                'variant_text'    => sprintf(
                    "\n%s\$_bcb_gap_%d = null; // BCB-PHP gapped insertion %d/%d",
                    $indent,
                    $nth,
                    $nth,
                    $edits,
                ),
                'note'            => sprintf('inserted after statement %d of %d', $index, count($target['statements'])),
            ],
            'delete' => (static function () use ($code, $statement, $target, $index, $nth, $edits): array {
                [$from, $to] = bcb_delete_span($code, $statement['start'], $statement['end']);

                return [
                    'kind'            => 'delete',
                    'function'        => $target['name'],
                    'statement_index' => $index,
                    'base_offset'     => $from,
                    'base_length'     => $to - $from,
                    'base_text'       => substr($code, $from, $to - $from),
                    'variant_text'    => '',
                    'note'            => sprintf('deleted statement %d of %d (edit %d/%d)', $index, count($target['statements']), $nth, $edits),
                ];
            })(),
            default => [
                'kind'            => 'substitute',
                'function'        => $target['name'],
                'statement_index' => $index,
                'base_offset'     => $statement['start'],
                'base_length'     => $statement['end'] - $statement['start'],
                'base_text'       => $text,
                'variant_text'    => sprintf('$_bcb_sub_%d = null; // BCB-PHP gapped substitution %d/%d', $nth, $nth, $edits),
                'note'            => sprintf('substituted statement %d of %d', $index, count($target['statements'])),
            ],
        };
    }

    $applied = bcb_apply_edits($code, $plan);

    return $applied + ['eligible' => true, 'reason' => ''];
}

// ---------------------------------------------------------------------------
// Family 2 — statement permutations
// ---------------------------------------------------------------------------

/**
 * Swap two statements inside one function, at a recorded distance.
 *
 * `adjacent` swaps a neighbouring pair in the middle of the function; `distant`
 * swaps the second statement with the second-to-last, leaving a matching first
 * and last statement in both cases. The reordered region is therefore flanked by
 * agreement, which is what makes the two detectors under comparison disagree
 * usefully: an order-blind bag sees the same multiset either way and reports the
 * region without localizing anything, while a position-aware method has to name
 * which statements moved or admit it cannot.
 *
 * @param string $mode adjacent or distant
 * @return array{code: string, injections: list<array<string, mixed>>, eligible: bool, reason: string}
 */
function bcb_apply_permutation(string $code, string $mode): array
{
    $ineligible = static fn(string $why): array => [
        'code' => $code, 'injections' => [], 'eligible' => false, 'reason' => $why,
    ];

    if (!in_array($mode, ['adjacent', 'distant'], true)) {
        return $ineligible('unknown permutation mode: ' . $mode);
    }

    $minimum = $mode === 'adjacent' ? 4 : 5;
    $target  = bcb_target_function($code, $minimum);

    if ($target === null) {
        return $ineligible(sprintf('no function with at least %d top-level statements', $minimum));
    }

    $count = count($target['statements']);

    [$left, $right] = $mode === 'adjacent'
        ? [intdiv($count, 2) - 1, intdiv($count, 2)]
        : [1, $count - 2];

    if ($left < 0 || $right <= $left || $right >= $count) {
        return $ineligible('no interior pair to swap');
    }

    $a = $target['statements'][$left];
    $b = $target['statements'][$right];

    $textA = substr($code, $a['start'], $a['end'] - $a['start']);
    $textB = substr($code, $b['start'], $b['end'] - $b['start']);

    $distance = $right - $left;

    $plan = [
        [
            'kind'            => 'swap',
            'function'        => $target['name'],
            'statement_index' => $left,
            'base_offset'     => $a['start'],
            'base_length'     => $a['end'] - $a['start'],
            'base_text'       => $textA,
            'variant_text'    => $textB,
            'note'            => sprintf('%s swap: statement %d receives statement %d (distance %d)', $mode, $left, $right, $distance),
        ],
        [
            'kind'            => 'swap',
            'function'        => $target['name'],
            'statement_index' => $right,
            'base_offset'     => $b['start'],
            'base_length'     => $b['end'] - $b['start'],
            'base_text'       => $textB,
            'variant_text'    => $textA,
            'note'            => sprintf('%s swap: statement %d receives statement %d (distance %d)', $mode, $right, $left, $distance),
        ],
    ];

    $applied = bcb_apply_edits($code, $plan);

    return $applied + ['eligible' => true, 'reason' => ''];
}

// ---------------------------------------------------------------------------
// The operator registry and the one entry point that applies it
// ---------------------------------------------------------------------------

/**
 * Parse a density-parameterized operator name into what it does.
 *
 * The name is the whole specification — `gapped_insert_d2` says family, kind and
 * density — so a manifest that records the name records everything needed to
 * re-derive the variant. Nothing about an injection lives only in the memory of
 * the process that made it.
 *
 * @return array{family: string, kind: string, edits: int}|null
 */
function bcb_operator_spec(string $name): ?array
{
    if (preg_match('/^gapped_(insert|delete|substitute)_d([123])$/', $name, $m) === 1) {
        return ['family' => 'gapped', 'kind' => $m[1], 'edits' => (int) $m[2]];
    }

    if (preg_match('/^permute_(adjacent|distant)$/', $name, $m) === 1) {
        return ['family' => 'permutation', 'kind' => $m[1], 'edits' => 1];
    }

    return null;
}

/** @return list<string> the nine gapped operators, in a fixed order */
function bcb_gapped_operator_names(): array
{
    $names = [];

    foreach (['insert', 'delete', 'substitute'] as $kind) {
        foreach ([1, 2, 3] as $density) {
            $names[] = sprintf('gapped_%s_d%d', $kind, $density);
        }
    }

    return $names;
}

/** @return list<string> the two permutation operators, in a fixed order */
function bcb_permutation_operator_names(): array
{
    return ['permute_adjacent', 'permute_distant'];
}

/**
 * Every operator name this file can apply: the five E2 operators first, then the
 * density-parameterized families, in a fixed order so a run over "all" is
 * reproducible.
 *
 * @return list<string>
 */
function bcb_all_operator_names(): array
{
    return [
        ...array_keys(bcb_operators()),
        ...bcb_gapped_operator_names(),
        ...bcb_permutation_operator_names(),
    ];
}

/**
 * Apply one operator by name — the single entry point shared by the injection
 * CLI and the manifest checker.
 *
 * There is exactly one of these on purpose. If the CLI produced variants one way
 * and the checker re-derived them another, the checker would be verifying its
 * own copy of the operator rather than the one that made the files, and the
 * byte-for-byte guarantee would be worth nothing.
 *
 * @return array{code: string, injections: list<array<string, mixed>>, eligible: bool, reason: string}
 */
function bcb_inject(string $code, string $operator): array
{
    $spec = bcb_operator_spec($operator);

    if ($spec !== null) {
        return $spec['family'] === 'gapped'
            ? bcb_apply_gapped($code, $spec['kind'], $spec['edits'])
            : bcb_apply_permutation($code, $spec['kind']);
    }

    $legacy = bcb_operators();

    if (!isset($legacy[$operator])) {
        return ['code' => $code, 'injections' => [], 'eligible' => false, 'reason' => 'unknown operator: ' . $operator];
    }

    // The five E2 operators rewrite whole token streams rather than statements,
    // so they report no per-injection records; their variants are still verified
    // byte-for-byte by re-derivation and digest.
    return [
        'code'       => $legacy[$operator]($code),
        'injections' => [],
        'eligible'   => true,
        'reason'     => '',
    ];
}

/**
 * Does this source parse?
 *
 * TOKEN_PARSE runs the real parser rather than the lexer, so a variant that the
 * segmenter cut in the wrong place raises ParseError here — in-process, with no
 * subprocess per file. Deliberately parse-only: the ssdiff operators produce
 * code that parses and then fails to *compile* (an int default under a string
 * hint), which is their intended output, not a defect to flag.
 */
function bcb_parses(string $code): bool
{
    try {
        $tokens = token_get_all($code, TOKEN_PARSE);
    } catch (ParseError) {
        return false;
    }

    // Non-empty as well as parseable: an empty token list means there was
    // nothing to mutate, which is not a variant worth scoring either.
    return $tokens !== [];
}
