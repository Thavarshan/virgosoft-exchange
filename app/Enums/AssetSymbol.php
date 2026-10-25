<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

/**
 * Supported cryptocurrency symbols.
 */
enum AssetSymbol: string
{
    use HasValues;

    case BTC = 'BTC';
    case ETH = 'ETH';
}
