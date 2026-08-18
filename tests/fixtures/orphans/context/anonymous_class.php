<?php

declare(strict_types=1);

namespace Demo\Context;

$plain = new class {
    use TraitUsedOnlyByAnonymousClass;

    public function methodOfAnonymousClass(): void
    {
    }
};

$withArgs = new class(1) {
    public function methodOfAnonymousClassWithArgs(): void
    {
    }
};

$extending = new class extends ClosureCapture {
    public function methodOfExtendingAnonymousClass(): void
    {
    }
};
