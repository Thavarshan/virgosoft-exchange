<?php

namespace App\Notifications;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Mail\OrderMatchedMail;
use Illuminate\Mail\Mailable;

final class OrderMatchedNotification extends TradingNotification
{
    /**
     * Create a participant-specific snapshot of a completed match.
     *
     * @param  numeric-string  $orderPrice
     * @param  numeric-string  $amount
     * @param  numeric-string  $executionPrice
     * @param  numeric-string  $tradeValue
     * @param  numeric-string  $commission
     */
    public function __construct(
        public readonly string $accountName,
        public readonly string $accountEmail,
        public readonly bool $isDemo,
        public readonly AssetSymbol $symbol,
        public readonly OrderSide $side,
        public readonly string $orderPrice,
        public readonly string $amount,
        public readonly string $executionPrice,
        public readonly string $tradeValue,
        public readonly string $commission,
    ) {
        parent::__construct();
    }

    /**
     * Build the fill receipt from the committed settlement values.
     */
    public function toMail(object $notifiable): Mailable
    {
        return (new OrderMatchedMail(
            accountName: $this->accountName,
            accountEmail: $this->accountEmail,
            isDemo: $this->isDemo,
            symbol: $this->symbol,
            side: $this->side,
            orderPrice: $this->orderPrice,
            amount: $this->amount,
            executionPrice: $this->executionPrice,
            tradeValue: $this->tradeValue,
            commission: $this->commission,
        ))->to($this->recipient($notifiable));
    }
}
