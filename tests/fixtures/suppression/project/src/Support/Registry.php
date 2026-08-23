<?php

namespace Demo\App\Support;

final class Registry
{
    public function make(): string
    {
        return 'Dynamic';
    }
}
