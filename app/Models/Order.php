<?php

namespace App\Models;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property AssetSymbol $symbol
 * @property OrderSide $side
 * @property numeric-string $price
 * @property numeric-string $amount
 * @property OrderStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
// Status changes belong to matching and settlement, not client mass assignment.
#[Fillable(['user_id', 'symbol', 'side', 'price', 'amount'])]
#[UseFactory(OrderFactory::class)]

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'price' => 'decimal:2',
            'side' => OrderSide::class,
            'status' => OrderStatus::class,
            'symbol' => AssetSymbol::class,
        ];
    }

    /**
     * Scope a query to orders for the requested market symbol.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function forSymbol(Builder $query, AssetSymbol $symbol): void
    {
        $query->where('symbol', $symbol);
    }

    /**
     * Scope a query to orders that are still available in the order book.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', OrderStatus::OPEN);
    }

    /**
     * Scope a query to the newest orders first.
     *
     * The identifier makes records with identical timestamps deterministic.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function newestFirst(Builder $query): void
    {
        $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Apply price-time priority to an order-book query.
     *
     * Buy orders have priority over sell orders. Within each side, the
     * best price is selected first, followed by the oldest order and its
     * identifier as a deterministic tie-breaker.
     *
     * @param  Builder<Order>  $query
     */
    #[Scope]
    protected function orderBook(Builder $query): void
    {
        // Display bids before asks so the two sides of the market are clear.
        $query
            ->orderByRaw(
                'CASE WHEN side = ? THEN 0 ELSE 1 END',
                [OrderSide::BUY->value],
            )
            // Buyers offering more have priority over buyers offering less.
            ->orderByRaw(
                'CASE WHEN side = ? THEN price END DESC NULLS LAST',
                [OrderSide::BUY->value],
            )
            // Sellers accepting less have priority over sellers asking more.
            ->orderByRaw(
                'CASE WHEN side = ? THEN price END ASC NULLS LAST',
                [OrderSide::SELL->value],
            )
            // Equal prices use time priority, then a stable ID tie-breaker.
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Get the user that owns the order.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
