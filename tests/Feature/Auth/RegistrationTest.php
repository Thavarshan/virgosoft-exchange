<?php

namespace Tests\Feature\Auth;

use Tests\FeatureTestCase;

class RegistrationTest extends FeatureTestCase
{
    public function test_public_registration_is_disabled(): void
    {
        $this->postJson('/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertMethodNotAllowed();
    }
}
