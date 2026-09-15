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

use function error_get_last;
use function file_put_contents;
use function strlen;

use LucianoPereira\PhpcpdNext\LogWriteException;

/**
 * Where a report goes, and what happens when it cannot go there.
 *
 * `file_put_contents` returns false on a directory that does not exist, a path
 * that is not writable, or a full disk, and every logger here discarded that
 * return. So `phpcpd --log-sarif=build/report.sarif src` on a machine without a
 * `build/` directory printed its findings, wrote nothing, and exited 0 — and the
 * pipeline downstream read the missing file as a clean run.
 *
 * A short write is the same failure arriving later: the file exists, the XML is
 * truncated, and whatever parses it next is the thing that reports the problem.
 * Both are checked here, once, for every format.
 */
use LucianoPereira\PhpcpdNext\Strings\Catalogue;

final readonly class LogFile
{
    public function __construct(private string $path) {}

    /** @throws LogWriteException */
    public function write(string $contents): void
    {
        $written = @file_put_contents($this->path, $contents);

        if ($written === false) {
            $error = error_get_last();

            throw new LogWriteException((new Catalogue())->get('refuse.writeFailed.report', [
                'path'   => $this->path,
                'detail' => $error === null ? '' : ': ' . $error['message'],
            ]));
        }

        if ($written !== strlen($contents)) {
            throw new LogWriteException((new Catalogue())->get('refuse.writePartial.report', [
                'written' => $written,
                'total'   => strlen($contents),
                'path'    => $this->path,
            ]));
        }
    }
}
