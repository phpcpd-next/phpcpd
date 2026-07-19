<?php

declare(strict_types=1);

namespace Demo;

use Symfony\Component\Console\Attribute\AsCommand;

// Unreferenced by name, but wired into the console via an attribute → an entry
// point, never reported as an orphan.
#[AsCommand(name: 'app:import')]
final class ImportCommand
{
    public function __invoke(): int
    {
        return 0;
    }
}
