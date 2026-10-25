<?php

namespace App\Actions\Trading;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;

/**
 * Immutable, validated input for placing an order.
 */
final readonly class OrderData
{
    /**
     * @param  numeric-string  $price
     * @param  numeric-string  $amount
     */
    public function __construct(
        public AssetSymbol $symbol,
        public OrderSide $side,
        public string $price,
        public string $amount,
    ) {}
}
