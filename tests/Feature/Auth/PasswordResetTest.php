<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\FeatureTestCase;

class PasswordResetTest extends FeatureTestCase
{
    public function test_password_reset_requests_remain_available_as_json_endpoints(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->postJson('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        Notification::assertSentTo($user, ResetPassword::class);
    }
}
