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

namespace LucianoPereira\PhpcpdNext\Orphan;

use function array_pop;

use function count;
use function end;
use function file_get_contents;
use function in_array;
use function is_string;
use function preg_match;
use function preg_quote;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function strrpos;
use function substr;
use function token_get_all;
use function trim;

use const T_ABSTRACT;

use const T_AS;
use const T_ATTRIBUTE;
use const T_CLASS;
use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_CURLY_OPEN;
use const T_DOC_COMMENT;
use const T_DOLLAR_OPEN_CURLY_BRACES;
use const T_ENUM;
use const T_EXTENDS;
use const T_FUNCTION;
use const T_IF;
use const T_IMPLEMENTS;
use const T_INTERFACE;
use const T_NAMESPACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_STATIC;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

use LucianoPereira\PhpcpdNext\Util\TokenCursor;

/**
 * Turns a set of PHP files into a {@see CollectedSymbols}: what is declared and
 * what is referenced. Pure token analysis via `token_get_all` — the same engine
 * the clone strategies use, and the same deliberate constraint (no parser, no
 * AST, no third-party dependency; see ROADMAP.md).
 *
 * The token approach is precisely what makes this better than a grep-based dead
 * code finder such as phpunused: comments and strings are distinguished from
 * real code, so a class name mentioned in a `// TODO` no longer masks a genuine
 * orphan, and a name in a string is scored as a *weak* (dynamic) signal rather
 * than a hard reference.
 *
 * Safety bias: reference detection is intentionally generous. Any code mention
 * of a name — `new`, `extends`, a type hint, a static call, an attribute, a
 * qualified name's last segment — counts. Over-counting yields a false negative
 * (a real orphan we stay silent about); under-counting would tell someone to
 * delete live code. We always prefer the former.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final class SymbolCollector
{
    /**
     * Attributes that wire a class into a framework's runtime, so the class is
     * reachable even though nothing references it by name. Harvested from
     * shipmonk/dead-code-detector's usage providers (Symfony, Laravel, Doctrine,
     * PHPUnit). Matched on the attribute's short name.
     *
     * @var list<string>
     */
    private const array ENTRYPOINT_ATTRIBUTES = [
        // PHP itself — an attribute class is instantiated by reflection.
        'Attribute',
        // Symfony routing / DI / messenger / console / scheduler.
        'Route', 'Get', 'Post', 'Put', 'Patch', 'Delete', 'Head', 'Options',
        'AsController', 'AsCommand', 'AsEventListener', 'AsMessageHandler',
        'AsPeriodicTask', 'AsCronTask', 'AsScheduledTask', 'AsDecorator',
        'AsAlias', 'AsTaggedItem', 'Required', 'Autoconfigure', 'AutoconfigureTag',
        'When', 'Autowire',
        // Doctrine mapping — entities are hydrated reflectively.
        'Entity', 'Embeddable', 'MappedSuperclass',
        // PHPUnit / test tooling — the runner discovers these reflectively.
        'CoversClass', 'Test', 'DataProvider', 'Group', 'RunTestsInSeparateProcesses',
    ];

    /**
     * Namespaces whose classes a framework instantiates by convention rather than
     * by reference. Matched as a namespace SUFFIX, so a modular project that
     * namespaces its seeders `Acme\Database\Seeders` is covered too.
     *
     * Laravel discovers seeders and factories by directory and class-name
     * convention — `db:seed` calls `DatabaseSeeder`, which in every non-trivial
     * project globs its siblings — so no seeder class name appears anywhere in
     * code. Measured on the adopting project: 24 live seeders and factories
     * reported in the tier that gates CI, where acting on the finding deletes a
     * working database bootstrap. `definite` is the wrong home for a symbol whose
     * caller is the framework itself.
     *
     * @var list<string>
     */
    private const array ENTRYPOINT_NAMESPACES = [
        'Database\\Seeders', 'Database\\Factories',
    ];

    /**
     * Docblock tags that declare a symbol intentionally public / kept. Harvested
     * from Psalm's `@psalm-api` and shipmonk's `@api` entry-point marker, plus a
     * project-native escape hatch.
     *
     * @var list<string>
     */
    private const array KEEP_TAGS = [
        '@api', '@psalm-api', '@phpstan-api', '@phpcpd-keep', '@phpcpd-ignore-orphan',
    ];

    /**
     * Declares a symbol deliberately not wired yet. The opposite claim to a keep
     * tag — which asserts the symbol IS reachable — so the two must not merge:
     * only this one carries an expiry, and only this one is worth reporting when
     * the symbol finally does get referenced.
     */
    private const string PLANNED_TAG = '@phpcpd-planned';

    /**
     * A declaration guarded by one of these is conditional on its own name not
     * already existing, which is a direct statement that it exists for a caller
     * outside this codebase. On a runtime where the name is taken it is never
     * declared at all, so a same-project reference to it would be a bug.
     *
     * @var list<string>
     */
    private const array EXISTENCE_GUARDS = [
        'function_exists', 'class_exists', 'interface_exists', 'trait_exists', 'enum_exists',
    ];

    /**
     * @param list<string>         $files
     * @param ?OrphanConfiguration $config which suppression rules are live. Idiom
     *                                     scanning is the only part of this pass
     *                                     that a rule can switch off, so a run
     *                                     with `--no-suppress=discovery` does not
     *                                     pay for it.
     */
    public function collect(array $files, ?OrphanConfiguration $config = null): CollectedSymbols
    {
        $config      = $config ?? new OrphanConfiguration();
        $definitions = [];
        $references  = [];
        $stringNames = [];
        $discoveries = [];
        $conventions = [];
        $traitUses   = [];
        $discovery   = $config->ruleEnabled(Rule::DISCOVERY);
        $convention  = $config->ruleEnabled(Rule::CONVENTION);

        foreach ($files as $file) {
            $buffer = file_get_contents($file);

            if ($buffer === false) {
                continue;
            }

            $this->collectFile(
                $file,
                $buffer,
                $definitions,
                $references,
                $stringNames,
                $discoveries,
                $conventions,
                $traitUses,
                $discovery,
                $convention,
            );
        }

        return new CollectedSymbols(
            $definitions,
            $references,
            $stringNames,
            new IdiomIndex($discoveries, $conventions, $traitUses),
        );
    }

    /**
     * @param list<Symbol>                                            $definitions
     * @param array<string, int>                                      $references
     * @param array<string, string>                                   $stringNames
     * @param array<string, list<array{pattern: string, at: string}>> $discoveries
     * @param array<string, array<string, string>>                    $conventions
     * @param array<string, list<string>>                             $traitUses
     */
    private function collectFile(
        string $file,
        string $buffer,
        array &$definitions,
        array &$references,
        array &$stringNames,
        array &$discoveries,
        array &$conventions,
        array &$traitUses,
        bool $scanDiscovery,
        bool $scanConvention,
    ): void {
        $tokens = token_get_all($buffer);
        $count  = count($tokens);

        // Idiom detection reads the same token stream the walk below consumes, so
        // it costs a second pass over an array already in memory and no I/O at
        // all. Reference detection cannot fold it in: this asks whether the file
        // BUILDS names, not whether it mentions them.
        if ($scanDiscovery) {
            $discovery = IdiomScanner::discovery($tokens, $file);

            if ($discovery !== null) {
                $discoveries[$discovery['directory']][] = [
                    'pattern' => $discovery['pattern'],
                    'at'      => $discovery['at'],
                ];
            }
        }

        $namespace     = '';
        $lastDoc       = null;
        $pendingAttrs  = [];
        $abstract      = false;
        $context       = [];      // stack of body kinds: 'type' | 'other'
        $pendingBlock  = null;    // what the next '{' opens
        $aliases       = [];      // alias short name => imported short name
        $localRefs     = [];      // names referenced in *this* file
        $pendingType   = null;    // fqn of the type the next 'type' block declares
        $typeStack     = [];      // fqn (null when anonymous) per open type body

        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '{') {
                    $opens        = $pendingBlock ?? 'other';
                    $context[]    = $opens;
                    $pendingBlock = null;

                    // Shadows $context exactly for 'type' frames, so the name of
                    // the enclosing declaration is available inside its body —
                    // which is what a trait use and a suffix concatenation have
                    // to be attributed to.
                    if ($opens === 'type') {
                        $typeStack[] = $pendingType;
                    }

                    $pendingType = null;
                } elseif ($token === '}') {
                    if (array_pop($context) === 'type') {
                        array_pop($typeStack);
                    }
                } elseif ($token === ';') {
                    // Statement boundary: a docblock or attribute that did not
                    // bind to a declaration must not leak onto the next one.
                    //
                    // $pendingBlock is reset for the same reason. A statement that
                    // ends in `;` never opened the body it announced, so a
                    // brace-less `if (class_exists(...)) require ...;` left 'guard'
                    // pending and stamped it on the next unrelated `{` — a
                    // foreach, a try, any block that does not announce its own
                    // kind. A type declared inside that block was then read as
                    // living in an existence guard and suppressed as a polyfill,
                    // which is a real orphan turned into silence.
                    //
                    // Safe because no construct that sets $pendingBlock has a `;`
                    // between itself and its own `{`.
                    $lastDoc      = null;
                    $pendingAttrs = [];
                    $abstract     = false;
                    $pendingBlock = null;
                    $pendingType  = null;
                }

                $i++;
                continue;
            }

            [$id, $text] = [$token[0], $token[1]];

            switch ($id) {
                case T_WHITESPACE:
                case T_COMMENT:
                    break;

                case T_DOC_COMMENT:
                    $lastDoc = $text;
                    break;

                case T_CURLY_OPEN:
                case T_DOLLAR_OPEN_CURLY_BRACES:
                    // Interpolation ("... {$x} ...") emits an *array* token for
                    // the opening brace but a plain '}' string token to close
                    // it. Without a matching push, that '}' pops a real block
                    // level and $context stays shallow for the rest of the file.
                    $context[] = 'other';
                    break;

                case T_ABSTRACT:
                    $abstract = true;
                    break;

                case T_IF:
                    if ($this->isExistenceGuard($tokens, $i + 1)) {
                        $pendingBlock = 'guard';
                    }

                    break;

                case T_ATTRIBUTE:
                    // Record the attribute's short name for entry-point scoring.
                    // The name token itself is still scanned normally below, so
                    // using an attribute also counts as a reference to it.
                    $nameIndex = $this->nextSignificant($tokens, $i + 1);

                    if ($nameIndex !== null) {
                        $pendingAttrs[] = $this->shortName($this->tokenName($tokens[$nameIndex]));
                    }

                    break;

                case T_NAMESPACE:
                    $nameIndex = $this->nextSignificant($tokens, $i + 1);

                    if ($nameIndex !== null && !is_string($tokens[$nameIndex])) {
                        $namespace = $this->tokenName($tokens[$nameIndex]);
                        $i         = $nameIndex; // names consumed; do not count as refs
                    }

                    break;

                case T_USE:
                    // An import (`use A\B\C;`) is not a use-site: skip its names
                    // so an unused import cannot mask a dead class. A trait use
                    // inside a type body IS a reference, so leave that to fall
                    // through to normal name counting. A closure capture
                    // (`use ($x)`) is the only `use` followed by `(`: skipping it
                    // would run past the closure's own `{` and desync $context.
                    $useNext = $this->nextSignificant($tokens, $i + 1);
                    $capture = $useNext !== null && $tokens[$useNext] === '(';

                    if (!$capture && end($context) !== 'type') {
                        $end = $this->skipToSemicolon($tokens, $i + 1);
                        $this->collectImportAliases($tokens, $i + 1, $end, $aliases);
                        $i = $end;
                        continue 2;
                    }

                    // A trait use inside a type body. Recorded as well as counted
                    // as a reference, because the convention rule needs to know
                    // WHICH type consumes the trait: a companion class is spared
                    // only when its base actually uses the trait that declares
                    // the suffix, never on the name pattern alone.
                    $consumer = end($typeStack);

                    if ($scanConvention && !$capture && end($context) === 'type' && is_string($consumer)) {
                        $this->collectTraitUses($tokens, $i + 1, $consumer, $traitUses);
                    }

                    break;

                case T_CLASS:
                case T_INTERFACE:
                case T_TRAIT:
                case T_ENUM:
                    $nameIndex = $this->nextSignificant($tokens, $i + 1);

                    // A name after the keyword marks a real declaration; anything
                    // else is anonymous (`new class`) or the `::class` constant.
                    if ($nameIndex !== null && $tokens[$nameIndex][0] === T_STRING) {
                        $name         = $tokens[$nameIndex][1];
                        $pendingType  = $namespace === '' ? $name : $namespace . '\\' . $name;
                        [$rule, $why] = $this->ruleFor($this->kindFor($id), $name, $pendingAttrs, $lastDoc, $context, $namespace);

                        $definitions[] = new Symbol(
                            kind:       $this->kindFor($id),
                            name:       $name,
                            fqn:        $namespace === '' ? $name : $namespace . '\\' . $name,
                            file:       $file,
                            line:       $token[2],
                            abstract:   $abstract && $id === T_CLASS,
                            rule:       $rule,
                            ruleReason: $why,
                        );

                        $pendingBlock = 'type';
                        $i            = $nameIndex + 1; // skip the declaration name
                        $lastDoc      = null;
                        $pendingAttrs = [];
                        $abstract     = false;
                        continue 2;
                    }

                    // An anonymous class declares no symbol but still opens a
                    // *type* body: its methods are methods, and a `use T;`
                    // inside it is a trait reference. Only `::class` gets here
                    // without opening a body.
                    if ($nameIndex !== null && $this->opensClassBody($tokens[$nameIndex])) {
                        $pendingBlock = 'type';
                    }

                    break;

                case T_FUNCTION:
                    $isMethod  = end($context) === 'type';
                    $nameIndex = $this->nextSignificant($tokens, $i + 1);

                    // Skip an optional return-by-reference `&`.
                    if ($nameIndex !== null && $tokens[$nameIndex] === '&') {
                        $nameIndex = $this->nextSignificant($tokens, $nameIndex + 1);
                    }

                    $pendingBlock = 'function';

                    if ($nameIndex !== null && !is_string($tokens[$nameIndex]) && $tokens[$nameIndex][0] === T_STRING) {
                        if (!$isMethod) {
                            $name         = $tokens[$nameIndex][1];
                            [$rule, $why] = $this->ruleFor(Symbol::KIND_FUNCTION, $name, $pendingAttrs, $lastDoc, $context, $namespace);

                            $definitions[] = new Symbol(
                                kind:       Symbol::KIND_FUNCTION,
                                name:       $name,
                                fqn:        $namespace === '' ? $name : $namespace . '\\' . $name,
                                file:       $file,
                                line:       $token[2],
                                rule:       $rule,
                                ruleReason: $why,
                            );
                        }

                        // Whether method or function, the declaration name is not
                        // a reference to a global function of the same name.
                        $i            = $nameIndex + 1;
                        $lastDoc      = null;
                        $pendingAttrs = [];
                        continue 2;
                    }

                    break;

                case T_STATIC:
                    // `static::class . 'Suffix'` — the companion-class idiom.
                    // `class_basename(static::class) . 'Suffix'` contains the
                    // same trio, so this one probe covers both shapes.
                    if ($scanConvention && IdiomScanner::readsStaticClass($tokens, $i)) {
                        $this->recordConvention($tokens, $i, $file, $token[2], end($typeStack), $conventions);
                    }

                    break;

                case T_STRING:
                    $references[$text] = ($references[$text] ?? 0) + 1;
                    $localRefs[$text]  = true;
                    break;

                case T_NAME_QUALIFIED:
                case T_NAME_FULLY_QUALIFIED:
                case T_NAME_RELATIVE:
                    $short              = $this->shortName($text);
                    $references[$short] = ($references[$short] ?? 0) + 1;
                    $localRefs[$short]  = true;
                    break;

                case T_CONSTANT_ENCAPSED_STRING:
                    $this->recordStringName($text, $file, $token[2], $stringNames);
                    break;
            }

            $i++;
        }

        // A class imported under an alias is referenced by that alias, never by
        // its own name. Credit the imported name, but only when the alias was
        // actually used in this file — an unused import must still not count.
        foreach ($aliases as $alias => $imported) {
            if (isset($localRefs[$alias])) {
                $references[$imported] = ($references[$imported] ?? 0) + 1;
            }
        }
    }

    /**
     * Record the trait names a `use` statement inside a type body consumes.
     *
     * Only the short name is kept, which is what the convention rule compares
     * against. Method aliasing inside the `{ ... }` block of a trait use is not
     * a trait name, so the walk stops at the brace.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param array<string, list<string>>                         $traitUses
     */
    private function collectTraitUses(array $tokens, int $from, string $consumer, array &$traitUses): void
    {
        $count = count($tokens);

        for ($i = $from; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === ';' || $token === '{') {
                    return;
                }

                continue;
            }

            if ($token[0] === T_STRING || $token[0] === T_NAME_QUALIFIED
                || $token[0] === T_NAME_FULLY_QUALIFIED || $token[0] === T_NAME_RELATIVE) {
                $traitUses[$consumer][] = $this->shortName($token[1]);
            }
        }
    }

    /**
     * Register a suffix the type enclosing $at appends to its consumer's runtime
     * class name, if the expression there is that idiom.
     *
     * Keyed suffix => declaring type, so the rule can require BOTH halves of the
     * claim: the suffix came from a literal in this type, and the class being
     * judged has a base that uses this type. Either half alone would be a name
     * pattern.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param array<string, array<string, string>>                $conventions
     */
    private function recordConvention(
        array $tokens,
        int $at,
        string $file,
        int $line,
        mixed $declarer,
        array &$conventions,
    ): void {
        if (!is_string($declarer)) {
            return;
        }

        $suffix = IdiomScanner::suffixAfter($tokens, $at);

        if ($suffix !== null) {
            // First site wins, so a trait that builds the name twice cites one
            // stable location and two runs report the same evidence.
            $conventions[$suffix][$declarer] ??= $file . ':' . $line;
        }
    }

    /**
     * Record `use A\B\Original as Alias;` pairs from an import statement, in
     * every form it takes: single, comma-separated, and grouped
     * (`use A\{B as C};`). Method aliasing inside a trait-use block never
     * reaches here — that has a type-body context and is counted normally.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     * @param array<string, string>                               $aliases
     */
    private function collectImportAliases(array $tokens, int $from, int $to, array &$aliases): void
    {
        $lastName    = null;
        $expectAlias = false;

        for ($i = $from; $i < $to; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                continue;
            }

            [$id, $text] = [$token[0], $token[1]];

            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }

            if ($id === T_AS) {
                $expectAlias = true;
                continue;
            }

            if ($id !== T_STRING && $id !== T_NAME_QUALIFIED && $id !== T_NAME_FULLY_QUALIFIED) {
                continue;
            }

            if ($expectAlias && $lastName !== null) {
                $aliases[$text] = $this->shortName($lastName);
                $expectAlias    = false;
                $lastName       = null;

                continue;
            }

            $lastName = $text;
        }
    }

    private function kindFor(int $tokenId): string
    {
        return match ($tokenId) {
            T_INTERFACE => Symbol::KIND_INTERFACE,
            T_TRAIT     => Symbol::KIND_TRAIT,
            T_ENUM      => Symbol::KIND_ENUM,
            default     => Symbol::KIND_CLASS,
        };
    }

    /**
     * Keep the FIRST location a name was seen as a string; that is the one the
     * report cites, and a stable choice makes the output reproducible.
     *
     * @param array<string, string> $stringNames
     */
    private function recordStringName(string $literal, string $file, int $line, array &$stringNames): void
    {
        $value = trim($literal, "'\"");

        if ($value === '') {
            return;
        }

        $where = $file . ':' . $line;
        $short = $this->shortName($value);

        $stringNames[$value] ??= $where;
        $stringNames[$short] ??= $where;
    }

    /**
     * Which rule, if any, already accounts for this symbol — checked in order of
     * how specific the claim is. An author-stated intent outranks a structural
     * one, because only the author knows whether the symbol is reachable
     * (`@api`) or knowingly unwired (`@phpcpd-planned`).
     *
     * @param list<string> $pendingAttrs
     * @param list<string> $context
     * @return array{0: ?string, 1: ?string} rule name, reason
     */
    private function ruleFor(
        string $kind,
        string $name,
        array $pendingAttrs,
        ?string $doc,
        array $context,
        string $namespace = '',
    ): array {
        $planned = $this->docTag($doc, self::PLANNED_TAG);

        if ($planned !== null) {
            return [Rule::PLANNED, $planned === '' ? null : $planned];
        }

        foreach (self::KEEP_TAGS as $tag) {
            $reason = $this->docTag($doc, $tag);

            if ($reason !== null) {
                return [Rule::KEEP, $reason === '' ? $tag : $reason];
            }
        }

        if (in_array('guard', $context, true)) {
            return [Rule::CONDITIONAL, (new Catalogue())->get('explain.orphan.guard')];
        }

        foreach ($pendingAttrs as $attribute) {
            if (in_array($attribute, self::ENTRYPOINT_ATTRIBUTES, true)) {
                return [Rule::ENTRYPOINT, 'wired via #[' . $attribute . ']'];
            }
        }

        // Declared in a namespace the framework itself loads from. Keyed on the
        // namespace rather than the class name, so this stays a structural claim
        // about where the code lives — the same standard every other rule meets.
        foreach (self::ENTRYPOINT_NAMESPACES as $entrypoint) {
            if ($namespace === $entrypoint || str_ends_with($namespace, '\\' . $entrypoint)) {
                return [Rule::ENTRYPOINT, (new Catalogue())->get('explain.orphan.entrypoint', ['namespace' => $entrypoint])];
            }
        }

        // Test classes are entry points the runner discovers reflectively; an
        // unreferenced one is normal, not dead. PHPUnit/Pest discover *Test
        // classes by the class-name convention, which is more reliable than a
        // path match (fixtures and helpers also live under tests/).
        if ($kind === Symbol::KIND_CLASS && str_ends_with($name, 'Test')) {
            return [Rule::ENTRYPOINT, 'test class'];
        }

        return [null, null];
    }

    /**
     * Find $tag in a docblock and return the text following it, or null when the
     * tag is absent. The tag must open the docblock or start a line after the
     * leading `*` — an unanchored `str_contains` also fires on prose such as
     * "Unlike @api classes, this one is internal", silently suppressing a real
     * finding.
     */
    private function docTag(?string $doc, string $tag): ?string
    {
        if ($doc === null) {
            return null;
        }

        $pattern = '#(?:/\*\*|^[ \t]*\*)[ \t]*' . preg_quote($tag, '#') . '\b[ \t]*(.*)$#m';

        if (preg_match($pattern, $doc, $matches) !== 1) {
            return null;
        }

        return trim(rtrim(trim($matches[1]), '*/'));
    }

    /**
     * Does the condition starting at $from call one of the existence guards?
     * Scans only to the end of the `if (...)` condition.
     *
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isExistenceGuard(array $tokens, int $from): bool
    {
        return TokenCursor::untilBalanced(
            $tokens,
            $from,
            '(',
            ')',
            static fn(array|string $token, int $depth): ?bool => $depth > 0
                && !is_string($token)
                && $token[0] === T_STRING
                && in_array($token[1], self::EXISTENCE_GUARDS, true) ? true : null,
        ) ?? false;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function nextSignificant(array $tokens, int $from): ?int
    {
        $count = count($tokens);

        for ($i = $from; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_string($token)) {
                return $i;
            }

            if ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Does this token, following the `class` keyword, begin an anonymous class
     * declaration rather than the `::class` constant?
     *
     * @param array{0: int, 1: string, 2: int}|string $token
     */
    private function opensClassBody(array|string $token): bool
    {
        if (is_string($token)) {
            return $token === '{' || $token === '(';
        }

        return $token[0] === T_EXTENDS || $token[0] === T_IMPLEMENTS;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function skipToSemicolon(array $tokens, int $from): int
    {
        $count = count($tokens);

        for ($i = $from; $i < $count; $i++) {
            if ($tokens[$i] === ';') {
                return $i + 1;
            }
        }

        return $count;
    }

    /**
     * @param array{0: int, 1: string, 2: int}|string $token
     */
    private function tokenName(array|string $token): string
    {
        return is_string($token) ? $token : $token[1];
    }

    private function shortName(string $qualified): string
    {
        $pos = strrpos($qualified, '\\');

        return $pos === false ? $qualified : substr($qualified, $pos + 1);
    }
}
