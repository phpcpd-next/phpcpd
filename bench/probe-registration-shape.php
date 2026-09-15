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
 * Pre-commitment probe — should a closure ARGUMENT refuse a registration?
 *
 * `Facts\FileRole` calls a file registration-role when a strict majority of its
 * top-level statements are dataflow-independent registration expressions, and
 * its docblock says in as many words which files it means:
 *
 *   "A file whose top level is thirty Route::get(…) calls and three
 *    Route::group(…, function () { … }) blocks *is* a route file, and the
 *    majority says so."
 *
 * It does not. `Statement::$singleCall` disqualifies a statement carrying any
 * of a list of keywords ANYWHERE in its tokens, argument contents included —
 * deliberately, because that test was promoted out of the M5 experiment's span
 * filter, where what sits inside an argument is exactly what has to be refused.
 * `T_FUNCTION` inside `Route::group(…, function () { … })` therefore refuses it,
 * and on a route file written entirely that way the count is not a minority but
 * zero: firefly-iii's `routes/web.php` scores 0 of 61, `routes/breadcrumbs.php`
 * 0 of 136. The majority clause never has anything to count, and stratum D2
 * fires on no file in the corpus.
 *
 * ## What the shipped rule actually does, which is not a bug
 *
 * `FileStatementsTest::aGroupingWrapperDoesNotCostTheFileItsRole` asserts the
 * design outright: over three plain `Route::get(…)` calls and one
 * `Route::group(…, function () { … })`, the role holds at **3 registrations of 4
 * statements**. The wrapper is counted and is *not* a registration, and the
 * majority carries the file. The docblock sentence about "thirty calls and three
 * blocks" says the same thing: thirty outvote three. Code, docblock and test
 * agree.
 *
 * The gap is narrower than a contradiction. The rule needs a majority of
 * *ungrouped* registrations, and a route file written entirely with grouping
 * wrappers has none — firefly-iii's `routes/web.php` is 60 groups of 61
 * statements, which is one of the two ordinary ways to write Laravel routes.
 *
 * ## Three readings, measured
 *
 *   - **as shipped** — a closure anywhere in the statement refuses it.
 *   - **closure argument is a value** — the sweep steps over a closure passed as
 *     an argument. Universal as a statement about PHP, and too permissive: it
 *     also takes a grouping wrapper whose closure holds real logic.
 *   - **closure argument holds only registrations** — recursive membership test,
 *     no constant and no corpus in it. A wrapper around a route list counts; a
 *     wrapper around an assignment, a loop or a branch does not, and nothing
 *     decides which but the body itself.
 *
 * The third gains exactly the four firefly-iii route files and refuses every
 * case the second picked up beyond them — phpunit's two single-statement
 * bootstraps and one bare-majority WordPress page. It is more conservative than
 * the second everywhere except where the evidence says it should fire.
 *
 * The rest is untouched — the majority, the dataflow-independence clause, and
 * the charter's paired negative (a run of `$builder->add(…)` over one shared
 * receiver is a procedure and stays asserted, because those statements name the
 * same variable).
 *
 * This script does NOT change `src/`. It measures what the rule would do, the
 * way the M5 pre-commitment experiment did: prototype in bench/, test against
 * the corpora, and let the outcome decide whether the milestone is built.
 *
 * The shape test here is written from scratch rather than imported. An oracle
 * that reuses the implementation it is judging tests nothing, and the point is
 * to find out whether the *rule* is right, not whether two copies agree.
 *
 * ## The payoff, measured where it has to be
 *
 * Files changing role is not the answer to anything; `Strata` demotes a finding
 * only when EVERY one of its sites is in a registration-role file, so the number
 * that matters is findings reached by that composition. On firefly-iii the
 * recursive reading takes 113 of the unified engine's 797 findings, 21 of the
 * token bag's 320 and 1 of Rabin-Karp's 95, all of them inside the four route
 * files. On php-parser and symfony-console it takes none of any engine's.
 *
 * Demotion is not suppression: those findings are still reported and still
 * exported, they stop being asserted. That is the posture the M5 rating round
 * licensed and the reason a wrong answer here costs prominence rather than a
 * clone.
 *
 * ## Ruling H's distinction, asked of registration files
 *
 * Ruling H keeps one span-level discriminator alive by asking what a thing *is*
 * rather than how much of something it has: "a table matching itself ... its
 * second run is not a copy anyone could remove, it is the regularity that makes
 * it a table". The same question can be asked here, and the answer separates the
 * demotable from the not:
 *
 *   - a registration file repeating **itself** is the regularity that makes it
 *     one — 112 of the unified engine's 113, 16 of the token bag's 21, and the
 *     single Rabin-Karp finding;
 *   - two **different** registration files agreeing is a copy someone could
 *     remove. There are six of those on firefly-iii and every one is the same
 *     pair, `routes/api.php` against `routes/web.php` — the same route block
 *     written into both surfaces, which is exactly what a maintainer might want
 *     to see.
 *
 * So the narrower composition — demote only where all sites are in one file —
 * costs one finding of 113 and removes the whole class the wider one would have
 * got wrong. The project's cost asymmetry points the same way: keeping six
 * findings asserted costs six noisy lines, demoting them costs a real clone its
 * prominence.
 *
 * Usage:
 *   php bench/probe-registration-shape.php [<dir> ...] [--list=N]
 *   php bench/probe-registration-shape.php --findings=<dir>
 */

