<?php

use App\Models\User;

test('protected session endpoint rejects guests with JSON', function () {
    $this->getJson('/api/session')->assertUnauthorized();
});

test('authenticated session endpoint exposes only identity fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/session')
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonMissingPath('user.password');
});
