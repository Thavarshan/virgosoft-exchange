<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\FeatureTestCase;

class DashboardTest extends FeatureTestCase
{
    public function test_the_authenticated_spa_shell_is_available(): void
    {
        $this->get('/trade')->assertOk();
    }

    public function test_authenticated_users_can_access_their_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/session')
            ->assertOk();
    }
}
