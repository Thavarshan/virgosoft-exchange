<?php

namespace App\Actions\Trading;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderCancelled;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    public function __construct(
        protected readonly CalculateOrderReservation $calculateOrderReservation,
        protected readonly ReleaseUsd $releaseUsd,
        protected readonly ReleaseAsset $releaseAsset,
        protected readonly LockUser $lockUser,
        protected readonly AcquireMarketLock $acquireMarketLock,
    ) {}

    /**
     * Cancel an open order and release its reservation atomically.
     */
    public function execute(User $user, Order $order): Order
    {
        return DB::transaction(
            fn (): Order => $this->cancelWithinTransaction($user, $order),
            attempts: 3,
        );
    }

    /**
     * Lock the records involved in cancellation and apply each state change.
     */
    protected function cancelWithinTransaction(User $user, Order $order): Order
    {
        $this->acquireMarketLock->execute($order->symbol);
        $lockedUser = $this->lockUser->execute($user);
        $lockedOrder = $this->lockOrder($lockedUser, $order);

        $this->ensureOrderIsOpen($lockedOrder);
        $releasedReservation = $this->releaseReservation($lockedUser, $lockedOrder);
        $cancelledOrder = $this->markAsCancelled($lockedOrder);

        OrderCancelled::dispatch($cancelledOrder, $releasedReservation);

        return $cancelledOrder;
    }

    /**
     * Lock the user's order and prevent cross-user cancellation.
     */
    protected function lockOrder(User $user, Order $order): Order
    {
        return Order::query()
            ->whereBelongsTo($user)
            ->lockForUpdate()
            ->findOrFail($order->id);
    }

    /**
     * Reject cancellation once matching or another terminal transition occurred.
     */
    protected function ensureOrderIsOpen(Order $order): void
    {
        if ($order->status === OrderStatus::OPEN) {
            return;
        }

        throw ValidationException::withMessages([
            'order' => 'Only open orders can be cancelled.',
        ]);
    }

    /**
     * Release the currency or asset reservation belonging to the order.
     *
     * @return numeric-string
     */
    protected function releaseReservation(User $user, Order $order): string
    {
        if ($order->side === OrderSide::BUY) {
            $reservation = $this->calculateOrderReservation->execute(
                $order->price,
                $order->amount,
            );
            $this->releaseUsd->execute($user, $reservation);

            return $reservation;
        }

        $this->releaseAsset->execute(
            $user,
            $order->symbol,
            $order->amount,
        );

        return $order->amount;
    }

    /**
     * Persist the terminal status after its reservation has been released.
     */
    protected function markAsCancelled(Order $order): Order
    {
        $order->status = OrderStatus::CANCELLED;
        $order->save();

        return $order;
    }
}
