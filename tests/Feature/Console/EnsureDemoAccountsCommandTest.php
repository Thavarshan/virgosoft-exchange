<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Support\DemoAccounts;
use Database\Seeders\DefaultUserSeeder;
use Database\Seeders\DemoTradingSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\FeatureTestCase;

class EnsureDemoAccountsCommandTest extends FeatureTestCase
{
    public function test_it_skips_when_demo_accounts_are_disabled(): void
    {
        config(['app.demo_accounts' => false]);

        $this->artisan('app:ensure-demo-accounts')
            ->expectsOutput('Demo accounts are not enabled; skipping.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_creates_both_accounts_with_generous_funds(): void
    {
        config(['app.demo_accounts' => true]);

        $this->artisan('app:ensure-demo-accounts')
            ->expectsOutput('Created 2 demo account(s).')
            ->assertExitCode(0);

        foreach ([DefaultUserSeeder::EMAIL, DemoTradingSeeder::EMAIL] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame('100000.00', $user->balance);
            $this->assertSame('0.00', $user->locked_balance);
            $this->assertTrue(Hash::check(DefaultUserSeeder::PASSWORD, $user->password));
            $this->assertSame(
                '10.00000000',
                $user->assets()->where('symbol', 'BTC')->firstOrFail()->amount,
            );
            $this->assertSame(
                '100.00000000',
                $user->assets()->where('symbol', 'ETH')->firstOrFail()->amount,
            );
        }
    }

    public function test_rerunning_never_changes_existing_balances(): void
    {
        config(['app.demo_accounts' => true]);
        $this->artisan('app:ensure-demo-accounts')->assertExitCode(0);

        $user = User::query()->where('email', DefaultUserSeeder::EMAIL)->firstOrFail();
        $user->balance = '12.34';
        $user->locked_balance = '5.00';
        $user->save();

        $this->artisan('app:ensure-demo-accounts')
            ->expectsOutput('Demo accounts already exist.')
            ->assertExitCode(0);

        $user->refresh();

        $this->assertDatabaseCount('users', 2);
        $this->assertSame('12.34', $user->balance);
        $this->assertSame('5.00', $user->locked_balance);
    }

    public function test_the_login_page_lists_the_demo_accounts_only_when_enabled(): void
    {
        config(['app.demo_accounts' => true]);

        $this->get('/login')->assertInertia(fn ($page) => $page
            ->component('auth/Login')
            ->has('demoAccounts', 2)
            ->where('demoAccounts.0.email', DefaultUserSeeder::EMAIL)
            ->where('demoAccounts.0.password', DefaultUserSeeder::PASSWORD)
            ->where('demoAccounts.1.email', DemoTradingSeeder::EMAIL)
            ->etc());

        config(['app.demo_accounts' => false]);

        $this->get('/login')->assertInertia(fn ($page) => $page
            ->has('demoAccounts', 0)
            ->etc());
    }

    public function test_the_listed_accounts_match_the_seeded_credentials(): void
    {
        $emails = array_column(DemoAccounts::all(), 'email');

        $this->assertSame(
            [DefaultUserSeeder::EMAIL, DemoTradingSeeder::EMAIL],
            $emails,
        );
    }
}
