<?php

namespace Tests\Feature\Trading;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Tests\FeatureTestCase;

class OrderTest extends FeatureTestCase
{
    public function test_users_can_create_a_valid_open_buy_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'price' => '100.00',
            'amount' => '0.12345678',
        ]);

        $order->refresh();

        $this->assertSame($user->id, $order->user_id);
        $this->assertTrue($order->user->is($user));
        $this->assertSame(AssetSymbol::BTC, $order->symbol);
        $this->assertSame(OrderSide::BUY, $order->side);
        $this->assertSame('100.00', $order->price);
        $this->assertSame('0.12345678', $order->amount);
        $this->assertSame(OrderStatus::OPEN, $order->status);
    }
}
