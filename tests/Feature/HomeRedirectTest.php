<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\FeatureTestCase;

class HomeRedirectTest extends FeatureTestCase
{
    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/')->assertRedirectToRoute('login');
    }

    public function test_signed_in_users_are_sent_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertRedirectToRoute('dashboard');
    }
}
