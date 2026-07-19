<?php

declare(strict_types=1);

// A global function whose only caller is itself. The recursive call counts as a
// reference, so the detector must stay silent — deleting it would break the
// recursion. This pins the tool's safety bias: prefer a miss over a false alarm.
function countdown(int $n): int
{
    if ($n <= 0) {
        return 0;
    }

    return countdown($n - 1);
}
