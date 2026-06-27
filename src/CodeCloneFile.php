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

namespace LucianoPereira\PhpcpdNext;

final readonly class CodeCloneFile
{
    public string $id;

    public function __construct(
        public string $name,
        public int $startLine,
    ) {
        $this->id = $this->name . ':' . $this->startLine;
    }

    public function id(): string
    {
        return $this->id;
    }
    public function name(): string
    {
        return $this->name;
    }
    public function startLine(): int
    {
        return $this->startLine;
    }
}
