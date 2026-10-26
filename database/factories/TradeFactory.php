<?php

namespace Database\Factories;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trade>
 */
class TradeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Both related orders represent the completed execution below.
            'buy_order_id' => Order::factory()->state([
                'symbol' => AssetSymbol::BTC,
                'side' => OrderSide::BUY,
                'price' => '100.00',
                'amount' => '0.00010000',
                'status' => OrderStatus::FILLED,
            ]),
            'sell_order_id' => Order::factory()->state([
                'symbol' => AssetSymbol::BTC,
                'side' => OrderSide::SELL,
                'price' => '100.00',
                'amount' => '0.00010000',
                'status' => OrderStatus::FILLED,
            ]),
            'symbol' => AssetSymbol::BTC,
            'price' => '100.00',
            'amount' => '0.00010000',
            'trade_value' => '0.01',
            // 1.5% of one cent rounds up to the one-cent minimum fee.
            'commission' => '0.01',
        ];
    }
}
