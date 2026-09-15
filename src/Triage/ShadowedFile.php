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

namespace LucianoPereira\PhpcpdNext\Triage;

use function count;
use function implode;

/**
 * One file the autoloader can never reach, and the wired files that hold the same
 * declarations. Carried rather than merely counted, because triage is never
 * silent: a discarded file has to be able to say which name of its own is loaded
 * from where instead.
 */
final readonly class ShadowedFile
{
    /**
     * @param string       $file       the shadowed file, as the scan named it
     * @param list<string> $symbols    every fully-qualified name it declares, sorted
     * @param list<string> $shadowedBy the wired files those names are autoloaded
     *                                 from instead, sorted and unique
     */
    public function __construct(
        public string $file,
        public array $symbols,
        public array $shadowedBy,
    ) {}

    /**
     * The one-line explanation the verbose output prints beside the count. It
     * names the wired file rather than the shadowed one, since that is what the
     * reader needs to check the claim.
     */
    public function reason(): string
    {
        return count($this->symbols) . ' declared '
            . (count($this->symbols) === 1 ? 'symbol is' : 'symbols are')
            . ' autoloaded from ' . implode(', ', $this->shadowedBy);
    }
}
