<?php

namespace App\Actions\Trading;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    public function __construct(
        private readonly CalculateOrderCost $calculateOrderCost,
    ) {}

    /**
     * Place an order and reserve its funds atomically.
     *
     * @param  array{
     *     symbol: string,
     *     side: string,
     *     price: numeric-string,
     *     amount: numeric-string,
     * }  $attributes
     */
    public function execute(User $user, array $attributes): Order
    {
        // The transaction keeps balance reservation and order creation atomic.
        return DB::transaction(
            fn (): Order => $this->placeOrder($user, $attributes),
        );
    }

    /**
     * Lock the owner, reserve the appropriate funds, and create the order.
     *
     * @param  array{
     *     symbol: string,
     *     side: string,
     *     price: numeric-string,
     *     amount: numeric-string,
     * }  $attributes
     */
    protected function placeOrder(User $user, array $attributes): Order
    {
        $lockedUser = $this->lockUser($user);

        if ($attributes['side'] === OrderSide::SELL->value) {
            $this->reserveAsset($lockedUser, $attributes);
        } else {
            $cost = $this->calculateOrderCost->execute(
                $attributes['price'],
                $attributes['amount'],
            );
            $this->reserveBalance($lockedUser, $cost);
        }

        return $this->createOrder($lockedUser, $attributes);
    }

    /**
     * Reload and lock the user row for the duration of the transaction.
     */
    protected function lockUser(User $user): User
    {
        // The row lock serializes concurrent reservations for this user.
        return User::query()
            ->lockForUpdate()
            ->findOrFail($user->id);
    }

    /**
     * Move the order cost from available to locked USD balance.
     *
     * @param  numeric-string  $cost
     */
    protected function reserveBalance(User $user, string $cost): void
    {
        if (bccomp($user->balance, $cost, 2) < 0) {
            throw ValidationException::withMessages([
                'balance' => 'Insufficient available balance.',
            ]);
        }

        // Only update balances after the funds check has succeeded.
        $user->balance = bcsub($user->balance, $cost, 2);
        $user->locked_balance = bcadd($user->locked_balance, $cost, 2);
        $user->save();
    }

    /**
     * Lock the user's asset row and reserve the requested quantity.
     *
     * @param  array{symbol: string, amount: numeric-string}  $attributes
     */
    protected function reserveAsset(User $user, array $attributes): void
    {
        $asset = Asset::query()
            ->whereBelongsTo($user)
            ->where('symbol', $attributes['symbol'])
            ->lockForUpdate()
            ->first();

        if ($asset === null || bccomp($asset->amount, $attributes['amount'], 8) < 0) {
            throw ValidationException::withMessages([
                'asset' => 'Insufficient available asset balance.',
            ]);
        }

        // Move the quantity only after the locked row passes the funds check.
        $asset->amount = bcsub($asset->amount, $attributes['amount'], 8);
        $asset->locked_amount = bcadd(
            $asset->locked_amount,
            $attributes['amount'],
            8,
        );
        $asset->save();
    }

    /**
     * Create an open buy order owned by the locked user.
     *
     * @param  array{
     *     symbol: string,
     *     side: string,
     *     price: numeric-string,
     *     amount: numeric-string,
     * }  $attributes
     */
    protected function createOrder(User $user, array $attributes): Order
    {
        // Ownership, side, and status are derived here rather than trusted.
        return tap(new Order([
            'symbol' => $attributes['symbol'],
            'side' => OrderSide::from($attributes['side']),
            'price' => $attributes['price'],
            'amount' => $attributes['amount'],
        ]), function (Order $order) use ($user): void {
            $order->status = OrderStatus::OPEN;
            $order->user()->associate($user);
            $order->save();
        });
    }
}
