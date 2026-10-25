<?php

namespace App\Http\Controllers\Api;

use App\Actions\Trading\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexOrderRequest;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Resources\OrderCollection;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Symfony\Component\HttpFoundation\Response;

#[Middleware('auth:sanctum')]
class OrderController extends Controller
{
    /**
     * List open orders for the requested market symbol.
     */
    public function index(IndexOrderRequest $request): OrderCollection
    {
        $symbol = $request->symbol();

        $orders = Order::query()
            ->forSymbol($symbol)
            ->open()
            ->orderBook()
            ->get();

        return OrderCollection::make($orders);
    }

    /**
     * Store a new buy order.
     */
    public function store(
        StoreOrderRequest $request,
        #[CurrentUser] User $user,
        PlaceOrder $placeOrder,
    ): JsonResponse {
        $order = $placeOrder->execute($user, $request->orderData());

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
