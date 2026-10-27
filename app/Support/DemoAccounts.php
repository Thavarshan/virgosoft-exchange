<?php

namespace App\Support;

use Database\Seeders\DefaultUserSeeder;
use Database\Seeders\DemoTradingSeeder;

/**
 * The shared demo accounts offered to reviewers on the login page.
 */
final class DemoAccounts
{
    /**
     * Starting funds for accounts created on a deployed demo environment.
     *
     * Local and test seeders keep their smaller, deterministic balances.
     */
    public const BALANCE = StartingWallet::USD_BALANCE;

    /** @var array<string, numeric-string> */
    public const ASSET_BALANCES = StartingWallet::ASSET_BALANCES;

    /**
     * Whether demo accounts are enabled for this environment.
     */
    public static function enabled(): bool
    {
        return (bool) config('app.demo_accounts');
    }

    /**
     * Get the demo credentials, as shown on the login page.
     *
     * @return list<array{name: string, email: string, password: string}>
     */
    public static function all(): array
    {
        return [
            [
                'name' => DefaultUserSeeder::NAME,
                'email' => DefaultUserSeeder::EMAIL,
                'password' => DefaultUserSeeder::PASSWORD,
            ],
            [
                'name' => DemoTradingSeeder::NAME,
                'email' => DemoTradingSeeder::EMAIL,
                'password' => DemoTradingSeeder::PASSWORD,
            ],
        ];
    }
}
