<?php

declare(strict_types=1);

namespace Demo\Derived;

use Demo\Derived\Support\Wired;

final class Boot
{
    public function run(): string
    {
        return (new Wired())->handle();
    }
}
