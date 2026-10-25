<?php

namespace App\Listeners;

use App\Actions\Trading\MatchOrder;
use App\Events\OrderPlaced;

class MatchPlacedOrder
{
    public function __construct(
        protected readonly MatchOrder $matchOrder,
    ) {}

    /**
     * Match the placed order inside its open database transaction.
     */
    public function handle(OrderPlaced $event): void
    {
        $this->matchOrder->execute($event->order);
    }
}
