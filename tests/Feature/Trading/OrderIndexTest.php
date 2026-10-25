<?php

namespace Tests\Feature\Trading;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\FeatureTestCase;

class OrderIndexTest extends FeatureTestCase
{
    public function test_it_returns_only_open_btc_orders_in_order_book_order(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $buyHigh = Order::factory()->for($firstUser)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ]);
        $buyLow = Order::factory()->for($secondUser)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'price' => '19000.00',
            'amount' => '0.02000000',
        ]);
        $sellLow = Order::factory()->for($firstUser)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'price' => '18000.00',
            'amount' => '0.03000000',
        ]);
        $sellHigh = Order::factory()->for($secondUser)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'price' => '21000.00',
            'amount' => '0.04000000',
        ]);

        Order::factory()->for($firstUser)->create([
            'symbol' => AssetSymbol::ETH,
            'side' => OrderSide::BUY,
            'price' => '1500.00',
            'amount' => '0.10000000',
        ]);
        Order::factory()->for($firstUser)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'status' => OrderStatus::FILLED,
            'price' => '22000.00',
            'amount' => '0.05000000',
        ]);
        Order::factory()->for($firstUser)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'status' => OrderStatus::CANCELLED,
            'price' => '23000.00',
            'amount' => '0.06000000',
        ]);

        $response = $this->actingAs($firstUser)
            ->getJson('/api/orders?symbol=BTC');

        $response->assertOk();
        $response->assertJsonCount(4, 'orders');
        $response->assertJsonPath('orders.0.id', $buyHigh->id);
        $response->assertJsonPath('orders.0.side', OrderSide::BUY->value);
        $response->assertJsonPath('orders.0.price', '20000.00');
        $response->assertJsonPath('orders.0.amount', '0.01000000');
        $response->assertJsonPath('orders.1.id', $buyLow->id);
        $response->assertJsonPath('orders.1.side', OrderSide::BUY->value);
        $response->assertJsonPath('orders.1.price', '19000.00');
        $response->assertJsonPath('orders.1.amount', '0.02000000');
        $response->assertJsonPath('orders.2.id', $sellLow->id);
        $response->assertJsonPath('orders.2.side', OrderSide::SELL->value);
        $response->assertJsonPath('orders.2.price', '18000.00');
        $response->assertJsonPath('orders.2.amount', '0.03000000');
        $response->assertJsonPath('orders.3.id', $sellHigh->id);
        $response->assertJsonPath('orders.3.side', OrderSide::SELL->value);
        $response->assertJsonPath('orders.3.price', '21000.00');
        $response->assertJsonPath('orders.3.amount', '0.04000000');
    }

    public function test_orders_with_equal_prices_are_sorted_by_age_then_id(): void
    {
        $user = User::factory()->create();
        $createdAt = Carbon::parse('2026-01-01 12:00:00');

        $firstBuy = Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'price' => '20000.00',
        ]);
        $secondBuy = Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'price' => '20000.00',
        ]);
        $firstSell = Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'price' => '21000.00',
        ]);
        $secondSell = Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'price' => '21000.00',
        ]);

        foreach ([$firstBuy, $secondBuy, $firstSell, $secondSell] as $order) {
            $order->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
        }

        $response = $this->actingAs($user)->getJson('/api/orders?symbol=BTC');

        $response->assertOk();
        $response->assertJsonPath('orders.0.id', $firstBuy->id);
        $response->assertJsonPath('orders.1.id', $secondBuy->id);
        $response->assertJsonPath('orders.2.id', $firstSell->id);
        $response->assertJsonPath('orders.3.id', $secondSell->id);
    }
}
