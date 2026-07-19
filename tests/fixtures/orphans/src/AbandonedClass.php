<?php

declare(strict_types=1);

namespace Demo;

// Nothing references this anywhere → a definite orphan.
final class AbandonedClass
{
    public function forgotten(): void
    {
    }
}
