<?php

namespace App\Actions\Trading;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderMatched;
use App\Models\Order;
use App\Models\User;

class MatchOrder
{
    public function __construct(
        protected readonly FindCounterOrder $findCounterOrder,
        protected readonly CalculateOrderCost $calculateOrderCost,
        protected readonly SettleBuyer $settleBuyer,
        protected readonly SettleSeller $settleSeller,
        protected readonly FillMatchedOrders $fillMatchedOrders,
        protected readonly LockUser $lockUser,
        protected readonly AcquireMarketLock $acquireMarketLock,
    ) {}

    /**
     * Match the new order against the oldest compatible full-sized order.
     *
     * The caller owns the surrounding transaction. This coordinator only
     * assembles the match; the focused actions perform discovery, settlement,
     * and lifecycle changes before the transaction can commit.
     */
    public function execute(Order $order): void
    {
        if ($order->status !== OrderStatus::OPEN) {
            return;
        }

        // This is re-entrant when PlaceOrder already acquired the same lock.
        $this->acquireMarketLock->execute($order->symbol);
        $counterOrder = $this->findCounterOrder->execute($order);

        if ($counterOrder === null) {
            return;
        }

        $match = $this->createMatch($order, $counterOrder);
        $executionPrice = $match->executionPrice;
        $tradeValue = $this->calculateTradeValue(
            $executionPrice,
            $match->buyOrder->amount,
        );
        $commission = $this->settleBuyer->execute(
            $match->buyer,
            $match->buyOrder,
            $tradeValue,
        );

        $this->settleSeller->execute(
            $match->seller,
            $match->sellOrder,
            $tradeValue,
        );
        $this->fillMatchedOrders->execute(
            $match->buyOrder,
            $match->sellOrder,
        );
        $this->broadcastMatch(
            $match,
            $executionPrice,
            $tradeValue,
            $commission,
        );
    }

    /**
     * Build the typed match context used by settlement and broadcasting.
     */
    protected function createMatch(
        Order $order,
        Order $counterOrder,
    ): OrderMatch {
        $buyOrder = $order->side === OrderSide::BUY
                ? $order
                : $counterOrder;
        $sellOrder = $order->side === OrderSide::SELL
                ? $order
                : $counterOrder;
        $lockedUsers = $this->lockParticipants($buyOrder, $sellOrder);

        return new OrderMatch(
            buyOrder: $buyOrder,
            sellOrder: $sellOrder,
            buyer: $lockedUsers[$buyOrder->user_id],
            seller: $lockedUsers[$sellOrder->user_id],
            executionPrice: $counterOrder->price,
        );
    }

    /**
     * Lock both participants in primary-key order to make lock acquisition
     * deterministic when two opposite orders arrive concurrently.
     *
     * @return array<int, User>
     */
    protected function lockParticipants(Order $buyOrder, Order $sellOrder): array
    {
        $userIds = [$buyOrder->user_id, $sellOrder->user_id];
        sort($userIds, SORT_NUMERIC);

        $lockedUsers = [];

        foreach (array_unique($userIds) as $userId) {
            $lockedUsers[$userId] = $this->lockUser->byId($userId);
        }

        return $lockedUsers;
    }

    /**
     * Calculate the execution value at the existing counter-order price.
     *
     * @param  numeric-string  $executionPrice
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    protected function calculateTradeValue(
        string $executionPrice,
        string $amount,
    ): string {
        return $this->calculateOrderCost->execute(
            $executionPrice,
            $amount,
        );
    }

    /**
     * Broadcast the completed settlement after the transaction commits.
     *
     * @param  numeric-string  $executionPrice
     * @param  numeric-string  $tradeValue
     * @param  numeric-string  $commission
     */
    protected function broadcastMatch(
        OrderMatch $match,
        string $executionPrice,
        string $tradeValue,
        string $commission,
    ): void {
        OrderMatched::dispatch(
            $match->buyOrder,
            $match->sellOrder,
            $executionPrice,
            $tradeValue,
            $commission,
        );
    }
}
