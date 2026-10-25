<?php

namespace App\Actions\Trading;

class CalculateOrderCost
{
    public function __construct(
        protected readonly RoundCurrencyUp $roundCurrencyUp,
    ) {}

    /**
     * Calculate an order's USD cost using exact decimal arithmetic.
     *
     * @param  numeric-string  $price
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public function execute(string $price, string $amount): string
    {
        // BCMath avoids floating-point rounding during financial calculations.
        $rawCost = bcmul($price, $amount, 10);

        // Round up so every order reserves at least one cent when needed.
        return $this->roundCurrencyUp->execute($rawCost);
    }
}
