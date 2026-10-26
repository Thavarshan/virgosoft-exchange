<?php

namespace App\Actions\Trading;

use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SettleSeller
{
    protected const USD_SCALE = 2;
    protected const ASSET_SCALE = 8;

    /**
     * Credit the seller and release the reserved asset quantity.
     *
     * @param  numeric-string  $tradeValue
     */
    public function execute(
        User $seller,
        Order $sellOrder,
        string $tradeValue,
    ): void {
        $asset = $this->lockReservedAsset($seller, $sellOrder);

        $seller->balance = bcadd(
            $seller->balance,
            $tradeValue,
            self::USD_SCALE,
        );
        $seller->save();

        $asset->locked_amount = bcsub(
            $asset->locked_amount,
            $sellOrder->amount,
            self::ASSET_SCALE,
        );
        $asset->save();
    }

    /**
     * Lock the seller's reserved asset row before settlement.
     */
    protected function lockReservedAsset(User $seller, Order $sellOrder): Asset
    {
        $asset = Asset::query()
            ->whereBelongsTo($seller)
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->first();

        if (
            $asset === null
            || bccomp(
                $asset->locked_amount,
                $sellOrder->amount,
                self::ASSET_SCALE,
            ) < 0
        ) {
            throw ValidationException::withMessages([
                'asset' => 'The reserved asset balance is invalid.',
            ]);
        }

        return $asset;
    }
}
