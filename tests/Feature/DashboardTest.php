<?php

namespace Tests\Feature;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\FeatureTestCase;

class DashboardTest extends FeatureTestCase
{
    public function test_guests_receive_401_from_the_dashboard_api(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_rejects_unsupported_periods(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/dashboard?period=14');

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('period');
    }

    public function test_dashboard_contains_only_the_users_trades_and_uses_execution_terms(): void
    {
        $this->travelTo('2026-10-26 12:00:00');

        $user = User::factory()->create([
            'balance' => '1234.56',
            'locked_balance' => '200.00',
        ]);
        $counterparty = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCounterparty = User::factory()->create();

        $firstTrade = $this->createTrade(
            $user,
            $counterparty,
            [
                'price' => '95000.00',
                'trade_value' => '950.00',
                'commission' => '14.25',
            ],
            '2026-10-26 10:00:00',
        );
        $secondTrade = $this->createTrade(
            $counterparty,
            $user,
            [
                'symbol' => AssetSymbol::ETH,
                'price' => '2000.00',
                'amount' => '1.00000000',
                'trade_value' => '2000.00',
                'commission' => '30.00',
            ],
            '2026-10-25 10:00:00',
        );
        $this->createTrade(
            $otherUser,
            $otherCounterparty,
            [
                'price' => '99999.00',
                'trade_value' => '999.99',
            ],
            '2026-10-26 09:00:00',
        );

        $openOrder = Order::factory()->for($user)->create([
            'status' => OrderStatus::OPEN,
            'price' => '100.00',
        ]);
        Order::factory()->for($user)->create([
            'status' => OrderStatus::CANCELLED,
            'price' => '101.00',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/dashboard?period=7');

        $response->assertOk();
        $response->assertJsonPath('dashboard.period', 7);
        $response->assertJsonPath('dashboard.timezone', 'UTC');
        $response->assertJsonPath('dashboard.summary.available_usd', '1234.56');
        $response->assertJsonPath('dashboard.summary.reserved_usd', '200.00');
        $response->assertJsonPath('dashboard.summary.open_orders', 1);
        $response->assertJsonPath(
            'dashboard.summary.executed_trade_value',
            '2950.00',
        );
        $response->assertJsonCount(3, 'dashboard.order_status_breakdown');
        $response->assertJsonPath(
            'dashboard.recent_executions.0.id',
            $firstTrade->id,
        );
        $response->assertJsonPath(
            'dashboard.recent_executions.0.price',
            '95000.00',
        );
        $response->assertJsonPath(
            'dashboard.recent_executions.0.trade_value',
            '950.00',
        );
        $response->assertJsonPath(
            'dashboard.recent_executions.1.id',
            $secondTrade->id,
        );
        $response->assertJsonCount(2, 'dashboard.recent_executions');
        $response->assertJsonMissingPath('dashboard.recent_executions.2');
    }

    public function test_dashboard_returns_calendar_days_and_zero_value_days(): void
    {
        $this->travelTo('2026-10-26 12:00:00');

        $user = User::factory()->create();
        $counterparty = User::factory()->create();

        $trade = $this->createTrade(
            $user,
            $counterparty,
            ['trade_value' => '12.34'],
            '2026-10-20 00:00:00',
        );
        $this->createTrade(
            $user,
            $counterparty,
            ['trade_value' => '99.99'],
            '2026-10-19 23:59:59',
        );

        $response = $this->actingAs($user)->getJson('/api/dashboard?period=7');

        $response->assertOk();
        $response->assertJsonCount(7, 'dashboard.daily_executed_value');
        $response->assertJsonPath('dashboard.daily_executed_value.0.date', '2026-10-20');
        $response->assertJsonPath('dashboard.daily_executed_value.0.value', '12.34');
        $response->assertJsonPath('dashboard.daily_executed_value.1.value', '0.00');
        $response->assertJsonPath('dashboard.summary.executed_trade_value', '12.34');
        $this->assertSame($trade->id, $response->json('dashboard.recent_executions.0.id'));
    }

    public function test_users_without_dashboard_activity_receive_empty_activity(): void
    {
        $this->travelTo('2026-10-26 12:00:00');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/dashboard?period=30');

        $response->assertOk();
        $response->assertJsonPath('dashboard.period', 30);
        $response->assertJsonPath('dashboard.summary.executed_trade_value', '0.00');
        $response->assertJsonPath('dashboard.summary.open_orders', 0);
        $response->assertJsonCount(30, 'dashboard.daily_executed_value');
        $response->assertJsonPath('dashboard.recent_executions', []);
    }

    public function test_authenticated_users_can_access_their_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/session')
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createTrade(
        User $buyer,
        User $seller,
        array $attributes,
        string $createdAt,
    ): Trade {
        $buyOrder = Order::factory()->for($buyer)->create([
            'symbol' => $attributes['symbol'] ?? AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'status' => OrderStatus::FILLED,
            'price' => $attributes['limit_price'] ?? '100000.00',
            'amount' => $attributes['amount'] ?? '0.01000000',
        ]);
        $sellOrder = Order::factory()->for($seller)->create([
            'symbol' => $attributes['symbol'] ?? AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'status' => OrderStatus::FILLED,
            'price' => $attributes['limit_price'] ?? '95000.00',
            'amount' => $attributes['amount'] ?? '0.01000000',
        ]);
        $trade = Trade::factory()->create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            ...$attributes,
        ]);
        $timestamp = Carbon::parse($createdAt);

        $trade->forceFill([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->saveQuietly();

        return $trade;
    }
}
