<?php

namespace Tests\Feature\Trading;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Tests\FeatureTestCase;

class OrderCancellationTest extends FeatureTestCase
{
    public function test_users_can_cancel_sell_orders_and_restore_assets(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->for($user)->create();
        $asset->amount = '1.00000000';
        $asset->save();

        $placement = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '20000.00',
            'amount' => '0.25000000',
        ]);
        $placement->assertCreated();

        $order = Order::query()->latest('id')->firstOrFail();

        $response = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk();
        $response->assertJsonStructure(['order']);
        $response->assertJsonMissingPath('data');
        $asset->refresh();
        $order->refresh();

        $this->assertSame(OrderStatus::CANCELLED, $order->status);
        $this->assertSame('1.00000000', $asset->amount);
        $this->assertSame('0.00000000', $asset->locked_amount);
    }

    public function test_cancelling_an_order_twice_releases_funds_only_once(): void
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
        $firstCancellation = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");
        $firstCancellation->assertOk();

        $secondCancellation = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");
        $secondCancellation->assertUnprocessable();

        $user->refresh();

        $this->assertSame('1000.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_another_user_cannot_cancel_an_order(): void
    {
        $owner = User::factory()->create();
        $owner->balance = '1000.00';
        $owner->save();
        $otherUser = User::factory()->create();

        $placement = $this->actingAs($owner)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ]);
        $placement->assertCreated();

        $order = Order::query()->latest('id')->firstOrFail();

        $response = $this->actingAs($otherUser)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertNotFound();
        $order->refresh();
        $owner->refresh();
        $otherUser->refresh();

        $this->assertSame(OrderStatus::OPEN, $order->status);
        $this->assertSame('797.00', $owner->balance);
        $this->assertSame('203.00', $owner->locked_balance);
        $this->assertSame('0.00', $otherUser->balance);
        $this->assertSame('0.00', $otherUser->locked_balance);
    }

    public function test_guests_cannot_cancel_orders(): void
    {
        $response = $this->postJson('/api/orders/1/cancel');

        $response->assertUnauthorized();
    }

    public function test_filled_orders_cannot_be_cancelled(): void
    {
        $user = User::factory()->create();
        $user->balance = '1000.00';
        $user->save();
        $order = Order::factory()->for($user)->create([
            'status' => OrderStatus::FILLED,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertUnprocessable();
        $order->refresh();
        $user->refresh();

        $this->assertSame(OrderStatus::FILLED, $order->status);
        $this->assertSame('1000.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_fractional_cent_buy_cancellation_releases_the_exact_reserved_amount(): void
    {
        $user = User::factory()->create();
        $user->balance = '1.00';
        $user->save();

        $placement = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.00000001',
        ]);
        $placement->assertCreated();

        $order = Order::query()->latest('id')->firstOrFail();
        $user->refresh();
        $this->assertSame('0.98', $user->balance);
        $this->assertSame('0.02', $user->locked_balance);

        $response = $this->actingAs($user)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk();
        $user->refresh();

        $this->assertSame('1.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }
}
