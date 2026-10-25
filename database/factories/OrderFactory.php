<?php

namespace Database\Factories;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            // A required positive amount keeps the factory valid by default.
            'price' => '100.00',
            'amount' => '0.00000001',
        ];
    }
}
