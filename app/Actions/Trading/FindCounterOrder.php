<?php

namespace App\Actions\Trading;

use App\Enums\OrderSide;
use App\Models\Order;

class FindCounterOrder
{
    /**
     * Find and lock the oldest compatible full-sized counter-order.
     */
    public function execute(Order $order): ?Order
    {
        $counterSide = $order->side === OrderSide::BUY
            ? OrderSide::SELL
            : OrderSide::BUY;

        $query = Order::query()
            ->forSymbol($order->symbol)
            ->open()
            ->where('side', $counterSide)
            ->where('user_id', '!=', $order->user_id)
            ->where('amount', $order->amount);

        if ($order->side === OrderSide::BUY) {
            $query->where('price', '<=', $order->price);
        } else {
            $query->where('price', '>=', $order->price);
        }

        return $query
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }
}
