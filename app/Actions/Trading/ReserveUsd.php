<?php

namespace App\Actions\Trading;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Move an exact USD reservation from available to locked funds.
 */
final class ReserveUsd
{
    /**
     * @param  numeric-string  $amount
     */
    public function execute(User $user, string $amount): void
    {
        if (bccomp($user->balance, $amount, 2) < 0) {
            throw ValidationException::withMessages([
                'balance' => 'Insufficient available balance.',
            ]);
        }

        $user->balance = bcsub($user->balance, $amount, 2);
        $user->locked_balance = bcadd($user->locked_balance, $amount, 2);
        $user->save();
    }
}
