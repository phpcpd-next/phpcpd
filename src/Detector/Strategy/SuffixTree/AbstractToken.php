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

abstract class AbstractToken
{
    public int $tokenCode;
    public int $line;
    public string $file;
    public string $tokenName;
    public string $content;

    abstract public function __toString(): string;

    abstract public function hashCode(): int;

    abstract public function equals(self $other): bool;
}
