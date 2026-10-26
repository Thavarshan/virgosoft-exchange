<?php

namespace App\Actions\Trading;

use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SettleBuyer
{
    protected const USD_SCALE = 2;

    public function __construct(
        protected readonly CalculateCommission $calculateCommission,
        protected readonly CalculateOrderReservation $calculateOrderReservation,
        protected readonly CreditAsset $creditAsset,
    ) {
    }

    /**
     * Settle the buyer's USD reservation and credit the purchased asset.
     *
     * @param  numeric-string  $tradeValue
     * @return numeric-string The commission charged to the buyer.
     */
    public function execute(
        User $buyer,
        Order $buyOrder,
        string $tradeValue,
    ): string {
        $commission = $this->calculateCommission->execute($tradeValue);
        $this->settleUsd(
            $buyer,
            $buyOrder,
            $tradeValue,
            $commission,
        );
        $this->creditAsset->execute(
            $buyer,
            $buyOrder->symbol,
            $buyOrder->amount,
        );

        return $commission;
    }

    /**
     * Charge the executed trade and refund unused limit-price reservation.
     *
     * @param  numeric-string  $tradeValue
     * @param  numeric-string  $commission
     */
    protected function settleUsd(
        User $buyer,
        Order $buyOrder,
        string $tradeValue,
        string $commission,
    ): void {
        $reservation = $this->calculateOrderReservation->execute(
            $buyOrder->price,
            $buyOrder->amount,
        );
        $executionTotal = bcadd(
            $tradeValue,
            $commission,
            self::USD_SCALE,
        );

        if (
            bccomp($buyer->locked_balance, $reservation, self::USD_SCALE) < 0
            || bccomp($reservation, $executionTotal, self::USD_SCALE) < 0
        ) {
            throw ValidationException::withMessages([
                'balance' => 'The reserved buyer balance is invalid.',
            ]);
        }

        $refund = bcsub(
            $reservation,
            $executionTotal,
            self::USD_SCALE,
        );
        $buyer->balance = bcadd(
            $buyer->balance,
            $refund,
            self::USD_SCALE,
        );
        $buyer->locked_balance = bcsub(
            $buyer->locked_balance,
            $reservation,
            self::USD_SCALE,
        );
        $buyer->save();
    }
}