require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/harness.php';

use LucianoPereira\PhpcpdNext\Facts\FileRole;
use LucianoPereira\PhpcpdNext\Facts\FileStatements;

// phpcpd-ignore-start
//
// The keyword list below is `FileStatements::disqualifying()`, copied. It is
// private there and cannot be imported, and importing it would be the wrong
// move anyway: this probe exists to ask whether the shipped reading of a
// statement is right, and a probe that borrows the subject's own data cannot
// report that the data is part of the problem. It was, twice — `T_FOR` refusing
// `Breadcrumbs::for` is a fact about this list.
//
// Declared here rather than fixed, and only on this side: the live list in
// `src/` stays visible to the scan.

/**
 * Keywords that make a statement something other than a discarded call.
 *
 * @return array<int, true>
 */
function prs_disqualifying(): array
{
    /** @var ?array<int, true> $set */
    static $set = null;

    if ($set !== null) {
        return $set;
    }

    $set = [];

    foreach ([
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
    ] as $name) {
        if (defined($name)) {
            /** @var int $token */
            $token       = constant($name);
            $set[$token] = true;
        }
    }

    return $set;
}

// phpcpd-ignore-end

/**
 * The file's top-level statements, each as its significant tokens.
 *
 * Deliberately simple: split on `;` at bracket depth zero, outside every brace
 * body. That is enough for the question — a registration file's top level is a
 * run of call statements — and where it is not enough the caller compares its
 * count against `FileStatements` and says so rather than trusting itself.
 *
 * @return list<list<array{0: int|string, 1: string}>>
 */
function prs_top_level(string $source): array
{
    $tokens = @token_get_all($source);
    $flat   = [];

    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG], true)) {
            continue;
        }

        $flat[] = is_array($token) ? $token : [$token, $token];
    }

    $out   = [];
    $cur   = [];
    $depth = 0;
    $count = count($flat);

    for ($index = 0; $index < $count; $index++) {
        $type = $flat[$index][0];

        if ($type === '(' || $type === '[') {
            $depth++;
            $cur[] = $flat[$index];

            continue;
        }

        if ($type === ')' || $type === ']') {
            $depth--;
            $cur[] = $flat[$index];

            continue;
        }

        // A brace at top level opens a BLOCK — a class body, a function body,
        // an `if`. Its header is a top-level statement in its own right and is
        // emitted as one; the body belongs to the block and is skipped.
        //
        // Dropping the header instead, which is what this walker did first,
        // silently removes exactly the statements that deny a majority: every
        // `if (…) { … }` in a WordPress page script vanished and the file came
        // out registration-role at 14 of 19. The denominator has to be honest
        // or the majority means nothing.
        if ($type === '{' && $depth === 0) {
            $cur[] = $flat[$index];
            $out[] = $cur;
            $cur   = [];

            $braces = 1;

            while (++$index < $count && $braces > 0) {
                $inner = $flat[$index][0];

                if ($inner === '{') {
                    $braces++;
                } elseif ($inner === '}') {
                    $braces--;
                }
            }

            // A `;` closing the construct (a closure assignment, `};`) is part
            // of the block, not the next statement.
            if (($flat[$index][0] ?? null) === ';') {
                $index++;
            }

            $index--;

            continue;
        }

        $cur[] = $flat[$index];

        if ($type === ';' && $depth === 0) {
            $out[] = $cur;
            $cur   = [];
        }
    }

    if ($cur !== []) {
        $out[] = $cur;
    }

    return $out;
}

