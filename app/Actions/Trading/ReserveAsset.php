<?php

namespace App\Actions\Trading;

use App\Enums\AssetSymbol;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Move an exact asset quantity from available to locked holdings.
 */
final class ReserveAsset
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

        if ($asset === null || bccomp($asset->amount, $amount, 8) < 0) {
            throw ValidationException::withMessages([
                'asset' => 'Insufficient available asset balance.',
            ]);
        }

        $asset->amount = bcsub($asset->amount, $amount, 8);
        $asset->locked_amount = bcadd($asset->locked_amount, $amount, 8);
        $asset->save();
    }
}
