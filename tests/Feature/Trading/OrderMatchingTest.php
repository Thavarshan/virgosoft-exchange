<?php

namespace Tests\Feature\Trading;

use App\Actions\Trading\CalculateCommission;
use App\Actions\Trading\MatchOrder;
use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\FeatureTestCase;

class OrderMatchingTest extends FeatureTestCase
{
    public function test_commission_rounds_up_using_the_fifth_decimal_place(): void
    {
        $commission = app(CalculateCommission::class)->execute('0.67');

        $this->assertSame('0.02', $commission);
    }

    public function test_equal_buy_and_sell_orders_are_matched_and_settled(): void
    {
        $seller = User::factory()->create();
        $sellerAsset = Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $buyer = User::factory()->create();
        $buyer->balance = '1000.00';
        $buyer->save();

        $sellPlacement = $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);
        $sellPlacement->assertCreated();

        $sellOrder = Order::query()->latest('id')->firstOrFail();

        Event::fake([OrderMatched::class]);

        $buyPlacement = $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);

        $buyPlacement->assertCreated();
        $buyOrder = Order::query()->latest('id')->firstOrFail();

        $buyOrder->refresh();
        $sellOrder->refresh();
        $buyer->refresh();
        $seller->refresh();
        $sellerAsset->refresh();
        $buyerAsset = Asset::query()
            ->whereBelongsTo($buyer)
            ->where('symbol', AssetSymbol::BTC)
            ->firstOrFail();

        $this->assertSame(OrderStatus::FILLED, $buyOrder->status);
        $this->assertSame(OrderStatus::FILLED, $sellOrder->status);
        $this->assertSame('35.75', $buyer->balance);
        $this->assertSame('0.00', $buyer->locked_balance);
        $this->assertSame('0.01000000', $buyerAsset->amount);
        $this->assertSame('0.00000000', $buyerAsset->locked_amount);
        $this->assertSame('950.00', $seller->balance);
        $this->assertSame('0.00', $seller->locked_balance);
        $this->assertSame('0.99000000', $sellerAsset->amount);
        $this->assertSame('0.00000000', $sellerAsset->locked_amount);

        $this->assertDatabaseCount('trades', 1);
        $trade = Trade::query()->firstOrFail();

        $this->assertSame($buyOrder->id, $trade->buy_order_id);
        $this->assertSame($sellOrder->id, $trade->sell_order_id);
        $this->assertSame(AssetSymbol::BTC, $trade->symbol);
        $this->assertSame('95000.00', $trade->price);
        $this->assertSame('0.01000000', $trade->amount);
        $this->assertSame('950.00', $trade->trade_value);
        $this->assertSame('14.25', $trade->commission);
        $this->assertTrue($trade->buyOrder->is($buyOrder));
        $this->assertTrue($trade->sellOrder->is($sellOrder));

        Event::assertDispatched(OrderMatched::class, function (
            OrderMatched $event,
        ) use ($buyOrder, $sellOrder, $buyer, $seller): bool {
            $channels = array_map(
                static fn ($channel): string => $channel->name,
                $event->broadcastOn(),
            );

            return $event->buyOrder->is($buyOrder)
                && $event->sellOrder->is($sellOrder)
                && $event->broadcastConnections() === ['pusher']
                && $channels === [
                    'private-user.'.$buyer->id,
                    'private-user.'.$seller->id,
                ];
        });
    }

    public function test_a_user_cannot_match_against_their_own_order(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $user->balance = '1000.00';
        $user->save();

        $sellPlacement = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);
        $sellPlacement->assertCreated();

        $buyPlacement = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);
        $buyPlacement->assertCreated();

        $orders = Order::query()->orderBy('id')->get();
        $user->refresh();
        $asset->refresh();

        $this->assertCount(2, $orders);
        $this->assertSame(OrderStatus::OPEN, $orders[0]->status);
        $this->assertSame(OrderStatus::OPEN, $orders[1]->status);
        $this->assertSame('35.75', $user->balance);
        $this->assertSame('964.25', $user->locked_balance);
        $this->assertSame('0.99000000', $asset->amount);
        $this->assertSame('0.01000000', $asset->locked_amount);
    }

    public function test_new_sell_orders_match_existing_buys_at_the_buy_price(): void
    {
        $buyer = User::factory()->create();
        $buyer->balance = '1000.00';
        $buyer->save();
        $seller = User::factory()->create();
        $sellerAsset = Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);

        $buyPlacement = $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);
        $buyPlacement->assertCreated();
        $buyOrder = Order::query()->latest('id')->firstOrFail();

        Event::fake([OrderMatched::class]);

        $sellPlacement = $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '90000.00',
            'amount' => '0.01000000',
        ]);
        $sellPlacement->assertCreated();
        $sellOrder = Order::query()->latest('id')->firstOrFail();

        $buyOrder->refresh();
        $sellOrder->refresh();
        $buyer->refresh();
        $seller->refresh();
        $sellerAsset->refresh();
        $buyerAsset = Asset::query()
            ->whereBelongsTo($buyer)
            ->where('symbol', AssetSymbol::BTC)
            ->firstOrFail();

        $this->assertSame(OrderStatus::FILLED, $buyOrder->status);
        $this->assertSame(OrderStatus::FILLED, $sellOrder->status);
        $this->assertSame('950.00', $seller->balance);
        $this->assertSame('0.00', $seller->locked_balance);
        $this->assertSame('35.75', $buyer->balance);
        $this->assertSame('0.00', $buyer->locked_balance);
        $this->assertSame('0.01000000', $buyerAsset->amount);
        $this->assertSame('0.00000000', $buyerAsset->locked_amount);
        $this->assertSame('0.99000000', $sellerAsset->amount);
        $this->assertSame('0.00000000', $sellerAsset->locked_amount);

        Event::assertDispatched(
            OrderMatched::class,
            fn (OrderMatched $event): bool => $event->executionPrice === '95000.00',
        );
    }

    public function test_matching_selects_the_oldest_compatible_counter_order(): void
    {
        $firstSeller = User::factory()->create();
        $firstAsset = Asset::factory()->for($firstSeller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $secondSeller = User::factory()->create();
        $secondAsset = Asset::factory()->for($secondSeller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $buyer = User::factory()->create();
        $buyer->balance = '1100.00';
        $buyer->save();

        $firstPlacement = $this->actingAs($firstSeller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);
        $firstPlacement->assertCreated();
        $firstOrder = Order::query()->latest('id')->firstOrFail();
        $firstOrder->created_at = now()->subMinute();
        $firstOrder->saveQuietly();

        $secondPlacement = $this->actingAs($secondSeller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '94000.00',
            'amount' => '0.01000000',
        ]);
        $secondPlacement->assertCreated();
        $secondOrder = Order::query()->latest('id')->firstOrFail();

        Event::fake([OrderMatched::class]);

        $buyPlacement = $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '96000.00',
            'amount' => '0.01000000',
        ]);
        $buyPlacement->assertCreated();

        $firstOrder->refresh();
        $secondOrder->refresh();

        $this->assertSame(OrderStatus::FILLED, $firstOrder->status);
        $this->assertSame(OrderStatus::OPEN, $secondOrder->status);
        $this->assertSame('950.00', $firstSeller->refresh()->balance);
        $this->assertSame('0.00', $secondSeller->refresh()->balance);
        $this->assertSame('0.99000000', $firstAsset->refresh()->amount);
        $this->assertSame('0.01000000', $secondAsset->refresh()->locked_amount);
        Event::assertDispatched(
            OrderMatched::class,
            fn (OrderMatched $event): bool => $event->executionPrice === '95000.00',
        );
    }

    public function test_orders_with_different_quantities_remain_open(): void
    {
        $seller = User::factory()->create();
        $sellerAsset = Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $buyer = User::factory()->create();
        $buyer->balance = '3000.00';
        $buyer->save();

        $sellPlacement = $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);
        $sellPlacement->assertCreated();
        $sellOrder = Order::query()->latest('id')->firstOrFail();

        $buyPlacement = $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '100000.00',
            'amount' => '0.02000000',
        ]);
        $buyPlacement->assertCreated();
        $buyOrder = Order::query()->latest('id')->firstOrFail();

        $this->assertSame(OrderStatus::OPEN, $sellOrder->refresh()->status);
        $this->assertSame(OrderStatus::OPEN, $buyOrder->refresh()->status);
        $this->assertSame('0.99000000', $sellerAsset->refresh()->amount);
        $this->assertSame('0.01000000', $sellerAsset->locked_amount);
        $this->assertSame('2030.00', $buyer->refresh()->locked_balance);
    }

    public function test_orders_with_incompatible_prices_remain_open(): void
    {
        $seller = User::factory()->create();
        $sellerAsset = Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $buyer = User::factory()->create();
        $buyer->balance = '2000.00';
        $buyer->save();

        $sellPlacement = $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '96000.00',
            'amount' => '0.01000000',
        ]);
        $sellPlacement->assertCreated();
        $sellOrder = Order::query()->latest('id')->firstOrFail();

        $buyPlacement = $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);
        $buyPlacement->assertCreated();
        $buyOrder = Order::query()->latest('id')->firstOrFail();

        $this->assertSame(OrderStatus::OPEN, $sellOrder->refresh()->status);
        $this->assertSame(OrderStatus::OPEN, $buyOrder->refresh()->status);
        $this->assertSame('0.99000000', $sellerAsset->refresh()->amount);
        $this->assertSame('0.01000000', $sellerAsset->locked_amount);
        $this->assertSame('964.25', $buyer->refresh()->locked_balance);
    }

    public function test_price_improvement_refunds_the_buyers_unused_reservation(): void
    {
        $seller = User::factory()->create();
        Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $buyer = User::factory()->create();
        $buyer->balance = '1100.00';
        $buyer->save();

        $sellPlacement = $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);
        $sellPlacement->assertCreated();

        Event::fake([OrderMatched::class]);

        $buyPlacement = $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '100000.00',
            'amount' => '0.01000000',
        ]);
        $buyPlacement->assertCreated();

        $buyer->refresh();

        $this->assertSame('135.75', $buyer->balance);
        $this->assertSame('0.00', $buyer->locked_balance);

        $trade = Trade::query()->firstOrFail();

        $this->assertSame('95000.00', $trade->price);
        $this->assertSame('950.00', $trade->trade_value);
        $this->assertSame('14.25', $trade->commission);
    }

    public function test_failed_seller_settlement_rolls_back_buyer_settlement(): void
    {
        $seller = User::factory()->create();
        $sellerAsset = Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);

        $sellPlacement = $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);
        $sellPlacement->assertCreated();
        $sellOrder = Order::query()->latest('id')->firstOrFail();

        // Simulate an inconsistent reservation after the sell was placed.
        $sellerAsset->locked_amount = '0.00000000';
        $sellerAsset->save();

        $buyer = User::factory()->create();
        $buyer->balance = '1000.00';
        $buyer->save();
        Event::fake([OrderMatched::class]);

        $buyPlacement = $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ]);

        $buyPlacement->assertUnprocessable();
        $buyPlacement->assertJsonValidationErrors('asset');
        $buyer->refresh();
        $seller->refresh();
        $sellOrder->refresh();
        $sellerAsset->refresh();

        $this->assertSame('1000.00', $buyer->balance);
        $this->assertSame('0.00', $buyer->locked_balance);
        $this->assertDatabaseMissing('assets', [
            'user_id' => $buyer->id,
            'symbol' => AssetSymbol::BTC->value,
        ]);
        $this->assertDatabaseMissing('orders', [
            'user_id' => $buyer->id,
        ]);
        $this->assertSame('0.00', $seller->balance);
        $this->assertSame('0.00', $seller->locked_balance);
        $this->assertSame(OrderStatus::OPEN, $sellOrder->status);
        $this->assertSame('0.00000000', $sellerAsset->locked_amount);
        $this->assertDatabaseCount('trades', 0);
        Event::assertNotDispatched(OrderMatched::class);
    }

    public function test_matching_locks_participants_in_primary_key_order(): void
    {
        $seller = User::factory()->create();
        Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '0.99000000',
            'locked_amount' => '0.01000000',
        ]);
        $buyer = User::factory()->create([
            'locked_balance' => '964.25',
        ]);
        $sellOrder = Order::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'price' => '95000.00',
            'amount' => '0.01000000',
            'status' => OrderStatus::OPEN,
        ]);
        $buyOrder = Order::factory()->for($buyer)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'price' => '95000.00',
            'amount' => '0.01000000',
            'status' => OrderStatus::OPEN,
        ]);
        $lockedUserIds = [];

        DB::listen(function (QueryExecuted $query) use (&$lockedUserIds): void {
            if (
                str_contains($query->sql, 'from "users"')
                && str_contains($query->sql, 'for update')
            ) {
                $lockedUserIds[] = (int) $query->bindings[0];
            }
        });
        Event::fake([OrderMatched::class]);

        DB::transaction(function () use ($buyOrder): void {
            app(MatchOrder::class)->execute($buyOrder);
        });

        $this->assertSame([$seller->id, $buyer->id], $lockedUserIds);
        $this->assertNotSame($seller->id, $buyer->id);
        $this->assertTrue($sellOrder->refresh()->status === OrderStatus::FILLED);
        $this->assertTrue($buyOrder->refresh()->status === OrderStatus::FILLED);
    }
}