/**
 * Is this statement one call whose value is discarded, reading a closure
 * ARGUMENT as a value rather than as statements?
 *
 * One pass. A closure that appears inside an argument list is stepped over
 * whole — its parameters, its `use` clause, its return type and its body — and
 * the sweep resumes after it. Outside an argument list the same tokens are a
 * declaration and still refuse the statement, which is what keeps this a rule
 * about arguments rather than a hole in the test.
 *
 * @param list<array{0: int|string, 1: string}> $tokens
 */
function prs_is_registration(array $tokens, string $closureArgumentIsAValue, bool $keywordAfterArrowIsAName = false): bool
{
    $bad = prs_disqualifying();

    while ($tokens !== [] && $tokens[count($tokens) - 1][0] === ';') {
        array_pop($tokens);
    }

    $count = count($tokens);

    if ($count === 0) {
        return false;
    }

    $opensAsCall = false;
    $depth       = 0;
    $previous    = '';
    $index       = 0;

    while ($index < $count) {
        $type = $tokens[$index][0];

        // `static function (…) { … }` and `fn (…) => …` as an argument.
        if ($closureArgumentIsAValue !== 'no' && $depth > 0 && prs_opens_closure($tokens, $index)) {
            $end = prs_past_closure($tokens, $index);

            // The recursive reading: a closure argument is a value only when
            // what it holds is itself registrations. A grouping wrapper around
            // a route list counts; a grouping wrapper around real logic does
            // not, and no threshold decides which.
            if ($closureArgumentIsAValue === 'only-registrations'
                && !prs_body_is_registrations(array_slice($tokens, $index, $end - $index))) {
                return false;
            }

            $index = $end;

            continue;
        }

        if ($type === '(' || $type === '[') {
            if ($type === '(' && $depth === 0 && $index > 0) {
                $opensAsCall = true;
            }

            $depth++;
            $previous = $type;
            $index++;

            continue;
        }

        if ($type === ')' || $type === ']') {
            $depth--;
            $previous = $type;
            $index++;

            continue;
        }

        // A keyword directly after `::` or `->` is a METHOD NAME, not a
        // keyword. PHP allows every one of them there, and the tokenizer still
        // spells `Breadcrumbs::for` with `T_FOR`. The shipped test carves out
        // `Foo::class` alone; `class` is not special, it is simply the one
        // that came up first.
        $afterArrow = $previous === T_DOUBLE_COLON
            || $previous === T_OBJECT_OPERATOR
            || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $previous === T_NULLSAFE_OBJECT_OPERATOR);

        if (is_int($type) && isset($bad[$type])
            && !($type === T_CLASS && $previous === T_DOUBLE_COLON)
            && !($keywordAfterArrowIsAName && $afterArrow)) {
            return false;
        }

        if ($type === '=' || $type === '&') {
            return false;
        }

        $previous = $type;
        $index++;
    }

    return $opensAsCall;
}

/**
 * Is every statement in a closure's body itself a registration?
 *
 * The body is split on `;` at its own top level; a body that holds a block, an
 * assignment or a control-flow statement fails on that statement and takes the
 * whole closure with it. An empty body is vacuously true and is not special —
 * `Route::group([…], fn () {})` registers nothing and says so.
 *
 * @param list<array{0: int|string, 1: string}> $closure the closure's own tokens
 */
function prs_body_is_registrations(array $closure): bool
{
    $open = null;
    $count = count($closure);

    for ($index = 0; $index < $count; $index++) {
        if ($closure[$index][0] === '{') {
            $open = $index;

            break;
        }
    }

    // An arrow function's body is one expression, not statements.
    if ($open === null) {
        return true;
    }

    $depth = 0;
    $cur   = [];

    for ($index = $open + 1; $index < $count - 1; $index++) {
        $type = $closure[$index][0];

        if ($type === '(' || $type === '[' || $type === '{') {
            $depth++;
        } elseif ($type === ')' || $type === ']' || $type === '}') {
            $depth--;
        }

        $cur[] = $closure[$index];

        if ($type === ';' && $depth === 0) {
            if (!prs_is_registration($cur, 'only-registrations', true)) {
                return false;
            }

            $cur = [];
        }
    }

    // Anything left over is a statement that never closed — a block.
    return array_filter($cur, static fn(array $t): bool => $t[0] !== '}') === [];
}

/**
 * Does a closure begin at this index — allowing the `static` that a framework
 * callback is almost always written with?
 *
 * @param list<array{0: int|string, 1: string}> $tokens
 */
