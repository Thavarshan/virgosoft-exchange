<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderOpened implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * Create an event after a placed order remains open after matching.
     *
     * @param  numeric-string  $reservedAmount
     */
    public function __construct(
        public readonly Order $order,
        public readonly string $reservedAmount,
    ) {}
}
