<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    /**
     * Create a matched-order event for both participants.
     *
     * @param  numeric-string  $executionPrice
     * @param  numeric-string  $tradeValue
     * @param  numeric-string  $commission
     */
    public function __construct(
        public readonly Order $buyOrder,
        public readonly Order $sellOrder,
        public readonly string $executionPrice,
        public readonly string $tradeValue,
        public readonly string $commission,
    ) {
        $this->broadcastVia('pusher');
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('user.'.$this->buyOrder->user_id),
            new PrivateChannel('user.'.$this->sellOrder->user_id),
        ];

        return $this->buyOrder->user_id === $this->sellOrder->user_id
            ? [$channels[0]]
            : $channels;
    }

    /**
     * Set the frontend event name used by the private channels.
     */
    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    /**
     * Provide the settlement details needed by wallet and order-book clients.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'buy_order_id' => $this->buyOrder->id,
            'sell_order_id' => $this->sellOrder->id,
            'symbol' => $this->buyOrder->symbol->value,
            'execution_price' => $this->executionPrice,
            'amount' => $this->buyOrder->amount,
            'trade_value' => $this->tradeValue,
            'commission' => $this->commission,
        ];
    }
}
