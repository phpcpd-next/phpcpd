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

/*
 * Half of a table pair whose matching run sits WHOLLY INSIDE the array frame.
 * The rows at both ends of the table differ from the other half's in keys and
 * in values, and the two files' preambles differ, so the maximal common run is
 * the interior rows and nothing reachable outside the frame. That is stratum
 * D1's scope — the dead rule 9's scope, reused whole and with no floor — and
 * the pair is DEMOTED, never silenced.
 */

return [
    ['alpha' => 'one', 'beta' => 11, 'gamma' => 'red'],
    ['alpha' => 'two', 'beta' => 12, 'gamma' => 'blue'],
    ['code' => 'IT', 'prefix' => 39, 'name' => 'italy', 'active' => true],
    ['code' => 'JP', 'prefix' => 81, 'name' => 'japan', 'active' => true],
    ['code' => 'KR', 'prefix' => 82, 'name' => 'korea', 'active' => true],
    ['code' => 'CN', 'prefix' => 86, 'name' => 'china', 'active' => false],
    ['code' => 'DE', 'prefix' => 49, 'name' => 'germany', 'active' => true],
    ['code' => 'FR', 'prefix' => 33, 'name' => 'france', 'active' => true],
    ['code' => 'ES', 'prefix' => 34, 'name' => 'spain', 'active' => true],
    ['code' => 'PT', 'prefix' => 351, 'name' => 'portugal', 'active' => false],
    ['code' => 'NL', 'prefix' => 31, 'name' => 'holland', 'active' => true],
    ['code' => 'BE', 'prefix' => 32, 'name' => 'belgium', 'active' => true],
    ['code' => 'PL', 'prefix' => 48, 'name' => 'poland', 'active' => true],
    ['code' => 'SE', 'prefix' => 46, 'name' => 'sweden', 'active' => false],
    ['delta' => 'nine', 'epsilon' => 91],
    ['delta' => 'ten', 'epsilon' => 92],
];
