<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        #[CurrentUser] User $user,
    ): OrderCollection {
        return OrderCollection::make(
            $user->orders()
                ->newestFirst()
                ->paginate(20),
        );
    }
}
