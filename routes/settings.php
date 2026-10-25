<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

    Route::get('settings/password', [SecurityController::class, 'edit'])
        ->name('password.edit');
    Route::put('settings/password', [SecurityController::class, 'update'])
        ->name('settings.password.update');
    Route::get('settings/appearance', fn () => Inertia::render('settings/Appearance'))
        ->name('appearance.edit');
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->name('security.edit');
});
