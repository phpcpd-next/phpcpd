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

namespace LucianoPereira\PhpcpdNext\Log;

use LucianoPereira\PhpcpdNext\CodeCloneMap;

/**
 * A report writer. Every file-output format (PMD XML, JSON, SARIF) implements
 * this and serialises the format-neutral CodeCloneMap to its own format and file.
 */
interface Logger
{
    public function process(CodeCloneMap $clones): void;
}
