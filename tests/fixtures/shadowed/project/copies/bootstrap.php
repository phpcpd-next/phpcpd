<?php

declare(strict_types=1);

/**
 * Negative 4 — an unmapped file that declares nothing at all. "Every symbol it
 * declares is declared elsewhere" is vacuously true of a file with no symbols, so
 * without an explicit floor this script would be discarded by an empty premise.
 * Kept.
 */

require __DIR__ . '/Support/Ledger.php';

echo 'bootstrapped', PHP_EOL;
