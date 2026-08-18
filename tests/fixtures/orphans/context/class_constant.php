<?php

declare(strict_types=1);

namespace Demo\Context;

final class ClassConstantHost
{
    public function names(): array
    {
        return [ClosureCapture::class, self::class];
    }

    public function alsoAfterTheConstant(): void
    {
        $this->stillAMethod();
    }

    private function stillAMethod(): void
    {
    }
}
