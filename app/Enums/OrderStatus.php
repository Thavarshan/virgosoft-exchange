<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * The lifecycle state of an order.
 */
enum OrderStatus: int
{
    use HasValues;

    case OPEN = 1;
    case FILLED = 2;
    case CANCELLED = 3;
}
