<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\FeatureTestCase;

class PasswordConfirmationTest extends FeatureTestCase
{
    public function test_guests_cannot_access_the_session_endpoint(): void
    {
        $this->getJson('/api/session')->assertUnauthorized();
    }

    public function test_authenticated_users_can_access_the_session_endpoint(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/session');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');
    }
}
