<?php

namespace Tests\Feature\Trading;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

class OrderHistoryTest extends FeatureTestCase
{
    public function test_guests_cannot_view_order_history(): void
    {
        $response = $this->getJson('/api/profile/orders');

        $response->assertUnauthorized();
    }

    public function test_users_receive_only_their_own_orders_including_all_statuses(): void
    {
        $user = User::factory()->create();
        $openOrder = Order::factory()->for($user)->create([
            'status' => OrderStatus::OPEN,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ]);
        $filledOrder = Order::factory()->for($user)->create([
            'status' => OrderStatus::FILLED,
            'price' => '21000.00',
            'amount' => '0.02000000',
        ]);
        $cancelledOrder = Order::factory()->for($user)->create([
            'status' => OrderStatus::CANCELLED,
            'price' => '19000.00',
            'amount' => '0.00500000',
        ]);
        Order::factory()->for(User::factory())->create();

        $response = $this->actingAs($user)->getJson('/api/profile/orders');

        $response->assertOk();
        $response->assertJsonStructure([
            'orders' => [
                ['id', 'price', 'amount', 'status'],
            ],
            'links',
            'meta',
        ]);
        $response->assertJsonCount(3, 'orders');

        $returnedIds = collect($response->json('orders'))
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([
            $openOrder->id,
            $filledOrder->id,
            $cancelledOrder->id,
        ], $returnedIds);

        foreach ($response->json('orders') as $order) {
            $this->assertIsString($order['price']);
            $this->assertIsString($order['amount']);
        }
        $response->assertJsonMissingPath('data');
    }

