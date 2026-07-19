<?php

declare(strict_types=1);

namespace Demo;

/**
 * Application root, consumed by the framework kernel.
 *
 * @api
 */
final class UsedService
{
    /** @var array<string, string> */
    private array $widgets = [
        'default' => 'Demo\\DynamicWidget',
    ];

    public function run(): string
    {
        return (new Helper())->greet();
    }
}
