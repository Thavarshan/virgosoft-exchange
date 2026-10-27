<?php

namespace Tests\Feature;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Models\Asset;
use App\Models\User;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderMatchedNotification;
use App\Notifications\OrderOpenedNotification;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TradingNotificationTest extends TestCase
{
    use DatabaseMigrations;

    /**
     * Configure the private inbox used for demo-account activity.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'services.trading_notifications.demo_email',
            'monitor@example.test',
        );
    }

    /**
     * Verify that a resting order sends its exact reservation to its owner.
     */
    public function test_open_order_notification_uses_reserved_amount_and_owner_email(): void
    {
        $user = User::factory()->create(['email' => 'trader@example.test']);
        $user->balance = '1000.00';
        $user->save();
        Queue::fake();

        $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        $queuedJob = Queue::pushed(SendQueuedNotifications::class)->first();
        $notification = $queuedJob->notification;
        $notifiable = $queuedJob->notifiables->first();

        $this->assertInstanceOf(OrderOpenedNotification::class, $notification);
        $this->assertInstanceOf(AnonymousNotifiable::class, $notifiable);
        $this->assertSame($user->email, $notifiable->routeNotificationFor('mail'));
        $this->assertSame('203.00', $notification->reservedAmount);
        $this->assertSame('0.01000000', $notification->amount);
        $this->assertSame('emails', $queuedJob->queue);
        $this->assertSame(3, $queuedJob->tries);
        $this->assertSame([10, 30, 60], $queuedJob->backoff());
        $this->assertSame(45, $queuedJob->timeout);
        $this->assertTrue($queuedJob->afterCommit);

        $mail = $notification->toMail($notifiable);
        $mail->assertTo($user->email);
        $this->assertStringContainsString('203.00 USD', $mail->render());

        Queue::assertPushed(SendQueuedNotifications::class, function (
            SendQueuedNotifications $job,
        ) use ($user): bool {
            $notification = $job->notification;
            $notifiable = $job->notifiables->first();

            return $notification instanceof OrderOpenedNotification
                && $notifiable instanceof AnonymousNotifiable
                && $notifiable->routeNotificationFor('mail') === $user->email
                && $notification->reservedAmount === '203.00'
                && $notification->amount === '0.01000000';
        });
        Queue::assertPushedOn('emails', SendQueuedNotifications::class);
    }

    /**
     * Verify demo routing, real-user routing, and actual match terms in receipts.
     */
    public function test_match_notifications_route_demo_to_monitor_and_real_user_to_their_email(): void
    {
        $seller = User::factory()->create(['email' => 'seller@example.test']);
        Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $buyer = User::factory()->create([
            'email' => 'demo@example.test',
            'is_demo' => true,
        ]);
        $buyer->balance = '2000.00';
        $buyer->save();
        Queue::fake();

        $this->actingAs($seller)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '100000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        Queue::assertPushedTimes(SendQueuedNotifications::class, 3);
        $this->assertSame(
            2,
            Queue::pushed(SendQueuedNotifications::class)
                ->filter(fn (SendQueuedNotifications $job): bool => $job->notification instanceof OrderMatchedNotification)
                ->count(),
        );
        Queue::assertPushed(SendQueuedNotifications::class, function (
            SendQueuedNotifications $job,
        ) use ($buyer): bool {
            $notification = $job->notification;
            $notifiable = $job->notifiables->first();

            return $notification instanceof OrderMatchedNotification
                && $notification->isDemo
                && $notification->orderPrice === '100000.00'
                && $notification->executionPrice === '95000.00'
                && $notification->tradeValue === '950.00'
                && $notification->commission === '14.25'
                && $notifiable instanceof AnonymousNotifiable
                && $notifiable->routeNotificationFor('mail') === 'monitor@example.test'
                && $notification->accountEmail === $buyer->email;
        });
        Queue::assertPushed(SendQueuedNotifications::class, function (
            SendQueuedNotifications $job,
        ) use ($seller): bool {
            $notification = $job->notification;
            $notifiable = $job->notifiables->first();

            return $notification instanceof OrderMatchedNotification
                && ! $notification->isDemo
                && $notifiable instanceof AnonymousNotifiable
                && $notifiable->routeNotificationFor('mail') === $seller->email;
        });
        Queue::assertPushedOn('emails', SendQueuedNotifications::class);

        $buyerNotification = Queue::pushed(SendQueuedNotifications::class)
            ->first(fn (SendQueuedNotifications $job): bool => $job->notification instanceof OrderMatchedNotification
                && $job->notification->isDemo);
        $buyerMail = $buyerNotification->notification->toMail(
            $buyerNotification->notifiables->first(),
        );

        $this->assertStringContainsString('100000.00 USD', $buyerMail->render());
        $this->assertStringContainsString('95000.00 USD', $buyerMail->render());
        $this->assertStringContainsString('14.25 USD', $buyerMail->render());
    }

    /**
     * Avoid sending an open-order receipt when matching fills the new order.
     */
    public function test_matching_order_does_not_also_queue_an_open_order_notification(): void
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

        Queue::assertPushedTimes(SendQueuedNotifications::class, 1);

        $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        Queue::assertPushedTimes(SendQueuedNotifications::class, 3);
        $this->assertSame(
            1,
            Queue::pushed(SendQueuedNotifications::class)
                ->filter(fn (SendQueuedNotifications $job): bool => $job->notification instanceof OrderOpenedNotification)
                ->count(),
        );
    }

    /**
     * Verify cancellation mail reports the reservation that was released.
     */
    public function test_cancellation_notification_contains_the_released_reservation(): void
    {
        $user = User::factory()->create();
        $user->balance = '1000.00';
        $user->save();
        Queue::fake();

        $placement = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ]);
        $placement->assertCreated();
        $orderId = (int) $placement->json('order.id');

        $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel")
            ->assertOk();

        Queue::assertPushedTimes(SendQueuedNotifications::class, 2);
        Queue::assertPushed(SendQueuedNotifications::class, function (
            SendQueuedNotifications $job,
        ): bool {
            return $job->notification instanceof OrderCancelledNotification
                && $job->notification->releasedReservation === '203.00';
        });

        $cancelledNotification = Queue::pushed(SendQueuedNotifications::class)
            ->first(fn (SendQueuedNotifications $job): bool => $job->notification instanceof OrderCancelledNotification)
            ->notification;

        $this->assertStringContainsString(
            '203.00 USD',
            $cancelledNotification->toMail(new AnonymousNotifiable()->route(
                'mail',
                $user->email,
            ))->render(),
        );
    }

    /**
     * Verify sell cancellation mail reports the released asset quantity.
     */
    public function test_sell_cancellation_notification_contains_released_assets(): void
    {
        $user = User::factory()->create();
        Asset::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        Queue::fake();

        $placement = $this->actingAs($user)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::SELL->value,
            'price' => '95000.00',
            'amount' => '0.25000000',
        ]);
        $placement->assertCreated();
        $orderId = (int) $placement->json('order.id');

        $this->actingAs($user)->postJson("/api/orders/{$orderId}/cancel")
            ->assertOk();

        Queue::assertPushed(SendQueuedNotifications::class, function (
            SendQueuedNotifications $job,
        ): bool {
            $notification = $job->notification;

            return $notification instanceof OrderCancelledNotification
                && $notification->side === OrderSide::SELL
                && $notification->releasedReservation === '0.25000000';
        });

        $cancelledNotification = Queue::pushed(SendQueuedNotifications::class)
            ->first(fn (SendQueuedNotifications $job): bool => $job->notification instanceof OrderCancelledNotification)
            ->notification;

        $this->assertStringContainsString(
            '0.25000000 BTC',
            $cancelledNotification->toMail(new AnonymousNotifiable()->route(
                'mail',
                $user->email,
            ))->render(),
        );
    }

    /**
     * Verify a rolled-back match never queues participant email.
     */
    public function test_notifications_wait_for_commit_and_are_discarded_on_rollback(): void
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

        Queue::assertNotPushed(SendQueuedNotifications::class);
        $this->assertDatabaseCount('trades', 0);
    }

    /**
     * Verify a successful match queues mail only after an outer commit.
     */
    public function test_notifications_are_queued_after_an_outer_transaction_commits(): void
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

        $this->actingAs($buyer)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '95000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        Queue::assertNothingPushed();
        DB::commit();

        Queue::assertPushedTimes(SendQueuedNotifications::class, 2);
        $this->assertDatabaseCount('trades', 1);
    }

    /**
     * Verify demo receipts fail closed when their monitoring inbox is missing.
     */
    public function test_demo_trading_mail_is_skipped_when_the_monitoring_address_is_unset(): void
    {
        config()->set('services.trading_notifications.demo_email', null);

        $demo = User::factory()->create(['is_demo' => true]);
        $demo->balance = '1000.00';
        $demo->save();
        Queue::fake();

        $this->actingAs($demo)->postJson('/api/orders', [
            'symbol' => AssetSymbol::BTC->value,
            'side' => OrderSide::BUY->value,
            'price' => '20000.00',
            'amount' => '0.01000000',
        ])->assertCreated();

        Queue::assertNothingPushed();
        $this->assertTrue($demo->is_demo);
    }
}
