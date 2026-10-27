<?php

namespace App\Listeners;

use App\Actions\Notifications\SendTradingNotification;
use App\Events\OrderOpened;
use App\Notifications\OrderOpenedNotification;

class NotifyOrderOpened
{
    /**
     * Create the listener with the centralized recipient policy.
     */
    public function __construct(
        protected readonly SendTradingNotification $sendTradingNotification,
    ) {}

    /**
     * Queue a receipt only for an order that remained open after matching.
     */
    public function handle(OrderOpened $event): void
    {
        $order = $event->order->loadMissing('user');
        $user = $order->user;

        $this->sendTradingNotification->execute(
            $user,
            new OrderOpenedNotification(
                accountName: $user->name,
                accountEmail: $user->email,
                isDemo: $user->is_demo,
                symbol: $order->symbol,
                side: $order->side,
                price: $order->price,
                amount: $order->amount,
                reservedAmount: $event->reservedAmount,
            ),
        );
    }
}
