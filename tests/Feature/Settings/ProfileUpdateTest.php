<?php

namespace Tests\Feature\Settings;

use App\Enums\AssetSymbol;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Tests\FeatureTestCase;

class ProfileUpdateTest extends FeatureTestCase
{
    public function test_authenticated_users_can_access_their_profile_settings(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/profile')
            ->assertOk();
    }

    public function test_users_with_financial_balances_cannot_delete_their_account(): void
    {
        $user = User::factory()->create();
        $user->balance = '100.00';
        $user->save();
        $asset = Asset::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);

        $response = $this->from('/settings/profile')
            ->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password']);

        $response->assertRedirect('/settings/profile');
        $response->assertSessionHasErrors([
            'account' => 'Accounts with balances or trading records cannot be deleted.',
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertModelExists($user);
        $this->assertModelExists($asset);
    }

    public function test_users_with_orders_cannot_delete_their_account(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create();

        $response = $this->from('/settings/profile')
            ->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password']);

        $response->assertRedirect('/settings/profile');
        $response->assertSessionHasErrors([
            'account' => 'Accounts with balances or trading records cannot be deleted.',
        ]);
        $this->assertAuthenticatedAs($user);
        $this->assertModelExists($user);
        $this->assertModelExists($order);
    }

    public function test_empty_accounts_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password']);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertModelMissing($user);
    }
}
