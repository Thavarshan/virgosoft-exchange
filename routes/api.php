<?php

use App\Http\Controllers\Api\OrderCancellationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderHistoryController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/profile', [ProfileController::class, 'show'])
    ->name('api.profile');

Route::get('/profile/orders', OrderHistoryController::class)
    ->name('api.profile.orders');

Route::get('/orders', [OrderController::class, 'index'])
    ->name('api.orders.index');

Route::post('/orders', [OrderController::class, 'store'])
    ->name('api.orders.store');

Route::post('/orders/{order}/cancel', OrderCancellationController::class)
    ->name('api.orders.cancel');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/session', function (Request $request) {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    })->name('api.session');
});
