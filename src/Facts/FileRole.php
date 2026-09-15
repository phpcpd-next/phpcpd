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


/**
 * What a file *is* within the project, asked of its own tokens (ruling T as
 * amended 2026-09-02: role features, content- and wiring-derived only, **never
 * path patterns**).
 *
 * One role so far, the one the M5 charter pre-registers as demote stratum D2.
 *
 * ## Registration role
 *
 * A file is **registration-role** when, over the statements at its top level —
 * outside every function, class, interface, trait and enum body, and past the
 * `declare` / `namespace` / `use` preamble:
 *
 *   1. there is at least one such statement; and
 *   2. a **strict majority** of them are *registration expressions*; and
 *   3. those registration expressions are **pairwise dataflow-independent** —
 *      no two of them name the same variable.
 *
 * A *registration expression* is a top-level statement that is one call
 * expression whose value is discarded ({@see Statement::$singleCall}), **and
 * whose closure arguments hold only registration expressions themselves**.
 * Route tables, service-provider bindings, event maps and middleware stacks are
 * all written this way; so is any other configuration a framework asks for as
 * statements rather than as an array.
 *
 * ### Why the second clause, and why it is a recursion rather than a size
 *
 * A closure passed as an argument is a value the call receives, not a statement
 * the file executes — which is what lets `Route::group(…, function () { … })`
 * read as one call at all. But a value can hold anything, and without a second
 * clause the first one alone would call any file registration-role whose top
 * level is forty callbacks doing real work.
 *
 * So the closure is asked what it holds, in the same terms and by the same test.
 * A wrapper around a route list is a registration because a route list is what
 * it holds; a wrapper around an assignment, a branch or a loop is not, and
 * nothing but the body decides which. Nesting follows for free: a group of
 * groups of registrations is a registration. No constant appears, and there is
 * no size below which a closure is forgiven — the question is what the code
 * *is*, which is the only span-level question ruling H left standing.
 *
 * It costs something and the cost is measurable rather than argued: on
 * firefly-iii's `routes/breadcrumbs.php` the second clause refuses 26 of the 135
 * callbacks the first clause accepted, because those 26 do more than register.
 * The majority carries the file anyway, at 109 of 136, which is the majority
 * clause doing exactly the job it was introduced for.
 *
 * ### Why a majority, and why that is a definition rather than a constant
 *
 * "Predominantly" means more than half; a strict majority is the arithmetic of
 * the word, not a value fitted to an outcome. There is no curve it sits on and
 * nothing it could be tuned against — moving it would be changing the *word*,
 * which is what plan §3 forbids doing to a constant and which the M5 charter
 * settles by naming the role membership a definition.
 *
 * The majority is what makes this a **role** rather than the M5 experiment's
 * span filter. Rule 8 asked whether *every* statement of a reported span was a
 * registration expression, and the answer on real route files was no: one
 * grouping wrapper or one closure-bearing registration in a block of forty
 * refuses the whole span, which is why the rule reached one route finding of
 * seven. A file whose top level is thirty `Route::get(…)` calls and three
 * `Route::group(…, function () { … })` blocks *is* a route file, and the
 * majority says so.
 *
 * ### Why dataflow independence is required, and what it costs
 *
 * A run of `$builder->add(…)` statements over one shared receiver is a
 * *procedure*: order matters, each call can depend on what the previous one did,
 * and a second copy of it is a second copy of behaviour. The charter's paired
 * negative for this stratum is exactly that file, and it stays asserted. The
 * cost is stated rather than hidden: a route file written against a `$router`
 * variable instead of a facade is not registration-role under this definition.
 * That is the conservative direction — interpretation rule 5's asymmetry, where
 * a wrongly demoted finding loses a real clone's prominence and a wrongly
 * asserted one costs one line of noise.
 *
 * ### Never a path
 *
 * Nothing above reads a filename, a directory or a suffix. Ruling K's ban is not
 * merely observed here, it is unbreakable: this class is handed a token stream
 * and nothing else.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final readonly class FileRole
{
    private function __construct(
        public bool $registration,
        public int $statements,
        public int $registrations,
        public bool $coupled,
    ) {}

    public static function of(FileStatements $facts): self
    {
        $all           = $facts->all();
        $considered    = 0;
        $registrations = 0;
        /** @var array<string, int> $seen variable name => how many registration expressions named it */
        $seen    = [];
        $coupled = false;

        foreach ($facts->topLevel() as $statement) {
            if ($statement->preamble || $statement->length() === 0) {
                continue;
            }

            $considered++;

            if (!self::isRegistration($statement, $all)) {
                continue;
            }

            $registrations++;

            foreach ($statement->variables as $name => $_) {
                $seen[$name] = ($seen[$name] ?? 0) + 1;

                if ($seen[$name] > 1) {
                    $coupled = true;
                }
            }
        }

        return new self(
            $considered > 0 && $registrations * 2 > $considered && !$coupled,
            $considered,
            $registrations,
            $coupled,
        );
    }

    /**
     * Is this statement a registration expression — one call whose value is
     * discarded, and whose closure arguments hold only registration expressions
     * themselves?
     *
     * The recursion is the whole of the second clause and it carries no
     * constant. A grouping wrapper around a route list is a registration,
     * because a route list is what it holds. A grouping wrapper around an
     * assignment, a branch or a loop is not, because that is not a registration
     * and nothing but the body decides it. Nesting works by the same reading, so
     * a group inside a group inside a group is still one.
     *
     * Without it the first clause alone would take any call at all that happens
     * to receive a closure, and a file whose top level is forty callbacks doing
     * real work would answer that it is a route file. The cost asymmetry this
     * class is written under makes that the expensive direction: a wrongly
     * demoted finding loses a real clone's prominence.
     *
     * An empty closure holds nothing, and vacuously passes. It also registers
     * nothing, which is a thing about the source rather than about this test.
     *
     * @param list<Statement> $all every statement of the file, by index
     */
    private static function isRegistration(Statement $statement, array $all): bool
    {
        if (!$statement->singleCall) {
            return false;
        }

        foreach ($statement->closureBodies as [$from, $to]) {
            for ($index = $from; $index <= $to; $index++) {
                $body = $all[$index] ?? null;

                // Nothing of its own to judge. A statement with no tokens, or
                // one holding only the delimiters that close the closure, is
                // not something the closure "holds" — it is how the closure
                // ends.
                if ($body === null || $body->length() === 0 || $body->closing) {
                    continue;
                }

                if (!self::isRegistration($body, $all)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The verdict as one line a reader can check against the file — the same
     * "never silent" standard the triage rungs are held to.
     */
    public function explain(): string
    {
        if ($this->statements === 0) {
            return (new Catalogue())->get('explain.role.noStatements');
        }

        if ($this->coupled) {
            return (new Catalogue())->get('explain.role.coupled', [
                'registrations' => $this->registrations,
                'statements'    => $this->statements,
            ]);
        }

        return (new Catalogue())->get('explain.role.independent', [
            'registrations' => $this->registrations,
            'statements'    => $this->statements,
        ]);
    }
}
