<?php

namespace Tests\Feature\Auth;

use Tests\FeatureTestCase;

class RegistrationTest extends FeatureTestCase
{
    public function test_users_can_register(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSuccessful();
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'new@example.com',
        ]);
        $this->assertAuthenticated();
    }
}
