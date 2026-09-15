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

use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use LucianoPereira\PhpcpdNext\LogWriteException;

/**
 * Shared plumbing for the JSON-family reports (Json, Sarif): both project the
 * same CodeCloneMap into a different array shape and then serialize it with
 * identical encoder flags. The per-format array building stays in each subclass;
 * only the constructor and the encode-and-write tail live here. Mirrors how
 * {@see AbstractXmlLogger} factors the PMD/DOM loggers.
 */
abstract class AbstractJsonLogger implements Logger
{
    protected readonly ReportPath $path;
    private readonly LogFile $file;

    public function __construct(protected readonly string $filename, ?ReportPath $path = null)
    {
        $this->path = $path ?? ReportPath::fromWorkingDirectory();
        $this->file = new LogFile($filename);
    }

    /**
     * @param array<string, mixed> $document
     * @throws LogWriteException
     */
    protected function write(array $document): void
    {
        $this->file->write(
            json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );
    }
}
