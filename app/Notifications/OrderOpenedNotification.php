<?php

namespace App\Notifications;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Mail\OrderOpenedMail;
use Illuminate\Mail\Mailable;

final class OrderOpenedNotification extends TradingNotification
{
    /**
     * Create a snapshot of an order that remains open after matching.
     *
     * @param  numeric-string  $price
     * @param  numeric-string  $amount
     * @param  numeric-string  $reservedAmount
     */
    public function __construct(
        public readonly string $accountName,
        public readonly string $accountEmail,
        public readonly bool $isDemo,
        public readonly AssetSymbol $symbol,
        public readonly OrderSide $side,
        public readonly string $price,
        public readonly string $amount,
        public readonly string $reservedAmount,
    ) {
        parent::__construct();
    }

    /**
     * Build the opened-order email from the reservation captured at placement.
     */
    public function toMail(object $notifiable): Mailable
    {
        return (new OrderOpenedMail(
            accountName: $this->accountName,
            accountEmail: $this->accountEmail,
            isDemo: $this->isDemo,
            symbol: $this->symbol,
            side: $this->side,
            price: $this->price,
            amount: $this->amount,
            reservedAmount: $this->reservedAmount,
        ))->to($this->recipient($notifiable));
    }
}
