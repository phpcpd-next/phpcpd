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

use function getcwd;
use function realpath;
use function rtrim;
use function str_starts_with;
use function strlen;
use function substr;

use const DIRECTORY_SEPARATOR;

/**
 * How a file is named in a report.
 *
 * The reports used to pass through whatever path the scan was handed, so the
 * same scan described itself two ways depending on how the command was typed:
 *
 *     phpcpd src          →  path="src/Detector/Detector.php"
 *     phpcpd /repo/src    →  path="/repo/src/Detector/Detector.php"
 *
 * Same clones, same lines, two different documents. That is a problem for the
 * readers these formats exist for: SonarQube resolves a path against the project
 * base directory, Jenkins against the workspace, and a report written inside a
 * container with absolute paths names files that do not exist on the machine
 * reading it.
 *
 * So a report says where a file is relative to the directory the tool was run
 * from — the repository root, in every CI layout — and a path outside that
 * directory stays absolute, because there is nothing honest to make it relative
 * to. The scan root is not the base: `phpcpd src` has the scan root `…/src`, and
 * relativising against it would emit `Detector/Detector.php`, dropping the one
 * segment a reader needs to find the file at all.
 */
final readonly class ReportPath
{
    private function __construct(private string $base) {}

    /** Paths are named relative to where the tool was run. */
    public static function fromWorkingDirectory(): self
    {
        $cwd = getcwd();

        return new self($cwd === false ? '' : rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    /** For tests, and for a caller that knows better than the process does. */
    public static function relativeTo(string $base): self
    {
        return new self(rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    public function of(string $path): string
    {
        if ($this->base === '') {
            return $path;
        }

        $absolute = realpath($path);

        // A path that cannot be resolved is reported as it was given. The file
        // may have been deleted between the scan and the report, and inventing
        // a location for it would be worse than repeating what was scanned.
        if ($absolute === false) {
            $absolute = $path;
        }

        if (!str_starts_with($absolute, $this->base)) {
            return $absolute;
        }

        return substr($absolute, strlen($this->base));
    }
}
