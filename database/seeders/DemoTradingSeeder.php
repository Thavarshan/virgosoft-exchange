<?php

namespace Database\Seeders;

use App\Actions\Seeding\SeedDemoAccount;
use App\Enums\AssetSymbol;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoTradingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(
        private readonly SeedDemoAccount $seedDemoAccount,
    ) {}

    public const NAME = 'Demo Counterparty';

    public const EMAIL = 'counterparty@example.com';

    public const PASSWORD = DefaultUserSeeder::PASSWORD;

    /**
     * Seed two funded local users who can immediately demonstrate trading.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->seedDemoAccount->execute(
            name: DefaultUserSeeder::NAME,
            email: DefaultUserSeeder::EMAIL,
            password: DefaultUserSeeder::PASSWORD,
            balance: '1000.00',
            assetBalances: [
                AssetSymbol::BTC->value => '1.00000000',
                AssetSymbol::ETH->value => '10.00000000',
            ],
        );
        $this->seedDemoAccount->execute(
            name: self::NAME,
            email: self::EMAIL,
            password: self::PASSWORD,
            balance: '1000.00',
            assetBalances: [
                AssetSymbol::BTC->value => '1.00000000',
                AssetSymbol::ETH->value => '10.00000000',
            ],
        );
    }
}
