<?php

namespace App\Actions\Wallet;

use App\Enums\AssetSymbol;
use App\Models\User;

class InitializeWallet
{
    /**
     * Set an initial USD balance when requested and create missing asset rows.
     *
     * Existing wallet rows are preserved so a rerun cannot erase reservations.
     *
     * @param  numeric-string  $usdBalance
     * @param  array<string, numeric-string>  $assetBalances
     */
    public function execute(
        User $user,
        string $usdBalance,
        array $assetBalances,
        bool $initializeUsdBalance,
    ): void {
        if ($initializeUsdBalance) {
            $user->balance = $usdBalance;
            $user->locked_balance = '0.00';
            $user->save();
        }

        foreach ($assetBalances as $symbol => $amount) {
            $asset = $user->assets()->firstOrNew([
                'symbol' => AssetSymbol::from($symbol),
            ]);

            if ($asset->exists) {
                continue;
            }

            $asset->amount = $amount;
            $asset->locked_amount = '0.00000000';
            $asset->save();
        }
    }
}
