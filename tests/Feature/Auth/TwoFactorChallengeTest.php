<?php

use App\Models\User;

test('session auth works without a bearer token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/session')->assertOk();
});
