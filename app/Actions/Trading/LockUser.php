<?php

namespace App\Actions\Trading;

use App\Models\User;

/**
 * Reload and lock a user row for an ongoing trading transaction.
 */
final class LockUser
{
    /**
     * Lock the persisted user record until the current transaction commits.
     */
    public function execute(User $user): User
    {
        return User::query()
            ->lockForUpdate()
            ->findOrFail($user->id);
    }

    /**
     * Lock a user by primary key when the model has not been loaded yet.
     */
    public function byId(int $userId): User
    {
        return User::query()
            ->lockForUpdate()
            ->findOrFail($userId);
    }
}
