<?php

namespace App\Actions\Trading;

use App\Enums\OrderStatus;
use App\Models\Order;

class FillMatchedOrders
{
    /**
     * Mark both fully matched orders as filled.
     */
    public function execute(Order $buyOrder, Order $sellOrder): void
    {
        $buyOrder->status = OrderStatus::FILLED;
        $buyOrder->save();
        $sellOrder->status = OrderStatus::FILLED;
        $sellOrder->save();
    }
}