    public function test_order_history_is_newest_first_with_id_tiebreaking(): void
    {
        $user = User::factory()->create();
        $oldest = Carbon::parse('2026-01-01 10:00:00');
        $sameTimestamp = Carbon::parse('2026-01-02 10:00:00');

        $firstSameTimestampOrder = Order::factory()->for($user)->create([
            'created_at' => $sameTimestamp,
            'updated_at' => $sameTimestamp,
        ]);
        $secondSameTimestampOrder = Order::factory()->for($user)->create([
            'created_at' => $sameTimestamp,
            'updated_at' => $sameTimestamp,
        ]);
        // Give the oldest record the highest ID so time must take priority.
        $oldestOrder = Order::factory()->for($user)->create([
            'created_at' => $oldest,
            'updated_at' => $oldest,
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile/orders');

        $response->assertOk();
        $this->assertSame([
            $secondSameTimestampOrder->id,
            $firstSameTimestampOrder->id,
            $oldestOrder->id,
        ], collect($response->json('orders'))->pluck('id')->all());
        $response->assertJsonMissingPath('data');
    }

    public function test_users_without_orders_receive_an_empty_history(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/profile/orders');

        $response->assertOk();
        $response->assertJsonPath('orders', []);
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonMissingPath('data');
    }

    public function test_order_history_uses_twenty_orders_per_page(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(21)->for($user)->create();

        $firstPage = $this->actingAs($user)->getJson('/api/profile/orders');
        $secondPage = $this->actingAs($user)->getJson('/api/profile/orders?page=2');

        $firstPage->assertOk();
        $firstPage->assertJsonCount(20, 'orders');
        $firstPage->assertJsonPath('meta.per_page', 20);
        $firstPage->assertJsonPath('meta.total', 21);
        $firstPage->assertJsonPath('meta.current_page', 1);

        $secondPage->assertOk();
        $secondPage->assertJsonCount(1, 'orders');
        $secondPage->assertJsonPath('meta.current_page', 2);
    }

    public function test_order_history_filters_by_symbol(): void
    {
        $user = User::factory()->create();
        $btcOrder = Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
        ]);
        Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::ETH,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/profile/orders?symbol=BTC');

        $response->assertOk();
        $response->assertJsonPath('orders.0.id', $btcOrder->id);
        $response->assertJsonCount(1, 'orders');
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_order_history_filters_by_side(): void
    {
        $user = User::factory()->create();
        $sellOrder = Order::factory()->for($user)->create([
            'side' => OrderSide::SELL,
        ]);
        Order::factory()->for($user)->create([
            'side' => OrderSide::BUY,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/profile/orders?side=sell');

        $response->assertOk();
        $response->assertJsonPath('orders.0.id', $sellOrder->id);
        $response->assertJsonCount(1, 'orders');
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_order_history_filters_by_status(): void
    {
        $user = User::factory()->create();
        $filledOrder = Order::factory()->for($user)->create([
            'status' => OrderStatus::FILLED,
        ]);
        Order::factory()->for($user)->create([
            'status' => OrderStatus::OPEN,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/profile/orders?status=2');

        $response->assertOk();
        $response->assertJsonPath('orders.0.id', $filledOrder->id);
        $response->assertJsonCount(1, 'orders');
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_order_history_combines_symbol_side_and_status_filters(): void
    {
        $user = User::factory()->create();
        $matchingOrder = Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::ETH,
            'side' => OrderSide::SELL,
            'status' => OrderStatus::CANCELLED,
        ]);
        Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::ETH,
            'side' => OrderSide::SELL,
            'status' => OrderStatus::OPEN,
        ]);
        Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::ETH,
            'side' => OrderSide::BUY,
            'status' => OrderStatus::CANCELLED,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/profile/orders?symbol=ETH&side=sell&status=3');

        $response->assertOk();
        $response->assertJsonPath('orders.0.id', $matchingOrder->id);
        $response->assertJsonCount(1, 'orders');
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_order_history_filters_before_pagination(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(10)->for($user)->create([
            'symbol' => AssetSymbol::ETH,
        ]);
        $btcOrders = Order::factory()->count(20)->for($user)->create([
            'symbol' => AssetSymbol::BTC,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/profile/orders?symbol=BTC');

        $response->assertOk();
        $response->assertJsonCount(20, 'orders');
        $response->assertJsonPath('meta.total', 20);
        $response->assertJsonPath('orders.0.id', $btcOrders->last()->id);
    }

    public function test_order_history_preserves_filters_in_pagination_links(): void
    {
        $user = User::factory()->create();
        Order::factory()->count(21)->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'status' => OrderStatus::OPEN,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/profile/orders?symbol=BTC&side=sell&status=1');

        $response->assertOk();
        $this->assertStringContainsString(
            'symbol=BTC',
            (string) $response->json('links.next'),
        );
        $this->assertStringContainsString(
            'side=sell',
            (string) $response->json('links.next'),
        );
        $this->assertStringContainsString(
            'status=1',
            (string) $response->json('links.next'),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHistoryFilters(): array
    {
        return [
            'unsupported symbol' => ['symbol=DOGE'],
            'unsupported side' => ['side=hold'],
            'unsupported status' => ['status=4'],
            'invalid page' => ['page=0'],
        ];
    }

    #[DataProvider('invalidHistoryFilters')]
    public function test_invalid_order_history_filters_return_validation_errors(
        string $query,
    ): void {
        $response = $this->actingAs(User::factory()->create())
            ->getJson("/api/profile/orders?{$query}");

        $response->assertUnprocessable();
        $response->assertJsonStructure(['message', 'errors']);
    }

    public function test_order_history_filters_do_not_expose_another_users_orders(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherOrder = Order::factory()->for($otherUser)->create([
            'symbol' => AssetSymbol::ETH,
            'side' => OrderSide::SELL,
            'status' => OrderStatus::FILLED,
        ]);
        Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/profile/orders?symbol=ETH&side=sell&status=2');

        $response->assertOk();
        $response->assertJsonPath('orders', []);
        $response->assertJsonPath('meta.total', 0);
        $response->assertJsonMissing(['id' => $otherOrder->id]);
    }
}
