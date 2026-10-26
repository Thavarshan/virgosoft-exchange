<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\BuildDashboard;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexDashboardRequest;
use App\Http\Resources\DashboardResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
class DashboardController extends Controller
{
    /**
     * Display the authenticated user's trading dashboard.
     */
    public function __invoke(
        IndexDashboardRequest $request,
        #[CurrentUser] User $user,
        BuildDashboard $buildDashboard,
    ): DashboardResource {
        return DashboardResource::make(
            $buildDashboard->execute($user, $request->period()),
        );
    }
}
