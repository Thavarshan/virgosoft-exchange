<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * Create an event after an order cancellation has released its reservation.
     *
     * @param  numeric-string  $releasedReservation
     */
    public function __construct(
        public readonly Order $order,
        public readonly string $releasedReservation,
    ) {}
}
