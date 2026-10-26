<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Tests\FeatureTestCase;

class SecurityTest extends FeatureTestCase
{
    public function test_the_protected_api_returns_a_stable_identity_contract(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/session')
            ->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email']]);
    }
}
