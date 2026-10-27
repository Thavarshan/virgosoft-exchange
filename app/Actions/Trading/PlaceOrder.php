<?php

namespace App\Actions\Trading;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderOpened;
use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlaceOrder
{
    public function __construct(
        protected readonly CalculateOrderReservation $calculateOrderReservation,
        protected readonly ReserveUsd $reserveUsd,
        protected readonly ReserveAsset $reserveAsset,
        protected readonly LockUser $lockUser,
        protected readonly AcquireMarketLock $acquireMarketLock,
    ) {}

    /**
     * Place an order and reserve its funds atomically.
     */
    public function execute(User $user, OrderData $orderData): Order
    {
        // The transaction keeps balance reservation and order creation atomic.
        return DB::transaction(
            fn (): Order => $this->placeOrder($user, $orderData),
            attempts: 3,
        );
    }

    /**
     * Lock the owner, reserve the appropriate funds, and create the order.
     */
    protected function placeOrder(User $user, OrderData $orderData): Order
    {
        // Serialize the whole market mutation before acquiring user locks.
        $this->acquireMarketLock->execute($orderData->symbol);
        $lockedUser = $this->lockUser->execute($user);
        $reservedAmount = $orderData->amount;

        if ($orderData->side === OrderSide::SELL) {
            $this->reserveAsset->execute(
                $lockedUser,
                $orderData->symbol,
                $orderData->amount,
            );
        } else {
            $reservedAmount = $this->calculateOrderReservation->execute(
                $orderData->price,
                $orderData->amount,
            );
            $this->reserveUsd->execute($lockedUser, $reservedAmount);
        }

        $order = $this->createOrder($lockedUser, $orderData);

        // The synchronous listener matches before this transaction commits.
        OrderPlaced::dispatch($order);

        // Only a surviving open order should receive a placement receipt.
        $order->refresh();

        if ($order->status === OrderStatus::OPEN) {
            OrderOpened::dispatch($order, $reservedAmount);
        }

        return $order;
    }

    /**
     * Create an open buy order owned by the locked user.
     */
    protected function createOrder(User $user, OrderData $orderData): Order
    {
        // Ownership, side, and status are derived here rather than trusted.
        $order = new Order([
            'symbol' => $orderData->symbol,
            'side' => $orderData->side,
            'price' => $orderData->price,
            'amount' => $orderData->amount,
        ]);
        $order->status = OrderStatus::OPEN;
        $order->user()->associate($user);
        $order->save();

        return $order;
    }
}
