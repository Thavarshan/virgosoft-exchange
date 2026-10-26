<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\FeatureTestCase;

class TwoFactorChallengeTest extends FeatureTestCase
{
    public function test_session_auth_works_without_a_bearer_token(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/session')
            ->assertOk();
    }
}
