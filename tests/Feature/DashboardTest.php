<?php

use App\Models\User;

test('the authenticated SPA shell is available', function () {
    $this->get('/trade')->assertOk();
});

test('authenticated users can access their session', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->getJson('/api/session')->assertOk();
});
