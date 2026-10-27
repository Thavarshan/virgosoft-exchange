<?php

namespace App\Listeners;

use App\Actions\Notifications\SendTradingNotification;
use App\Events\OrderCancelled;
use App\Notifications\OrderCancelledNotification;

class NotifyOrderCancelled
{
    /**
     * Create the listener with the centralized recipient policy.
     */
    public function __construct(
        protected readonly SendTradingNotification $sendTradingNotification,
    ) {}

    /**
     * Queue a cancellation receipt with the amount actually released.
     */
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order->loadMissing('user');
        $user = $order->user;

        $this->sendTradingNotification->execute(
            $user,
            new OrderCancelledNotification(
                accountName: $user->name,
                accountEmail: $user->email,
                isDemo: $user->is_demo,
                symbol: $order->symbol,
                side: $order->side,
                price: $order->price,
                amount: $order->amount,
                releasedReservation: $event->releasedReservation,
            ),
        );
    }
}
