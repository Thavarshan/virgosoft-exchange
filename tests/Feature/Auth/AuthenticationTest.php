<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\FeatureTestCase;

class AuthenticationTest extends FeatureTestCase
{
    public function test_the_spa_login_shell_is_available(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<meta name="csrf-token"', false);
    }

    public function test_users_can_authenticate_with_fortify(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_users_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/logout');

        $response->assertNoContent();
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $key = md5('login'.implode('|', [$user->email, '127.0.0.1']));

        RateLimiter::increment($key, amount: 5);

        $response = $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }
}