function prs_opens_closure(array $tokens, int $index): bool
{
    $type = $tokens[$index][0];

    if ($type === T_STATIC) {
        $type = $tokens[$index + 1][0] ?? null;
    }

    return $type === T_FUNCTION || (defined('T_FN') && $type === T_FN);
}

/**
 * The index just past a closure that begins at $index.
 *
 * A braced closure ends at the `}` matching its first `{`. An arrow function
 * has no braces and ends where its enclosing argument does — the first `,` or
 * `)` at the depth it started from.
 *
 * @param list<array{0: int|string, 1: string}> $tokens
 */
function prs_past_closure(array $tokens, int $index): int
{
    $count  = count($tokens);
    $arrow  = defined('T_FN') && ($tokens[$index][0] === T_FN
        || (($tokens[$index][0] === T_STATIC) && ($tokens[$index + 1][0] ?? null) === T_FN));
    $depth  = 0;
    $braces = 0;
    $opened = false;

    for ($index++; $index < $count; $index++) {
        $type = $tokens[$index][0];

        if ($type === '(' || $type === '[') {
            $depth++;

            continue;
        }

        if ($type === ')' || $type === ']') {
            if ($arrow && $depth === 0) {
                return $index;
            }

            $depth--;

            continue;
        }

        if ($arrow && $depth === 0 && $type === ',') {
            return $index;
        }

        if ($type === '{') {
            $braces++;
            $opened = true;

            continue;
        }

        if ($type === '}') {
            $braces--;

            if ($opened && $braces === 0) {
                return $index + 1;
            }
        }
    }

    return $count;
}

// Run only when invoked as a script. Required directly — as the debugging
// scaffolds around this probe do — it defines its functions and nothing else.
if (str_contains(bcb_argv()[0], 'probe-registration-shape')) {
    prs_main();
}

/** The sweep, over the directories named or over every pinned corpus. */
function prs_main(): void
{
$dirs     = [];
$list     = 12;
$findings = null;

foreach (array_slice(bcb_argv(), 1) as $argument) {
    if (str_starts_with($argument, '--list=')) {
        $list = max(0, (int) substr($argument, strlen('--list=')));

        continue;
    }

    if (str_starts_with($argument, '--findings=')) {
        $findings = substr($argument, strlen('--findings='));

        continue;
    }

    $dirs[] = $argument;
}

if ($findings !== null) {
    prs_findings($findings);

    return;
}

if ($dirs === []) {
    foreach (glob(__DIR__ . '/corpus/*', GLOB_ONLYDIR) ?: [] as $corpus) {
        $dirs[] = $corpus;
    }
}

bcb_require_dirs($dirs);

printf("Registration shape — does a closure argument refuse a registration?\n\n");

foreach ($dirs as $dir) {
    $now = 0;
    $variant = ['as shipped' => 0, 'closure' => 0, 'keyword' => 0, 'both' => 0, 'recursive' => 0];
    $flipped = [];
    $files = 0;

    foreach (bcb_files($dir) as $file) {
        $source = (string) file_get_contents($file);
        $files++;

        $shipped = FileRole::of(FileStatements::fromSource($source))->registration;

        $statements = prs_top_level($source);
        $considered = 0;
        $counts = ['as shipped' => 0, 'closure' => 0, 'keyword' => 0, 'both' => 0, 'recursive' => 0];

        foreach ($statements as $tokens) {
            // The preamble is not a statement this rule counts.
            $head = $tokens[0][0];

            if (is_int($head) && in_array($head, array_filter([
                defined('T_DECLARE') ? T_DECLARE : null,
                defined('T_NAMESPACE') ? T_NAMESPACE : null,
                defined('T_USE') ? T_USE : null,
            ]), true)) {
                continue;
            }

            $considered++;

            $counts['as shipped'] += prs_is_registration($tokens, 'no', false) ? 1 : 0;
            $counts['closure']    += prs_is_registration($tokens, 'value', false) ? 1 : 0;
            $counts['keyword']    += prs_is_registration($tokens, 'no', true) ? 1 : 0;
            $counts['both']       += prs_is_registration($tokens, 'value', true) ? 1 : 0;
            $counts['recursive']  += prs_is_registration($tokens, 'only-registrations', true) ? 1 : 0;
        }

        foreach ($counts as $name => $registrations) {
            $role = $considered > 0 && $registrations * 2 > $considered;
            $variant[$name] += $role ? 1 : 0;

            if ($role && $name === 'both' && $counts['as shipped'] * 2 <= $considered) {
                $flipped[] = sprintf('%s (%d of %d)', str_replace($dir . '/', '', $file), $registrations, $considered);
            }
        }

        $now += $shipped ? 1 : 0;
    }

    // `FileRole` is the shipped implementation over its own segmentation; the
    // other four are this probe's walker, so only they may be compared with one
    // another. The first of them reproduces the shipped shape test and exists
    // to say how far the two segmentations agree.
    printf("  %-18s %4d files   FileRole %d | this walker: shipped-shape %d  +closure-arg %d  +keyword-as-name %d  both %d  recursive %d\n",
        basename($dir), $files, $now,
        $variant['as shipped'], $variant['closure'], $variant['keyword'], $variant['both'], $variant['recursive']);

    foreach (array_slice($flipped, 0, $list) as $entry) {
        printf("      + %s\n", $entry);
    }

    if (count($flipped) > $list) {
        printf("      … and %d more\n", count($flipped) - $list);
    }
}
}

