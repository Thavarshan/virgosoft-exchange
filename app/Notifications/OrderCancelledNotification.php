<?php

namespace App\Notifications;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Mail\OrderCancelledMail;
use Illuminate\Mail\Mailable;

final class OrderCancelledNotification extends TradingNotification
{
    /**
     * Create a cancellation receipt with the reservation released.
     *
     * @param  numeric-string  $price
     * @param  numeric-string  $amount
     * @param  numeric-string  $releasedReservation
     */
    public function __construct(
        public readonly string $accountName,
        public readonly string $accountEmail,
        public readonly bool $isDemo,
        public readonly AssetSymbol $symbol,
        public readonly OrderSide $side,
        public readonly string $price,
        public readonly string $amount,
        public readonly string $releasedReservation,
    ) {
        parent::__construct();
    }

    /**
     * Build the cancellation email from the reservation released by the action.
     */
    public function toMail(object $notifiable): Mailable
    {
        return (new OrderCancelledMail(
            accountName: $this->accountName,
            accountEmail: $this->accountEmail,
            isDemo: $this->isDemo,
            symbol: $this->symbol,
            side: $this->side,
            price: $this->price,
            amount: $this->amount,
            releasedReservation: $this->releasedReservation,
        ))->to($this->recipient($notifiable));
    }
}
