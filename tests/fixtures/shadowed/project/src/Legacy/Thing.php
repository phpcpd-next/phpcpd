<?php

declare(strict_types=1);

namespace Legacy;

/**
 * Negative 5, first half — `Legacy\` is not in the manifest's autoload map at
 * all, so the autoloader maps neither this file nor its copy and the rung has no
 * grounds to prefer one. Both kept: a project may include such a file by hand,
 * and triage never guesses.
 */
final class Thing
{
    public function id(): string
    {
        return 'thing';
    }
}
