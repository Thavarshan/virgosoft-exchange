<?php

namespace App\Actions\Trading;

class CalculateOrderCost
{
    /**
     * Calculate an order's USD cost using exact decimal arithmetic.
     *
     * @return numeric-string
     */
    public function execute(string $price, string $amount): string
    {
        // BCMath avoids floating-point rounding during financial calculations.
        $rawCost = bcmul($price, $amount, 10);
        $wholeCents = bcdiv($rawCost, '0.01', 0);
        $roundedCost = bcdiv($wholeCents, '100', 2);

        // Round up so every order reserves at least one cent when needed.
        if (bccomp($rawCost, $roundedCost, 10) > 0) {
            return bcadd($roundedCost, '0.01', 2);
        }

        return $roundedCost;
    }
}
