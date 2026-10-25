<?php

namespace App\Actions\Seeding;

use App\Enums\AssetSymbol;
use App\Models\User;

class SeedDemoAccount
{
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
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        if ($user->wasRecentlyCreated) {
            $user->balance = $balance;
            $user->locked_balance = '0.00';
            $user->save();
        }

        foreach ($assetBalances as $symbol => $amount) {
            $this->initializeAsset(
                $user,
                AssetSymbol::from($symbol),
                $amount,
            );
        }

        return $user;
    }

    /**
     * Create an available asset balance only when the user lacks that asset.
     *
     * @param  numeric-string  $amount
     */
    private function initializeAsset(
        User $user,
        AssetSymbol $symbol,
        string $amount,
    ): void {
        $asset = $user->assets()->firstOrNew(['symbol' => $symbol]);

        if ($asset->exists) {
            return;
        }

        $asset->amount = $amount;
        $asset->locked_amount = '0.00000000';
        $asset->save();
    }
}
