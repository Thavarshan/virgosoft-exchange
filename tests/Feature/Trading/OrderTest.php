<?php

namespace Tests\Feature\Trading;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

    public function test_database_rejects_zero_price(): void
    {
        $order = Order::factory()->create();
        $order->price = '0.00';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/orders_price_positive/');

        $order->save();
    }

    public function test_database_rejects_negative_price(): void
    {
        $order = Order::factory()->create();
        $order->price = '-0.01';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/orders_price_positive/');

        $order->save();
    }

    public function test_database_rejects_zero_amount(): void
    {
        $order = Order::factory()->create();
        $order->amount = '0.00000000';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/orders_amount_positive/');

        $order->save();
    }

    public function test_database_rejects_negative_amount(): void
    {
        $order = Order::factory()->create();
        $order->amount = '-0.00000001';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/orders_amount_positive/');

        $order->save();
    }

    public function test_database_rejects_nonexistent_user(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/orders_user_id_foreign/');

        Order::factory()->create(['user_id' => 999999]);
    }

    public function test_database_rejects_invalid_symbol(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/orders_symbol_check/');

        // Raw inserts ensure PostgreSQL validates the enum value directly.
        DB::table('orders')->insert([
            'user_id' => $user->id,
            'symbol' => 'DOGE',
            'side' => 'buy',
            'price' => '100.00',
            'amount' => '0.00000001',
            'status' => OrderStatus::OPEN->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_invalid_side(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/orders_side_check/');

        DB::table('orders')->insert([
            'user_id' => $user->id,
            'symbol' => AssetSymbol::BTC->value,
            'side' => 'hold',
            'price' => '100.00',
            'amount' => '0.00000001',
            'status' => OrderStatus::OPEN->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_database_rejects_invalid_status(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/orders_status_valid/');

        DB::table('orders')->insert([
            'user_id' => $user->id,
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '100.00',
            'amount' => '0.00000001',
            'status' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
