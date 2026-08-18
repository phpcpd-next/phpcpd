<?php

declare(strict_types=1);

namespace Demo\Context;

final class Interpolation
{
    use TraitUsedAfterInterpolation;

    public function label(string $name): string
    {
        return "operator {$name} must be callable";
    }

    public function afterInterpolation(): void
    {
        $this->interpolationTail();
    }

    private function interpolationTail(): void
    {
    }
}
