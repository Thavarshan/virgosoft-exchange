<?php

namespace App\Http\Controllers\Api;

use App\Actions\Trading\CancelOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
class OrderCancellationController extends Controller
{
    /**
     * Cancel an open order and release its reservation.
     */
    public function __invoke(
        Order $order,
        #[CurrentUser] User $user,
        CancelOrder $cancelOrder,
    ): OrderResource {
        return OrderResource::make($cancelOrder->execute($user, $order));
    }
}
