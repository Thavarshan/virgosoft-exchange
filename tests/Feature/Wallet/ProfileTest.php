<?php

namespace Tests\Feature\Wallet;

use App\Enums\AssetSymbol;
use App\Models\Asset;
use App\Models\User;
use Tests\FeatureTestCase;

class ProfileTest extends FeatureTestCase
{
    public function test_guests_cannot_view_a_profile(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_authenticated_users_receive_only_their_profile_balances(): void
    {
        $user = User::factory()->create();
        $user->balance = '1234.56';
        $user->locked_balance = '78.90';
        $user->save();

        $ownedAsset = Asset::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
        ]);
        $ownedAsset->amount = '1.12345678';
        $ownedAsset->locked_amount = '0.00000001';
        $ownedAsset->save();

        $otherUser = User::factory()->create();
        Asset::factory()->for($otherUser)->create([
            'symbol' => AssetSymbol::ETH,
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk();
        $response->assertJsonMissingPath('data');
        $response->assertJsonPath('user.balance', '1234.56');
        $response->assertJsonPath('user.locked_balance', '78.90');
        $response->assertJsonCount(1, 'user.assets');
        $response->assertJsonPath('user.assets.0.symbol', 'BTC');
        $response->assertJsonPath('user.assets.0.amount', '1.12345678');
        $response->assertJsonPath(
            'user.assets.0.locked_amount',
            '0.00000001'
        );
        $response->assertJsonMissing(['symbol' => 'ETH']);
    }

    public function test_authenticated_users_without_assets_receive_an_empty_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk();
        $response->assertJsonPath('user.assets', []);
    }
}
