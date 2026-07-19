<?php

declare(strict_types=1);

namespace Demo;

// Referenced by UsedService → live.
final class Helper
{
    public function greet(): string
    {
        return 'hi';
    }
}
