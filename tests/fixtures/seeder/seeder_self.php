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
 * A table written as statements, repeating itself. The second run of rows is
 * not a copy anybody could edit out — it is the regularity that makes it a
 * table — which is the case ruling H draws the same way for array literals.
 */

function seed_currencies(): array
{
    $rows = [];
    $rows[] = ['code' => 'C01', 'name' => 'Currency 01', 'symbol' => 'S01', 'places' => 2];
    $rows[] = ['code' => 'C02', 'name' => 'Currency 02', 'symbol' => 'S02', 'places' => 2];
    $rows[] = ['code' => 'C03', 'name' => 'Currency 03', 'symbol' => 'S03', 'places' => 2];
    $rows[] = ['code' => 'C04', 'name' => 'Currency 04', 'symbol' => 'S04', 'places' => 2];
    $rows[] = ['code' => 'C05', 'name' => 'Currency 05', 'symbol' => 'S05', 'places' => 2];
    $rows[] = ['code' => 'C06', 'name' => 'Currency 06', 'symbol' => 'S06', 'places' => 2];
    $rows[] = ['code' => 'C07', 'name' => 'Currency 07', 'symbol' => 'S07', 'places' => 2];
    $rows[] = ['code' => 'C08', 'name' => 'Currency 08', 'symbol' => 'S08', 'places' => 2];
    $rows[] = ['code' => 'C09', 'name' => 'Currency 09', 'symbol' => 'S09', 'places' => 2];
    $rows[] = ['code' => 'C10', 'name' => 'Currency 10', 'symbol' => 'S10', 'places' => 2];
    $rows[] = ['code' => 'C11', 'name' => 'Currency 11', 'symbol' => 'S11', 'places' => 2];
    $rows[] = ['code' => 'C12', 'name' => 'Currency 12', 'symbol' => 'S12', 'places' => 2];
    $rows[] = ['code' => 'C13', 'name' => 'Currency 13', 'symbol' => 'S13', 'places' => 2];
    $rows[] = ['code' => 'C14', 'name' => 'Currency 14', 'symbol' => 'S14', 'places' => 2];
    $rows[] = ['code' => 'C15', 'name' => 'Currency 15', 'symbol' => 'S15', 'places' => 2];
    $rows[] = ['code' => 'C16', 'name' => 'Currency 16', 'symbol' => 'S16', 'places' => 2];
    $rows[] = ['code' => 'C17', 'name' => 'Currency 17', 'symbol' => 'S17', 'places' => 2];
    $rows[] = ['code' => 'C18', 'name' => 'Currency 18', 'symbol' => 'S18', 'places' => 2];
    $rows[] = ['code' => 'C19', 'name' => 'Currency 19', 'symbol' => 'S19', 'places' => 2];
    $rows[] = ['code' => 'C20', 'name' => 'Currency 20', 'symbol' => 'S20', 'places' => 2];
    $rows[] = ['code' => 'C21', 'name' => 'Currency 21', 'symbol' => 'S21', 'places' => 2];
    $rows[] = ['code' => 'C22', 'name' => 'Currency 22', 'symbol' => 'S22', 'places' => 2];
    $rows[] = ['code' => 'C23', 'name' => 'Currency 23', 'symbol' => 'S23', 'places' => 2];
    $rows[] = ['code' => 'C24', 'name' => 'Currency 24', 'symbol' => 'S24', 'places' => 2];
    $rows[] = ['code' => 'C25', 'name' => 'Currency 25', 'symbol' => 'S25', 'places' => 2];
    $rows[] = ['code' => 'C26', 'name' => 'Currency 26', 'symbol' => 'S26', 'places' => 2];
    $rows[] = ['code' => 'C27', 'name' => 'Currency 27', 'symbol' => 'S27', 'places' => 2];
    $rows[] = ['code' => 'C28', 'name' => 'Currency 28', 'symbol' => 'S28', 'places' => 2];
    $rows[] = ['code' => 'C29', 'name' => 'Currency 29', 'symbol' => 'S29', 'places' => 2];
    $rows[] = ['code' => 'C30', 'name' => 'Currency 30', 'symbol' => 'S30', 'places' => 2];

    return $rows;
}
