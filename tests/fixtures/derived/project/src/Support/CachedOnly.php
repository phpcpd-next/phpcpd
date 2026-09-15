<?php

declare(strict_types=1);

namespace Demo\Derived\Support;

/**
 * Nothing in this project's own source mentions this class. The only file that
 * names it is the compiled container blob under storage/framework, which the
 * product's default excludes never scan — so under ruling V it is unwired, and
 * before ruling V it was wired by a file that is not program text.
 */
final class CachedOnly
{
    public function handle(): string
    {
        return 'cached';
    }
}
