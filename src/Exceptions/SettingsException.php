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
 * A run could not be resolved from what the user provided — an unknown option,
 * an invalid value, an unreadable config file, a missing directory. Raised
 * before any file is scanned; the CLI prints the message and exits non-zero.
 */
final class SettingsException extends \RuntimeException implements Exception {}
