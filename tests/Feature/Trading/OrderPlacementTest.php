<?php

namespace Tests\Feature\Trading;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Tests\FeatureTestCase;

class OrderPlacementTest extends FeatureTestCase
{
    public function test_guests_cannot_place_orders(): void
    {
        $response = $this->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ]);

        $response->assertUnauthorized();
    }

    public function test_users_can_place_buy_orders_and_reserve_usd(): void
    {
        $user = User::factory()->create();
        $user->balance = '1000.00';
        $user->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('orders', 1);

        $order = Order::query()->latest('id')->firstOrFail();

        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(AssetSymbol::BTC, $order->symbol);
        $this->assertSame(OrderSide::BUY, $order->side);
        $this->assertSame(OrderStatus::OPEN, $order->status);
        $this->assertSame('20000.00', $order->price);
        $this->assertSame('0.01000000', $order->amount);

        $user->refresh();

        $this->assertSame('800.00', $user->balance);
        $this->assertSame('200.00', $user->locked_balance);
    }

    public function test_users_can_cancel_buy_orders_and_release_reserved_usd(): void
    {
        $user = User::factory()->create();
        $user->balance = '1000.00';
        $user->save();

        $placement = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ]);

        $placement->assertCreated();

        $order = Order::query()->latest('id')->firstOrFail();

        $response = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk();
        $response->assertJsonPath(
            'order.status',
            OrderStatus::CANCELLED->value,
        );

        $order->refresh();
        $user->refresh();

        $this->assertSame(OrderStatus::CANCELLED, $order->status);
        $this->assertSame('1000.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_users_can_place_sell_orders_and_reserve_assets(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->for($user)->create();
        $asset->amount = '1.00000000';
        $asset->locked_amount = '0.00000000';
        $asset->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '20000.00',
            'amount' => '0.25000000',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('orders', 1);

        $order = Order::query()->latest('id')->firstOrFail();

        $this->assertSame($user->id, $order->user_id);
        $this->assertSame(AssetSymbol::BTC, $order->symbol);
        $this->assertSame(OrderSide::SELL, $order->side);
        $this->assertSame(OrderStatus::OPEN, $order->status);

        $asset->refresh();

        $this->assertSame('0.75000000', $asset->amount);
        $this->assertSame('0.25000000', $asset->locked_amount);

        $user->refresh();

        $this->assertSame('0.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_users_cannot_sell_more_than_their_available_asset_balance(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->for($user)->create();
        $asset->amount = '0.50000000';
        $asset->locked_amount = '0.50000000';
        $asset->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '20000.00',
            'amount' => '0.75000000',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);

        $asset->refresh();
        $user->refresh();

        $this->assertSame('0.50000000', $asset->amount);
        $this->assertSame('0.50000000', $asset->locked_amount);
        $this->assertSame('0.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_users_cannot_sell_without_an_asset_row(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '20000.00',
            'amount' => '0.25000000',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);

        $user->refresh();

        $this->assertSame('0.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_users_cannot_sell_an_asset_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAsset = Asset::factory()->for($otherUser)->create();
        $otherAsset->amount = '1.00000000';
        $otherAsset->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '20000.00',
            'amount' => '0.25000000',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);

        $otherAsset->refresh();
        $user->refresh();

        $this->assertSame('1.00000000', $otherAsset->amount);
        $this->assertSame('0.00000000', $otherAsset->locked_amount);
        $this->assertSame('0.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_fractional_cent_costs_round_up_to_one_cent(): void
    {
        $user = User::factory()->create();
        $user->balance = '1.00';
        $user->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.00000001',
        ]);

        $response->assertCreated();
        $this->assertDatabaseCount('orders', 1);

        $user->refresh();

        $this->assertSame('0.99', $user->balance);
        $this->assertSame('0.01', $user->locked_balance);
    }

    public function test_users_cannot_place_an_order_when_the_rounded_cost_exceeds_balance(): void
    {
        $user = User::factory()->create();
        $user->balance = '1.00';
        $user->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '100.10',
            'amount' => '0.01000000',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);

        $user->refresh();

        $this->assertSame('1.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_costs_with_a_fractional_cent_round_up_to_the_next_cent(): void
    {
        $user = User::factory()->create();
        $user->balance = '2.00';
        $user->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '100.10',
            'amount' => '0.01000000',
        ]);

        $response->assertCreated();

        $user->refresh();

        $this->assertSame('0.99', $user->balance);
        $this->assertSame('1.01', $user->locked_balance);
    }

    public function test_numeric_order_values_are_rejected_as_unprocessable(): void
    {
        $user = User::factory()->create();
        $user->balance = '1000.00';
        $user->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => 20000.00,
            'amount' => 0.01000000,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['price', 'amount']);
        $this->assertDatabaseCount('orders', 0);

        $user->refresh();

        $this->assertSame('1000.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_price_exceeding_database_precision_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->balance = '1000.00';
        $user->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '1234567890123456789.00',
            'amount' => '0.01000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['price']);
        $this->assertDatabaseCount('orders', 0);

        $user->refresh();

        $this->assertSame('1000.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_amount_exceeding_database_precision_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->balance = '1000.00';
        $user->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '100.00',
            'amount' => '1234567890123.00000000',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['amount']);
        $this->assertDatabaseCount('orders', 0);

        $user->refresh();

        $this->assertSame('1000.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_orders_are_rejected_when_available_funds_are_insufficient(): void
    {
        $user = User::factory()->create();
        $user->balance = '199.99';
        $user->save();

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ]);

        $response->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);

        $user->refresh();

        $this->assertSame('199.99', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }
}
