<?php

declare(strict_types=1);

namespace Demo;

// Never referenced in code, but its name appears in a string literal (see
// UsedService::$widgets) that could feed a container or `new $class`.
// → possible orphan, not a safe delete.
final class DynamicWidget
{
    public function render(): string
    {
        return 'widget';
    }
}
