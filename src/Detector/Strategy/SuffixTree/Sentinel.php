<?php

declare(strict_types=1);
/*
 * This file is part of PHP Copy/Paste Detector (PHPCPD).
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LucianoPereira\PhpcpdNext\Detector\Strategy\SuffixTree;

use function random_int;

/**
 * A sentinel character which can be used to produce explicit leaves for all
 * suffixes. The sentinel just has to be appended to the list before handing
 * it to the suffix tree. For the sentinel equality and object identity are
 * the same!
 */
class Sentinel extends AbstractToken
{
    private int $hash;

    public function __construct()
    {
        $this->hash      = random_int(0, PHP_INT_MAX);
        $this->tokenCode = -1;
        $this->line      = -1;
        $this->file      = '<no file>';
        $this->tokenName = '<no token name>';
        $this->content   = '<no token content>';
    }

    #[\Override]
    public function __toString(): string
    {
        return '$';
    }

    #[\Override]
    public function hashCode(): int
    {
        return $this->hash;
    }

    #[\Override]
    public function equals(AbstractToken $other): bool
    {
        return $other instanceof self;
    }
}
