<?php

namespace App\Models;

use App\Enums\AssetSymbol;
use Database\Factories\TradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $buy_order_id
 * @property int $sell_order_id
 * @property AssetSymbol $symbol
 * @property numeric-string $price
 * @property numeric-string $amount
 * @property numeric-string $trade_value
 * @property numeric-string $commission
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $buyOrder
 * @property-read Order $sellOrder
 */
#[Fillable([
    'buy_order_id',
    'sell_order_id',
    'symbol',
    'price',
    'amount',
    'trade_value',
    'commission',
])]
#[UseFactory(TradeFactory::class)]

class Trade extends Model
{
    /** @use HasFactory<TradeFactory> */
    use HasFactory;

    /**
     * Get the order that supplied the buy side of the trade.
     *
     * @return BelongsTo<Order, $this>
     */
    public function buyOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'buy_order_id');
    }

    /**
     * Get the order that supplied the sell side of the trade.
     *
     * @return BelongsTo<Order, $this>
     */
    public function sellOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'sell_order_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'commission' => 'decimal:2',
            'price' => 'decimal:2',
            'symbol' => AssetSymbol::class,
            'trade_value' => 'decimal:2',
        ];
    }
}
