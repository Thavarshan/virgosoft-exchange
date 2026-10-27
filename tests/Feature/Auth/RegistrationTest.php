<?php

namespace Tests\Feature\Auth;

use App\Enums\AssetSymbol;
use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Support\Facades\Notification;
use Tests\FeatureTestCase;

class RegistrationTest extends FeatureTestCase
{
    public function test_users_can_register(): void
    {
        Notification::fake();

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
            'is_demo' => false,
        ]);
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $user->refresh();

        $this->assertSame('100000.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
        $this->assertDatabaseHas('assets', [
            'user_id' => $user->id,
            'symbol' => AssetSymbol::BTC->value,
            'amount' => '10.00000000',
            'locked_amount' => '0.00000000',
        ]);
        $this->assertDatabaseHas('assets', [
            'user_id' => $user->id,
            'symbol' => AssetSymbol::ETH->value,
            'amount' => '100.00000000',
            'locked_amount' => '0.00000000',
        ]);
        $this->assertSame(2, $user->assets()->count());

        Notification::assertSentTo(
            $user,
            WelcomeNotification::class,
            function (WelcomeNotification $notification, array $channels) use ($user): bool {
                $mail = $notification->toMail($user);
                $renderedMail = $mail->render();

                $mail->assertTo($user->email);
                $this->assertSame(['mail'], $channels);
                $this->assertSame('emails', $notification->queue);
                $this->assertTrue($notification->afterCommit);
                $this->assertStringContainsString('100000.00 USD', $renderedMail);
                $this->assertStringContainsString('10.00000000 BTC', $renderedMail);
                $this->assertStringContainsString('100.00000000 ETH', $renderedMail);
                $this->assertStringContainsString('1.5% commission', $renderedMail);
                $this->assertStringNotContainsString('assessment', strtolower($renderedMail));

                return true;
            },
        );
    }
}
