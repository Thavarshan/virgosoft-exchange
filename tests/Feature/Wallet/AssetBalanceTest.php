<?php

namespace Tests\Feature\Wallet;

use App\Enums\AssetSymbol;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\FeatureTestCase;

class AssetBalanceTest extends FeatureTestCase
{
    public function test_new_asset_balances_start_at_zero(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()
            ->for($user)
            ->create(['symbol' => AssetSymbol::BTC]);

        $asset->refresh();

        $this->assertSame(AssetSymbol::BTC, $asset->symbol);
        $this->assertSame('0.00000000', $asset->amount);
        $this->assertSame('0.00000000', $asset->locked_amount);
    }

    public function test_asset_balances_retain_eight_decimal_places(): void
    {
        $asset = Asset::factory()->create();
        $asset->amount = '1.12345678';
        $asset->locked_amount = '0.00000001';
        $asset->save();
        $asset->refresh();

        $this->assertSame('1.12345678', $asset->amount);
        $this->assertSame('0.00000001', $asset->locked_amount);
    }

    public function test_database_rejects_assets_for_nonexistent_users(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/assets_user_id_foreign/');

        Asset::factory()->create(['user_id' => 999999]);
    }

    public function test_a_user_cannot_have_two_btc_asset_rows(): void
    {
        $user = User::factory()->create();

        Asset::factory()->for($user)->create(['symbol' => AssetSymbol::BTC]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/assets_user_symbol_unique/');

        Asset::factory()->for($user)->create(['symbol' => AssetSymbol::BTC]);
    }

    public function test_different_users_can_each_have_a_btc_asset_row(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        Asset::factory()->for($firstUser)->create([
            'symbol' => AssetSymbol::BTC,
        ]);
        Asset::factory()->for($secondUser)->create([
            'symbol' => AssetSymbol::BTC,
        ]);

        $this->assertDatabaseCount('assets', 2);
    }

    public function test_a_user_can_have_btc_and_eth_asset_rows(): void
    {
        $user = User::factory()->create();

        Asset::factory()->for($user)->create(['symbol' => AssetSymbol::BTC]);
        Asset::factory()->for($user)->create(['symbol' => AssetSymbol::ETH]);

        $this->assertDatabaseCount('assets', 2);
    }

    public function test_database_rejects_negative_amounts(): void
    {
        $asset = Asset::factory()->create();
        $asset->amount = '-0.00000001';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/assets_amount_nonnegative/');

        $asset->save();
    }

    public function test_database_rejects_negative_locked_amounts(): void
    {
        $asset = Asset::factory()->create();
        $asset->locked_amount = '-0.00000001';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/assets_locked_amount_nonnegative/');

        $asset->save();
    }
}
