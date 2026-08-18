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
use const T_IMPLEMENTS;
use const T_INTERFACE;
use const T_NAMESPACE;
use const T_NAME_FULLY_QUALIFIED;
use const T_NAME_QUALIFIED;
use const T_NAME_RELATIVE;
use const T_STRING;
use const T_TRAIT;
use const T_USE;
use const T_WHITESPACE;

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
     * Docblock tags that declare a symbol intentionally public / kept. Harvested
     * from Psalm's `@psalm-api` and shipmonk's `@api` entry-point marker, plus a
     * project-native escape hatch.
     *
     * @var list<string>
     */
    private const array KEEP_TAGS = [
        '@api', '@psalm-api', '@phpstan-api', '@phpcpd-keep', '@phpcpd-ignore-orphan',
    ];

    /** @param list<string> $files */
    public function collect(array $files): CollectedSymbols
    {
        $definitions = [];
        $references  = [];
        $stringNames = [];

        foreach ($files as $file) {
            $buffer = file_get_contents($file);

            if ($buffer === false) {
                continue;
            }

            $this->collectFile($file, $buffer, $definitions, $references, $stringNames);
        }

        return new CollectedSymbols($definitions, $references, $stringNames);
    }

    /**
     * @param list<Symbol>        $definitions
     * @param array<string, int>  $references
     * @param array<string, bool> $stringNames
     */
    private function collectFile(
        string $file,
        string $buffer,
        array &$definitions,
        array &$references,
        array &$stringNames,
    ): void {
        $tokens = token_get_all($buffer);
        $count  = count($tokens);

        $namespace     = '';
        $lastDoc       = null;
        $pendingAttrs  = [];
        $abstract      = false;
        $context       = [];      // stack of body kinds: 'type' | 'other'
        $pendingBlock  = null;    // what the next '{' opens
        $aliases       = [];      // alias short name => imported short name
        $localRefs     = [];      // names referenced in *this* file

        $i = 0;

        while ($i < $count) {
            $token = $tokens[$i];

            if (is_string($token)) {
                if ($token === '{') {
                    $context[]    = $pendingBlock ?? 'other';
                    $pendingBlock = null;
                } elseif ($token === '}') {
                    array_pop($context);
                } elseif ($token === ';') {
                    // Statement boundary: a docblock or attribute that did not
                    // bind to a declaration must not leak onto the next one.
                    $lastDoc      = null;
                    $pendingAttrs = [];
                    $abstract     = false;
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

                    if (($useNext === null || $tokens[$useNext] !== '(') && end($context) !== 'type') {
                        $end = $this->skipToSemicolon($tokens, $i + 1);
                        $this->collectImportAliases($tokens, $i + 1, $end, $aliases);
                        $i = $end;
                        continue 2;
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
                        $name = $tokens[$nameIndex][1];

                        $definitions[] = new Symbol(
                            kind:       $this->kindFor($id),
                            name:       $name,
                            fqn:        $namespace === '' ? $name : $namespace . '\\' . $name,
                            file:       $file,
                            line:       $token[2],
                            abstract:   $abstract && $id === T_CLASS,
                            entrypoint: $this->entrypointReason($this->kindFor($id), $name, $pendingAttrs),
                            suppressed: $this->isSuppressed($lastDoc),
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
                            $name          = $tokens[$nameIndex][1];
                            $definitions[] = new Symbol(
                                kind:       Symbol::KIND_FUNCTION,
                                name:       $name,
                                fqn:        $namespace === '' ? $name : $namespace . '\\' . $name,
                                file:       $file,
                                line:       $token[2],
                                entrypoint: $this->entrypointReason(Symbol::KIND_FUNCTION, $name, $pendingAttrs),
                                suppressed: $this->isSuppressed($lastDoc),
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
                    $this->recordStringName($text, $stringNames);
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
     * @param array<string, bool> $stringNames
     */
    private function recordStringName(string $literal, array &$stringNames): void
    {
        $value = trim($literal, "'\"");

        if ($value === '') {
            return;
        }

        $stringNames[$value]                  = true;
        $stringNames[$this->shortName($value)] = true;
    }

    /**
     * @param list<string> $pendingAttrs
     */
    private function entrypointReason(string $kind, string $name, array $pendingAttrs): ?string
    {
        foreach ($pendingAttrs as $attribute) {
            if (in_array($attribute, self::ENTRYPOINT_ATTRIBUTES, true)) {
                return 'wired via #[' . $attribute . ']';
            }
        }

        // Test classes are entry points the runner discovers reflectively; an
        // unreferenced one is normal, not dead. PHPUnit/Pest discover *Test
        // classes by the class-name convention, which is more reliable than a
        // path match (fixtures and helpers also live under tests/).
        if ($kind === Symbol::KIND_CLASS && str_ends_with($name, 'Test')) {
            return 'test class';
        }

        return null;
    }

    private function isSuppressed(?string $doc): bool
    {
        if ($doc === null) {
            return false;
        }

        foreach (self::KEEP_TAGS as $tag) {
            if (str_contains($doc, $tag)) {
                return true;
            }
        }

        return false;
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
