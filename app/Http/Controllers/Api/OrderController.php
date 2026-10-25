<?php

namespace App\Http\Controllers\Api;

use App\Actions\Trading\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

#[Middleware('auth:sanctum')]
class OrderController extends Controller
{
    /**
     * Store a new buy order.
     */
    public function store(
        StoreOrderRequest $request,
        #[CurrentUser] User $user,
        PlaceOrder $placeOrder,
    ): JsonResponse {
        $order = $placeOrder->execute($user, $request->orderData());

        return response()->json([
            'order' => OrderResource::make($order),
        ], Response::HTTP_CREATED);
    }
}
