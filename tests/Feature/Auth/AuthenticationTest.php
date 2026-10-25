<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('the SPA login shell is available', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('<meta name="csrf-token"', false);
});

test('users can authenticate with Fortify', function () {
    $user = User::factory()->create();

    $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/logout')->assertNoContent();
    $this->assertGuest();
});

test('login is rate limited', function () {
    $user = User::factory()->create();
    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});
