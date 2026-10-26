<?php

namespace App\Actions\Trading;

use App\Models\User;

/**
 * Return a USD reservation to the user's available funds.
 */
final class ReleaseUsd
{
    /**
     * @param  numeric-string  $amount
     */
    public function execute(User $user, string $amount): void
    {
        $user->balance = bcadd($user->balance, $amount, 2);
        $user->locked_balance = bcsub($user->locked_balance, $amount, 2);
        $user->save();
    }
}
