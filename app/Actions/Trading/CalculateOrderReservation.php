<?php

namespace App\Actions\Trading;

class CalculateOrderReservation
{
    public function __construct(
        protected readonly CalculateOrderCost $calculateOrderCost,
        protected readonly CalculateCommission $calculateCommission,
    ) {}

    /**
     * Calculate the USD reservation required for a buy order.
     *
     * The reservation includes the rounded limit-price cost and the buyer's
     * commission so settlement cannot spend more than the locked funds.
     *
     * @param  numeric-string  $price
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public function execute(string $price, string $amount): string
    {
        $cost = $this->calculateOrderCost->execute($price, $amount);
        $commission = $this->calculateCommission->execute($cost);

        return bcadd($cost, $commission, 2);
    }
}
