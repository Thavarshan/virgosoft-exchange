<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::get('dashboard', fn () => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('trade', fn () => Inertia::render('Trade'))
    ->middleware(['auth', 'verified'])
    ->name('trade');

Route::get('wallet', fn () => Inertia::render('Wallet'))
    ->middleware(['auth', 'verified'])
    ->name('wallet');
