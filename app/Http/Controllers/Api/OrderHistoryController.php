<?php

namespace App\Http\Controllers\Api;

use App\Filters\OrderFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OrderHistoryRequest;
use App\Http\Resources\OrderCollection;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
class OrderHistoryController extends Controller
{
    /**
     * List every order belonging to the authenticated user.
     */
    public function __invoke(
        OrderHistoryRequest $request,
        #[CurrentUser] User $user,
    ): OrderCollection {
        $orders = OrderFilter::fromRequest($request)
            ->apply($user->orders()->newestFirst());

        return OrderCollection::make(
            $orders->paginate(20)->withQueryString(),
        );
    }
}
