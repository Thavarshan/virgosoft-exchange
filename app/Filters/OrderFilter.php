<?php

namespace App\Filters;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Http\Requests\Api\OrderHistoryRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Validated criteria for narrowing an order-history query.
 */
final readonly class OrderFilter
{
    /**
     * @param  AssetSymbol|null  $symbol  The market to include.
     * @param  OrderSide|null  $side  The order direction to include.
     * @param  OrderStatus|null  $status  The lifecycle state to include.
     */
    public function __construct(
        protected ?AssetSymbol $symbol = null,
        protected ?OrderSide $side = null,
        protected ?OrderStatus $status = null,
    ) {}

    /**
     * Build filter criteria from the already validated HTTP request.
     */
    public static function fromRequest(OrderHistoryRequest $request): self
    {
        return new self(
            symbol: $request->symbol(),
            side: $request->side(),
            status: $request->status(),
        );
    }

    /**
     * Apply the selected criteria without changing ownership or pagination.
     *
     * @param  Builder<Order>|HasMany<Order, User>  $query
     * @return Builder<Order>|HasMany<Order, User>
     */
    public function apply(Builder|HasMany $query): Builder|HasMany
    {
        if ($this->symbol !== null) {
            $query->where('symbol', $this->symbol);
        }

        if ($this->side !== null) {
            $query->where('side', $this->side);
        }

        if ($this->status !== null) {
            $query->where('status', $this->status);
        }

        return $query;
    }
}
