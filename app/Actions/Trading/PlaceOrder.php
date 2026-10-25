<?php

namespace App\Actions\Trading;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    /**
     * Place a buy order and reserve its USD cost atomically.
     *
     * @param  array{
     *     symbol: string,
     *     price: numeric-string,
     *     amount: numeric-string,
     * }  $attributes
     */
    public function execute(User $user, array $attributes): Order
    {
        // The transaction keeps balance reservation and order creation atomic.
        return DB::transaction(
            fn (): Order => $this->placeBuyOrder($user, $attributes),
        );
    }

    /**
     * Lock the owner, reserve the USD cost, and create the order.
     *
     * @param  array{
     *     symbol: string,
     *     price: numeric-string,
     *     amount: numeric-string,
     * }  $attributes
     */
    protected function placeBuyOrder(User $user, array $attributes): Order
    {
        $lockedUser = $this->lockUser($user);
        $cost = $this->calculateCost($attributes);

        $this->reserveBalance($lockedUser, $cost);

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
     * Calculate the order's USD cost using exact decimal arithmetic.
     *
     * @param  array{price: numeric-string, amount: numeric-string}  $attributes
     * @return numeric-string
     */
    protected function calculateCost(array $attributes): string
    {
        // BCMath avoids floating-point rounding during financial calculations.
        $rawCost = bcmul($attributes['price'], $attributes['amount'], 10);
        $wholeCents = bcdiv($rawCost, '0.01', 0);
        $roundedCost = bcdiv($wholeCents, '100', 2);

        // Round up so every order reserves at least one cent when needed.
        if (bccomp($rawCost, $roundedCost, 10) > 0) {
            return bcadd($roundedCost, '0.01', 2);
        }

        return $roundedCost;
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
     * Create an open buy order owned by the locked user.
     *
     * @param  array{
     *     symbol: string,
     *     price: numeric-string,
     *     amount: numeric-string,
     * }  $attributes
     */
    protected function createOrder(User $user, array $attributes): Order
    {
        // Ownership, side, and status are derived here rather than trusted.
        return tap(new Order([
            'symbol' => $attributes['symbol'],
            'side' => OrderSide::BUY,
            'price' => $attributes['price'],
            'amount' => $attributes['amount'],
        ]), function (Order $order) use ($user): void {
            $order->status = OrderStatus::OPEN;
            $order->user()->associate($user);
            $order->save();
        });
    }
}
