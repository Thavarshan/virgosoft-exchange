<?php

namespace App\Actions\Trading;

use App\Models\Order;
use App\Models\User;

/**
 * The locked records and execution terms for one full order match.
 */
final readonly class OrderMatch
{
    /**
     * @param  numeric-string  $executionPrice
     */
    public function __construct(
        public Order $buyOrder,
        public Order $sellOrder,
        public User $buyer,
        public User $seller,
        public string $executionPrice,
    ) {}
}
