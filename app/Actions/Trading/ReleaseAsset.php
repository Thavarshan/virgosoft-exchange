<?php

namespace App\Actions\Trading;

use App\Enums\AssetSymbol;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Return a reserved asset quantity to available holdings.
 */
final class ReleaseAsset
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

        if ($asset === null || bccomp($asset->locked_amount, $amount, 8) < 0) {
            throw ValidationException::withMessages([
                'asset' => 'The reserved asset balance could not be released.',
            ]);
        }

        $asset->amount = bcadd($asset->amount, $amount, 8);
        $asset->locked_amount = bcsub($asset->locked_amount, $amount, 8);
        $asset->save();
    }
}
