<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile and balances.
     */
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return response()->json([
            'user' => ProfileResource::make($user->load('assets')),
        ]);
    }
}
