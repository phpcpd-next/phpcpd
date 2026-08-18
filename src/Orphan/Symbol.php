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

/**
 * A top-level declaration discovered while scanning — a class, interface,
 * trait, enum, or global function. This is the unit the orphan detector
 * reasons about: something that is *defined* and might never be *referenced*.
 *
 * We deliberately stop at the type/function level. Method- and property-level
 * dead code needs whole-program type inference (which class does `$this->foo()`
 * resolve to under inheritance and DI?) — that is PHPStan/Psalm's job and is
 * out of scope for a token-based, zero-dependency tool. What token analysis can
 * do *safely* is decide whether a named type or function is ever mentioned at
 * all, which is exactly the "unreferenced file" case phpunused targets.
 */
final readonly class Symbol
{
    public const string KIND_CLASS     = 'class';
    public const string KIND_INTERFACE = 'interface';
    public const string KIND_TRAIT     = 'trait';
    public const string KIND_ENUM      = 'enum';
    public const string KIND_FUNCTION  = 'function';

    public function __construct(
        public string $kind,
        public string $name,
        public string $fqn,
        public string $file,
        public int $line,
        public bool $abstract = false,
        public ?string $entrypoint = null,
        public bool $suppressed = false,
    ) {}

    /**
     * Interfaces, abstract classes, and traits are contracts: an unreferenced
     * one may still be implemented, extended, or used by code outside the
     * scanned set (a plugin, a downstream package). A trait in particular
     * exists to be consumed by *other* classes, so a library ships them for
     * consumers that are not in the scan at all. Reporting them as
     * *definitely* dead is unsafe, so they are demoted to the "possible" tier
     * — mirroring Psalm's split between UnusedClass and PossiblyUnusedClass.
     */
    public function isContract(): bool
    {
        return $this->kind === self::KIND_INTERFACE
            || $this->kind === self::KIND_TRAIT
            || $this->abstract;
    }
}