/**
 * The payoff, as findings rather than as files.
 *
 * `Presentation\Strata` puts a finding in the registration stratum only when
 * EVERY one of its sites is in a registration-role file — "a clone with one site
 * in a route file and one in a controller is a copy of registration *into*
 * program text, and the controller's copy is exactly what a reader wants
 * asserted". So what a rule change has to answer is not how many files changed
 * role but how many findings that composition now reaches.
 */
function prs_findings(string $dir): void
{
    $roles = [];

    foreach (bcb_files($dir) as $file) {
        $considered = 0;
        $counts     = ['shipped' => 0, 'recursive' => 0];

        foreach (prs_top_level((string) file_get_contents($file)) as $tokens) {
            $head = $tokens[0][0];

            if (is_int($head) && in_array($head, array_filter([
                defined('T_DECLARE') ? T_DECLARE : null,
                defined('T_NAMESPACE') ? T_NAMESPACE : null,
                defined('T_USE') ? T_USE : null,
            ]), true)) {
                continue;
            }

            $considered++;
            $counts['shipped']   += prs_is_registration($tokens, 'no', true) ? 1 : 0;
            $counts['recursive'] += prs_is_registration($tokens, 'only-registrations', true) ? 1 : 0;
        }

        $roles[$file] = [
            'shipped'   => $considered > 0 && $counts['shipped'] * 2 > $considered,
            'recursive' => $considered > 0 && $counts['recursive'] * 2 > $considered,
        ];
    }

    printf("Findings whose every site sits in a registration-role file — %s\n\n", basename($dir));

    /** @var list<string> $crossing */
    $crossing = [];

    $files = bcb_files($dir);

    foreach (['rabin-karp', 'tokenbag', 'unified'] as $engine) {
        $map   = bcb_detect($files, ['algorithm' => $engine, 'minTokens' => 70, 'minLines' => 5]);
        $total = 0;
        $now   = 0;
        $after = 0;

        $selfRepeating = 0;
        $acrossFiles   = 0;

        foreach ($map as $clone) {
            $total++;
            $everyShipped   = true;
            $everyRecursive = true;
            $names          = [];

            foreach ($clone->files() as $site) {
                $role           = $roles[$site->name] ?? ['shipped' => false, 'recursive' => false];
                $everyShipped   = $everyShipped && $role['shipped'];
                $everyRecursive = $everyRecursive && $role['recursive'];
                $names[$site->name] = true;
            }

            $now   += $everyShipped ? 1 : 0;
            $after += $everyRecursive ? 1 : 0;

            if ($everyRecursive) {
                // Ruling H's distinction, asked of registration files rather
                // than of tables: a registration file repeating itself is the
                // regularity that makes it one, and its second run is not a
                // copy anybody could remove. Two DIFFERENT registration files
                // agreeing is a copy, and somebody might want it gone.
                if (count($names) === 1) {
                    $selfRepeating++;
                } else {
                    $acrossFiles++;
                    $crossing[] = sprintf('%s: %s', $engine, implode(' <-> ', array_map(
                        static fn(string $path): string => basename(dirname($path)) . '/' . basename($path),
                        array_keys($names),
                    )));
                }
            }
        }

        printf("  %-12s %4d findings   demoted: shipped %d, recursive %d  (+%d)   of those: %d self-repeating, %d across files\n",
            $engine, $total, $now, $after, $after - $now, $selfRepeating, $acrossFiles);
    }

    if ($crossing !== []) {
        printf("\n  the ones that cross registration files — the class ruling H would keep asserted:\n");

        foreach ($crossing as $entry) {
            printf("    %s\n", $entry);
        }
    }
}
