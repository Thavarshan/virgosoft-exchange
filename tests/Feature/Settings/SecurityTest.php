<?php

use App\Models\User;

test('the protected API returns a stable identity contract', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/session')
        ->assertJsonStructure(['user' => ['id', 'name', 'email']]);
});
