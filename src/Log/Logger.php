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

use LucianoPereira\PhpcpdNext\Presentation\Findings;

/**
 * A report writer. Every file-output format (PMD XML, JSON, SARIF) implements
 * this and serialises the format-neutral findings to its own format and file.
 *
 * The unit is {@see Findings} rather than a bare `CodeCloneMap` because the
 * demote tag has to reach every format: a tag visible only in the console is a
 * tag a CI pipeline cannot act on, which is the file-list-granularity gap the M4
 * packet recorded and the M5 charter closes. Every format still reports every
 * finding — the tag says how confident the tool is, never whether to look.
 */
interface Logger
{
    public function process(Findings $findings): void;
}
