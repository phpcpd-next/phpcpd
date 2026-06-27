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

namespace LucianoPereira\PhpcpdNext\Log;

use function file_put_contents;
use function mb_check_encoding;
use function mb_convert_encoding;
use function mb_internal_encoding;
use function preg_replace;

use LucianoPereira\PhpcpdNext\CodeCloneMap;

abstract class AbstractXmlLogger implements Logger
{
    protected readonly \DOMDocument $document;

    public function __construct(private readonly string $filename)
    {
        $this->document               = new \DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;
    }

    abstract public function process(CodeCloneMap $clones): void;

    protected function flush(): void
    {
        file_put_contents($this->filename, $this->document->saveXML());
    }

    /**
     * Make a string safe to drop into a DOM text/CDATA node: ensure UTF-8 and strip
     * code points that are illegal in XML 1.0 even when escaped (e.g. NUL, most C0
     * controls). DOM itself handles entity escaping, so no manual htmlspecialchars
     * is needed — that was the job of the hand-rolled escaper this replaced.
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
