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

use function mb_check_encoding;
use function mb_convert_encoding;
use function mb_internal_encoding;
use function preg_replace;

use LucianoPereira\PhpcpdNext\LogWriteException;
use LucianoPereira\PhpcpdNext\Presentation\Findings;

/**
 * Shared plumbing for the XML-family reports: a document to build into and the
 * two things that are the same whatever the schema — getting a source file's
 * bytes safely into a node, and getting the document onto disk.
 *
 * The per-format element building stays in each subclass. Mirrors how
 * {@see AbstractJsonLogger} factors the JSON/SARIF loggers.
 */
abstract class AbstractXmlLogger implements Logger
{
    protected readonly \DOMDocument $document;
    protected readonly ReportPath $path;
    private readonly LogFile $file;

    public function __construct(string $filename, ?ReportPath $path = null)
    {
        $this->path                   = $path ?? ReportPath::fromWorkingDirectory();
        $this->file                   = new LogFile($filename);
        $this->document               = new \DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;
    }

    abstract public function process(Findings $findings): void;

    /** @throws LogWriteException */
    protected function flush(): void
    {
        $this->file->write((string) $this->document->saveXML());
    }

    /**
     * Make a string safe to put in a DOM node.
     *
     * Two separate hazards, and only one of them is DOM's problem. Escaping is:
     * the document escapes `&` and `<` on its own, which is why no
     * `htmlspecialchars` is needed here and why the hand-rolled escaper this
     * replaced was doing the job twice. Encoding is not: a source file that is
     * Latin-1, or that carries a NUL or a stray C0 control, holds code points
     * that XML 1.0 forbids *even escaped*, and a document containing one is not
     * ill-formatted but unparseable. Since the strings here are lines of
     * somebody's source, the scanner meets both regularly.
     *
     * So the bytes are brought to UTF-8 first, and anything XML still cannot
     * represent becomes U+FFFD — the replacement character, which says a
     * character was here and could not be carried, rather than dropping it and
     * silently changing the line the report is quoting.
     */
    protected function sanitizeForXml(string $string): string
    {
        if (!mb_check_encoding($string, 'UTF-8')) {
            $converted = mb_convert_encoding($string, 'UTF-8', mb_internal_encoding());
            $string    = $converted !== false ? $converted : $string;
        }

        return preg_replace(
            '/[^\x09\x0A\x0D\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u',
            "\xEF\xBF\xBD",
            $string,
        ) ?? $string;
    }
}
