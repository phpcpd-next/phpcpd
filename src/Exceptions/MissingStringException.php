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

namespace LucianoPereira\PhpcpdNext\Exceptions;

use RuntimeException;

/**
 * A string was asked for that the catalogue does not hold.
 *
 * Raised rather than tolerated, because the alternative is a report printing a
 * key at a user, which no test asserts against and no reader can act on.
 */
final class MissingStringException extends RuntimeException {}
