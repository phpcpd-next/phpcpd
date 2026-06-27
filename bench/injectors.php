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

/** @return array<string, callable(string):string> operator name => function */
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
