<?php

namespace Tests\Feature\Broadcasting;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderMatchedBroadcastTest extends TestCase
{
    use DatabaseMigrations;

    public function test_order_matched_is_queued_after_a_successful_commit(): void
    {
        $seller = User::factory()->create();
        Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $buyer = User::factory()->create();
        $buyer->balance = '1000.00';
        $buyer->save();
        Queue::fake();

        $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        DB::beginTransaction();

        $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        Queue::assertNothingPushed();
        DB::commit();

        Queue::assertPushedTimes(BroadcastEvent::class, 1);
        Queue::assertPushed(BroadcastEvent::class, function (
            BroadcastEvent $job,
        ) use ($buyer, $seller): bool {
            return $job->event instanceof OrderMatched
                && $job->event->buyOrder->user_id === $buyer->id
                && $job->event->sellOrder->user_id === $seller->id;
        });
    }

    public function test_rolling_back_the_outer_transaction_discards_the_broadcast(): void
    {
        $seller = User::factory()->create();
        Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $buyer = User::factory()->create();
        $buyer->balance = '1000.00';
        $buyer->save();
        $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ])->assertCreated();
        Queue::fake();

        DB::beginTransaction();

        try {
            $this->actingAs($buyer)->postJson('/api/orders', [
                'symbol' => AssetSymbol::BTC->value,
                'side' => OrderSide::BUY->value,
                'price' => '95000.00',
                'amount' => '0.01000000',
            ])->assertCreated();

            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }

        Queue::assertNotPushed(BroadcastEvent::class);
    }

    public function test_failed_matching_never_queues_order_matched(): void
    {
        $seller = User::factory()->create();
        $sellerAsset = Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ])->assertCreated();
        $sellerAsset->locked_amount = '0.00000000';
        $sellerAsset->save();

        $buyer = User::factory()->create();
        $buyer->balance = '1000.00';
        $buyer->save();
        Queue::fake();

        $response = $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);

        $response->assertUnprocessable();
        Queue::assertNotPushed(BroadcastEvent::class);
    }
}
