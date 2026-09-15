<?php

declare(strict_types=1);

namespace Demo\Shadow\Support;

/**
 * Negative 2, first half — the same name lives at lib/Support/Twin.php, and the
 * manifest maps `Demo\Shadow\` to BOTH src/ and lib/. The autoloader can reach
 * either file, so "only one of the two" is false and neither is shadowed. This
 * project's own manifest has the same shape (three directories under one
 * prefix), which is why the case is a fixture rather than a hypothetical.
 */
final class Twin
{
    public function name(): string
    {
        return 'twin';
    }
}
