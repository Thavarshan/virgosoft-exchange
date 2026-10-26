<?php

namespace App\Actions\Trading;

/**
 * Round positive USD values upward to the currency's two decimal places.
 */
final class RoundCurrencyUp
{
    /**
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public function execute(string $amount): string
    {
        return bcround($amount, 2, \RoundingMode::PositiveInfinity);
    }
}
