<?php

namespace App\Actions\Trading;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelOrder
{
    private const USD_SCALE = 2;

    private const ASSET_SCALE = 8;

    public function __construct(
        private readonly CalculateOrderCost $calculateOrderCost,
    ) {}

    /**
     * Cancel an open order and release its reservation atomically.
     */
    public function execute(User $user, Order $order): Order
    {
        return DB::transaction(
            fn (): Order => $this->cancelWithinTransaction($user, $order),
        );
    }

    /**
     * Lock the records involved in cancellation and apply each state change.
     */
    protected function cancelWithinTransaction(User $user, Order $order): Order
    {
        $lockedUser = $this->lockUser($user);
        $lockedOrder = $this->lockOrder($lockedUser, $order);

        $this->ensureOrderIsOpen($lockedOrder);
        $this->releaseReservation($lockedUser, $lockedOrder);

        return $this->markAsCancelled($lockedOrder);
    }

    /**
     * Reload and lock the user row for the transaction.
     */
    protected function lockUser(User $user): User
    {
        return User::query()
            ->lockForUpdate()
            ->findOrFail($user->id);
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
     */
    protected function releaseReservation(User $user, Order $order): void
    {
        if ($order->side === OrderSide::BUY) {
            $this->releaseUsd($user, $order);

            return;
        }

        $this->releaseAsset($user, $order);
    }

    /**
     * Return a buy order's reserved USD to the available balance.
     */
    protected function releaseUsd(User $user, Order $order): void
    {
        $cost = $this->calculateOrderCost->execute(
            $order->price,
            $order->amount,
        );

        $user->balance = bcadd($user->balance, $cost, self::USD_SCALE);
        $user->locked_balance = bcsub(
            $user->locked_balance,
            $cost,
            self::USD_SCALE,
        );
        $user->save();
    }

    /**
     * Move the cancelled sell quantity back to the user's available asset.
     */
    protected function releaseAsset(User $user, Order $order): void
    {
        $asset = Asset::query()
            ->whereBelongsTo($user)
            ->where('symbol', $order->symbol->value)
            ->lockForUpdate()
            ->first();

        if (
            $asset === null
            || bccomp($asset->locked_amount, $order->amount, self::ASSET_SCALE) < 0
        ) {
            throw ValidationException::withMessages([
                'asset' => 'The reserved asset balance could not be released.',
            ]);
        }

        $asset->amount = bcadd(
            $asset->amount,
            $order->amount,
            self::ASSET_SCALE,
        );
        $asset->locked_amount = bcsub(
            $asset->locked_amount,
            $order->amount,
            self::ASSET_SCALE,
        );
        $asset->save();
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
