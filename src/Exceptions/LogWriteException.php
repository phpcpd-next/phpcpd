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

namespace LucianoPereira\PhpcpdNext;

/**
 * A report could not be written where it was asked to go.
 *
 * Raised after the scan, so the findings are already known and the console
 * report has already been printed; what failed is the record of them. The CLI
 * says so and exits non-zero, because a build step that asked for
 * `--log-sarif` and got no file has not done what it was asked, and the one
 * outcome it must never get is a silent success.
 */
final class LogWriteException extends \RuntimeException implements Exception {}
