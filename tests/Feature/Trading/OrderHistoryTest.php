<?php

namespace Tests\Feature\Trading;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
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
}
