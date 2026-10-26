<?php

namespace Database\Seeders;

use App\Actions\Seeding\SeedDemoAccount;
use App\Enums\AssetSymbol;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function __construct(
        protected readonly SeedDemoAccount $seedDemoAccount,
    ) {
    }

    public const NAME = 'Demo Trader';
    public const EMAIL = 'demo@example.com';
    public const PASSWORD = 'password';

    /**
     * Seed the default local development user with funds for demonstrations.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

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
