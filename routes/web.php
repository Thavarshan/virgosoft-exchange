<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// There is no public landing page: the app is for signed-in traders only.
Route::get('/', fn () => redirect()->route(
    Auth::check() ? 'dashboard' : 'login',
))->name('home');

Route::get('dashboard', fn () => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('trade', fn () => Inertia::render('Trade'))
    ->middleware(['auth', 'verified'])
    ->name('trade');

Route::get('wallet', fn () => Inertia::render('Wallet'))
    ->middleware(['auth', 'verified'])
    ->name('wallet');
