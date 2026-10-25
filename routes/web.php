<?php

use Illuminate\Support\Facades\Route;

Route::view('/login', 'app')->name('login');
Route::view('/register', 'app')->name('register');

Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|login|logout|sanctum|broadcasting|up).*$')
    ->name('home');
