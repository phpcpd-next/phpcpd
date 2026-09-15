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
 * Marker for every exception this package throws, so an embedder can catch the
 * tool's failures with one clause and let everything else propagate.
 */
interface Exception extends \Throwable {}
