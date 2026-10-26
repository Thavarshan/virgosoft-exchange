<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced
{
    use Dispatchable, SerializesModels;

    /**
     * Create an event for the newly reserved order.
     *
     * The event is intentionally synchronous. Its listener must settle a
     * compatible order before the placement transaction commits, otherwise
     * reservation and matching could succeed or fail independently.
     */
    public function __construct(
        public readonly Order $order,
    ) {}
}
