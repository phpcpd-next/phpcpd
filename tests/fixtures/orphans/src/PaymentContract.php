<?php

declare(strict_types=1);

namespace Demo;

// Unreferenced interface → possible orphan (external implementers may exist).
interface PaymentContract
{
    public function charge(int $cents): bool;
}
