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
 * Half of a pair of *different* seeders carrying the same rows. Two different
 * runs agreeing is a copy someone could remove, and the M5 rating round called
 * that shape genuine duplication, so it stays asserted.
 */

function seed_rows_a(): array
{
    $rows = [];
    $rows[] = ['code' => 'X01', 'name' => 'Row 01', 'symbol' => 'Y01', 'places' => 2];
    $rows[] = ['code' => 'X02', 'name' => 'Row 02', 'symbol' => 'Y02', 'places' => 2];
    $rows[] = ['code' => 'X03', 'name' => 'Row 03', 'symbol' => 'Y03', 'places' => 2];
    $rows[] = ['code' => 'X04', 'name' => 'Row 04', 'symbol' => 'Y04', 'places' => 2];
    $rows[] = ['code' => 'X05', 'name' => 'Row 05', 'symbol' => 'Y05', 'places' => 2];
    $rows[] = ['code' => 'X06', 'name' => 'Row 06', 'symbol' => 'Y06', 'places' => 2];
    $rows[] = ['code' => 'X07', 'name' => 'Row 07', 'symbol' => 'Y07', 'places' => 2];
    $rows[] = ['code' => 'X08', 'name' => 'Row 08', 'symbol' => 'Y08', 'places' => 2];
    $rows[] = ['code' => 'X09', 'name' => 'Row 09', 'symbol' => 'Y09', 'places' => 2];

    return $rows;
}
