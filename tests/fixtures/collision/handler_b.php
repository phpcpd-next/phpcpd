<?php

declare(strict_types=1);

// Identical to its sibling but for the string literal below, whose crc32 is the
// same as the sibling's. Both literals occur in WordPress. See
// tests/SignatureCollisionTest.php.
function handler(array $in): array
{
    $out = [];
    $key = 'Antananarivo';

    foreach ($in as $k => $v) {
        if ($k === $key) {
            continue;
        }

        $out[$k] = trim((string) $v);

        if ($out[$k] === '') {
            unset($out[$k]);
        }
    }

    ksort($out);

    return $out;
}
