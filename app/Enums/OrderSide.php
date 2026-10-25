<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * The direction of an order in the market.
 */
enum OrderSide: string
{
    use HasValues;

    case BUY = 'buy';
    case SELL = 'sell';
}
