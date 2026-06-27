<?php

declare(strict_types=1);

function calculateDiscount(float $price, int $quantity): float
{
    if ($quantity >= 100) {
        return $price * 0.80;
    }

    if ($quantity >= 50) {
        return $price * 0.90;
    }

    return $price;
}
