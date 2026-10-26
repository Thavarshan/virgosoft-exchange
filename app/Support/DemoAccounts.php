<?php

namespace App\Support;

use App\Enums\AssetSymbol;
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
    public const BALANCE = '100000.00';

    /** @var array<string, numeric-string> */
    public const ASSET_BALANCES = [
        AssetSymbol::BTC->value => '10.00000000',
        AssetSymbol::ETH->value => '100.00000000',
    ];

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
