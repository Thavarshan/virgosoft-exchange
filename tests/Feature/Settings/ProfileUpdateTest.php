<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Tests\FeatureTestCase;

class ProfileUpdateTest extends FeatureTestCase
{
    public function test_authenticated_users_can_access_their_profile_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/profile')
            ->assertOk();
    }
}
