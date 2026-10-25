<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\FeatureTestCase;

class DashboardTest extends FeatureTestCase
{
    public function test_authenticated_users_can_access_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_authenticated_users_can_access_their_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/session')
            ->assertOk();
    }
}
