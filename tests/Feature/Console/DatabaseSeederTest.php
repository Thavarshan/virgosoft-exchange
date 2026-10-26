<?php

namespace Tests\Feature\Console;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultUserSeeder;
use Database\Seeders\DemoTradingSeeder;
use Tests\FeatureTestCase;

class DatabaseSeederTest extends FeatureTestCase
{
    public function test_database_seeder_creates_two_funded_demo_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('assets', 4);

        $defaultUser = User::query()
            ->where('email', DefaultUserSeeder::EMAIL)
            ->firstOrFail();
        $counterparty = User::query()
            ->where('email', DemoTradingSeeder::EMAIL)
            ->firstOrFail();

        $this->assertSame('1000.00', $defaultUser->balance);
        $this->assertSame('0.00', $defaultUser->locked_balance);
        $this->assertSame(2, $defaultUser->assets()->count());
        $this->assertSame('1000.00', $counterparty->balance);
        $this->assertSame('0.00', $counterparty->locked_balance);
        $this->assertSame(2, $counterparty->assets()->count());

        foreach ([$defaultUser, $counterparty] as $user) {
            $this->assertSame(
                '1.00000000',
                $user->assets()->where('symbol', AssetSymbol::BTC)->firstOrFail()->amount,
            );
            $this->assertSame(
                '10.00000000',
                $user->assets()->where('symbol', AssetSymbol::ETH)->firstOrFail()->amount,
            );
        }
    }

    public function test_database_seeder_preserves_active_trading_state(): void
    {
        $this->seed(DatabaseSeeder::class);

        $defaultUser = User::query()
            ->where('email', DefaultUserSeeder::EMAIL)
            ->firstOrFail();
        $counterparty = User::query()
            ->where('email', DemoTradingSeeder::EMAIL)
            ->firstOrFail();

        $this->actingAs($defaultUser)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '90000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        $this->actingAs($counterparty)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '100000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        $buyOrder = Order::query()->whereBelongsTo($defaultUser)->firstOrFail();
        $sellOrder = Order::query()->whereBelongsTo($counterparty)->firstOrFail();

        $this->seed(DatabaseSeeder::class);

        $defaultUser->refresh();
        $counterparty->refresh();
        $buyOrder->refresh();
        $sellOrder->refresh();
        $defaultBtc = $defaultUser->assets()
            ->where('symbol', AssetSymbol::BTC)
            ->firstOrFail();
        $counterpartyBtc = $counterparty->assets()
            ->where('symbol', AssetSymbol::BTC)
            ->firstOrFail();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('assets', 4);
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame('86.50', $defaultUser->balance);
        $this->assertSame('913.50', $defaultUser->locked_balance);
        $this->assertSame('1000.00', $counterparty->balance);
        $this->assertSame('0.00', $counterparty->locked_balance);
        $this->assertSame('1.00000000', $defaultBtc->amount);
        $this->assertSame('0.00000000', $defaultBtc->locked_amount);
        $this->assertSame('0.99000000', $counterpartyBtc->amount);
        $this->assertSame('0.01000000', $counterpartyBtc->locked_amount);
        $this->assertSame(OrderStatus::OPEN, $buyOrder->status);
        $this->assertSame(OrderStatus::OPEN, $sellOrder->status);
    }
}
