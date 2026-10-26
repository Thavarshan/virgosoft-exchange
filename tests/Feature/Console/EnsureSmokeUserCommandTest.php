<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\FeatureTestCase;

class EnsureSmokeUserCommandTest extends FeatureTestCase
{
    public function test_it_skips_when_the_smoke_user_is_not_configured(): void
    {
        config([
            'services.smoke.email' => null,
            'services.smoke.password' => null,
        ]);

        $this->artisan('app:ensure-smoke-user')
            ->expectsOutput('Smoke user is not configured; skipping.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_creates_a_funded_user_without_assets(): void
    {
        $this->configureSmokeUser('smoke@example.test', 'a-long-smoke-secret');

        $this->artisan('app:ensure-smoke-user')
            ->expectsOutput('Smoke user created.')
            ->assertExitCode(0);

        $user = User::query()
            ->where('email', 'smoke@example.test')
            ->firstOrFail();

        $this->assertSame('10.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
        $this->assertSame(0, $user->assets()->count());
        $this->assertTrue(Hash::check('a-long-smoke-secret', $user->password));
    }

    public function test_rerunning_keeps_balances_and_rotates_the_password(): void
    {
        $this->configureSmokeUser('smoke@example.test', 'a-long-smoke-secret');
        $this->artisan('app:ensure-smoke-user')->assertExitCode(0);

        $user = User::query()->where('email', 'smoke@example.test')->firstOrFail();
        $user->balance = '7.50';
        $user->locked_balance = '2.50';
        $user->save();

        $this->configureSmokeUser('smoke@example.test', 'a-rotated-smoke-secret');

        $this->artisan('app:ensure-smoke-user')
            ->expectsOutput('Smoke user already exists.')
            ->assertExitCode(0);

        $user->refresh();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame('7.50', $user->balance);
        $this->assertSame('2.50', $user->locked_balance);
        $this->assertTrue(Hash::check('a-rotated-smoke-secret', $user->password));
    }

    public function test_it_rejects_a_weak_password(): void
    {
        $this->configureSmokeUser('smoke@example.test', 'short');

        $this->artisan('app:ensure-smoke-user')
            ->expectsOutput(
                'The smoke user needs a valid email and a password of at least 12 characters.',
            )
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    protected function configureSmokeUser(string $email, string $password): void
    {
        config([
            'services.smoke.email' => $email,
            'services.smoke.password' => $password,
        ]);
    }
}
