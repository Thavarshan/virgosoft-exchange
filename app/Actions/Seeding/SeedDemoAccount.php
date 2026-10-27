<?php

namespace App\Actions\Seeding;

use App\Actions\Wallet\InitializeWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SeedDemoAccount
{
    /**
     * Create the seeder with the shared wallet initializer.
     */
    public function __construct(
        protected readonly InitializeWallet $initializeWallet,
    ) {}

    /**
     * Create a demo account and initialize only newly created financial rows.
     *
     * Existing balances and assets are deliberately left untouched so rerunning
     * local seeders cannot invalidate active reservations or orders.
     *
     * @param  numeric-string  $balance
     * @param  array<string, numeric-string>  $assetBalances
     */
    public function execute(
        string $name,
        string $email,
        string $password,
        string $balance,
        array $assetBalances,
    ): User {
        return DB::transaction(function () use (
            $name,
            $email,
            $password,
            $balance,
            $assetBalances,
        ): User {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'email_verified_at' => now(),
                    'is_demo' => true,
                ],
            );

            if (! $user->is_demo) {
                $user->forceFill(['is_demo' => true])->save();
            }

            $this->initializeWallet->execute(
                user: $user,
                usdBalance: $balance,
                assetBalances: $assetBalances,
                initializeUsdBalance: $user->wasRecentlyCreated,
            );

            return $user;
        });
    }
}
