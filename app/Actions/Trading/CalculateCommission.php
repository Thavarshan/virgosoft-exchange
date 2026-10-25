<?php

namespace App\Actions\Trading;

class CalculateCommission
{
    protected const RATE = '0.015';

    public function __construct(
        protected readonly RoundCurrencyUp $roundCurrencyUp,
    ) {}

    /**
     * Calculate the buyer commission for a USD trade value.
     *
     * Commission is rounded up to the nearest cent so a positive trade is
     * never undercharged. This also establishes a one-cent minimum fee.
     *
     * @param  numeric-string  $tradeValue
     * @return numeric-string
     */
    public function execute(string $tradeValue): string
    {
        // Preserve the fifth decimal place so upward rounding sees values such
        // as 0.01005 instead of an already truncated 0.0100.
        $rawCommission = bcmul($tradeValue, self::RATE, 5);

        return $this->roundCurrencyUp->execute($rawCommission);
    }
}
