<?php

namespace App\Actions\Trading;

use App\Enums\AssetSymbol;
use App\Models\Asset;
use App\Models\User;

/**
 * Credit a settled asset quantity to a user's available holdings.
 */
final class CreditAsset
{
    /**
     * @param  numeric-string  $amount
     */
    public function execute(User $user, AssetSymbol $symbol, string $amount): void
    {
        $asset = Asset::query()
            ->whereBelongsTo($user)
            ->where('symbol', $symbol)
            ->lockForUpdate()
            ->first();

        if ($asset === null) {
            $asset = new Asset([
                'symbol' => $symbol,
                'amount' => '0.00000000',
                'locked_amount' => '0.00000000',
            ]);
            $asset->user()->associate($user);
        }

        $asset->amount = bcadd($asset->amount, $amount, 8);
        $asset->save();
    }
}
