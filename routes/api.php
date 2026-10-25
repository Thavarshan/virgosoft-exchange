<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/profile', [ProfileController::class, 'show'])
    ->name('api.profile');

Route::post('/orders', [OrderController::class, 'store'])
    ->name('api.orders.store');

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
