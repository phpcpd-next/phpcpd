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

use function crc32;

class Token extends AbstractToken
{
    public function __construct(
        int $tokenCode,
        string $tokenName,
        int $line,
        string $file,
        string $content,
    ) {
        $this->tokenCode = $tokenCode;
        $this->tokenName = $tokenName;
        $this->line      = $line;
        $this->content   = $content;
        $this->file      = $file;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->tokenName;
    }

    #[\Override]
    public function hashCode(): int
    {
        return crc32($this->content);
    }

    #[\Override]
    public function equals(AbstractToken $other): bool
    {
        return $other->hashCode() === $this->hashCode();
    }
}
