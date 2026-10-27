<?php

namespace App\Listeners;

use App\Actions\Notifications\SendTradingNotification;
use App\Events\OrderMatched;
use App\Notifications\OrderMatchedNotification;

class NotifyOrderMatched
{
    /**
     * Create the listener with the centralized recipient policy.
     */
    public function __construct(
        protected readonly SendTradingNotification $sendTradingNotification,
    ) {}

    /**
     * Queue a fill receipt for the buyer and seller using the same settlement.
     */
    public function handle(OrderMatched $event): void
    {
        $buyOrder = $event->buyOrder->loadMissing('user');
        $sellOrder = $event->sellOrder->loadMissing('user');
        $buyer = $buyOrder->user;
        $seller = $sellOrder->user;

        $this->sendTradingNotification->execute(
            $buyer,
            new OrderMatchedNotification(
                accountName: $buyer->name,
                accountEmail: $buyer->email,
                isDemo: $buyer->is_demo,
                symbol: $buyOrder->symbol,
                side: $buyOrder->side,
                orderPrice: $buyOrder->price,
                amount: $buyOrder->amount,
                executionPrice: $event->executionPrice,
                tradeValue: $event->tradeValue,
                commission: $event->commission,
            ),
        );

        $this->sendTradingNotification->execute(
            $seller,
            new OrderMatchedNotification(
                accountName: $seller->name,
                accountEmail: $seller->email,
                isDemo: $seller->is_demo,
                symbol: $sellOrder->symbol,
                side: $sellOrder->side,
                orderPrice: $sellOrder->price,
                amount: $sellOrder->amount,
                executionPrice: $event->executionPrice,
                tradeValue: $event->tradeValue,
                commission: $event->commission,
            ),
        );
    }
}
