<?php

namespace Tests\Feature\Trading;

use App\Actions\Trading\OrderData;
use App\Actions\Trading\PlaceOrder;
use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Throwable;

class ConcurrentOrderPlacementTest extends TestCase
{
    use DatabaseMigrations;

    public function test_opposite_concurrent_placements_settle_without_lost_balances(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required.');
        }

        $seller = User::factory()->create();
        Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '0.99000000',
            'locked_amount' => '0.01000000',
        ]);
        $buyer = User::factory()->create();
        $buyer->balance = '1035.75';
        $buyer->locked_balance = '964.25';
        $buyer->save();

        Order::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'price' => '95000.00',
            'amount' => '0.01000000',
            'status' => OrderStatus::OPEN,
        ]);
        Order::factory()->for($buyer)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'price' => '95000.00',
            'amount' => '0.01000000',
            'status' => OrderStatus::OPEN,
        ]);

        $directory = sys_get_temp_dir().'/virgosoft-concurrency-'.bin2hex(random_bytes(8));
        mkdir($directory);

        try {
            $this->runConcurrentPlacements($directory, $seller, $buyer);
        } finally {
            $this->removeTemporaryFiles($directory);
        }

        $seller->refresh();
        $buyer->refresh();
        $sellerAsset = Asset::query()
            ->whereBelongsTo($seller)
            ->where('symbol', AssetSymbol::BTC)
            ->firstOrFail();
        $buyerAsset = Asset::query()
            ->whereBelongsTo($buyer)
            ->where('symbol', AssetSymbol::BTC)
            ->firstOrFail();

        $this->assertSame(4, Order::query()->count());
        $this->assertSame(0, Order::query()->where('status', OrderStatus::OPEN)->count());
        $this->assertSame('1900.00', $seller->balance);
        $this->assertSame('0.00', $seller->locked_balance);
        $this->assertSame('71.50', $buyer->balance);
        $this->assertSame('0.00', $buyer->locked_balance);
        $this->assertSame('0.98000000', $sellerAsset->amount);
        $this->assertSame('0.00000000', $sellerAsset->locked_amount);
        $this->assertSame('0.02000000', $buyerAsset->amount);
        $this->assertSame('0.00000000', $buyerAsset->locked_amount);
    }

    public function test_opposite_concurrent_placements_on_an_empty_book_are_matched(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required.');
        }

        $seller = User::factory()->create();
        Asset::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'amount' => '1.00000000',
        ]);
        $buyer = User::factory()->create();
        $buyer->balance = '1000.00';
        $buyer->save();

        $directory = sys_get_temp_dir().'/virgosoft-empty-book-'.bin2hex(random_bytes(8));
        mkdir($directory);

        try {
            $this->runConcurrentPlacements($directory, $seller, $buyer);
        } finally {
            $this->removeTemporaryFiles($directory);
        }

        $seller->refresh();
        $buyer->refresh();
        $sellerAsset = Asset::query()
            ->whereBelongsTo($seller)
            ->where('symbol', AssetSymbol::BTC)
            ->firstOrFail();
        $buyerAsset = Asset::query()
            ->whereBelongsTo($buyer)
            ->where('symbol', AssetSymbol::BTC)
            ->firstOrFail();

        $this->assertSame(2, Order::query()->count());
        $this->assertSame(
            2,
            Order::query()->where('status', OrderStatus::FILLED)->count(),
        );
        $this->assertSame('950.00', $seller->balance);
        $this->assertSame('0.00', $seller->locked_balance);
        $this->assertSame('35.75', $buyer->balance);
        $this->assertSame('0.00', $buyer->locked_balance);
        $this->assertSame('0.99000000', $sellerAsset->amount);
        $this->assertSame('0.00000000', $sellerAsset->locked_amount);
        $this->assertSame('0.01000000', $buyerAsset->amount);
        $this->assertSame('0.00000000', $buyerAsset->locked_amount);
    }

    /**
     * Start both workers behind a barrier so they enter placement together.
     */
    protected function runConcurrentPlacements(
        string $directory,
        User $seller,
        User $buyer,
    ): void {
        $workers = [
            'seller' => [
                'userId' => $seller->id,
                'side' => OrderSide::SELL,
            ],
            'buyer' => [
                'userId' => $buyer->id,
                'side' => OrderSide::BUY,
            ],
        ];
        $processIds = [];

        foreach ($workers as $name => $worker) {
            $processId = pcntl_fork();

            if ($processId === -1) {
                $this->fail('Unable to fork the concurrent placement worker.');
            }

            if ($processId === 0) {
                $this->executePlacementWorker(
                    $directory,
                    $name,
                    $worker['userId'],
                    $worker['side'],
                );
            }

            $processIds[] = $processId;
        }

        $this->waitForWorkers($directory, array_keys($workers));
        touch($directory.'/release');

        foreach ($processIds as $processId) {
            pcntl_waitpid($processId, $status);
            $this->assertTrue(
                pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0,
                'A concurrent placement worker failed.',
            );
        }

        foreach (array_keys($workers) as $name) {
            $result = json_decode(
                file_get_contents($directory.'/'.$name.'.result'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            $this->assertSame('ok', $result['status'], (string) ($result['message'] ?? ''));
        }
    }

    /**
     * Execute one placement with a fresh database connection in a child.
     */
    protected function executePlacementWorker(
        string $directory,
        string $name,
        int $userId,
        OrderSide $side,
    ): never {
        try {
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            Event::fake([OrderMatched::class]);
            file_put_contents($directory.'/'.$name.'.ready', 'ready');

            while (! file_exists($directory.'/release')) {
                usleep(1_000);
            }

            $data = new OrderData(
                symbol: AssetSymbol::BTC,
                side: $side,
                price: '95000.00',
                amount: '0.01000000',
            );
            app(PlaceOrder::class)->execute(
                User::query()->findOrFail($userId),
                $data,
            );
            file_put_contents($directory.'/'.$name.'.result', json_encode([
                'status' => 'ok',
            ], JSON_THROW_ON_ERROR));
        } catch (Throwable $exception) {
            file_put_contents($directory.'/'.$name.'.result', json_encode([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], JSON_THROW_ON_ERROR));
        }

        exit(0);
    }

    /**
     * Wait until every worker has established its independent connection.
     *
     * @param  array<int, string>  $workerNames
     */
    protected function waitForWorkers(string $directory, array $workerNames): void
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $ready = array_filter(
                $workerNames,
                fn (string $name): bool => file_exists($directory.'/'.$name.'.ready'),
            );

            if (count($ready) === count($workerNames)) {
                return;
            }

            usleep(1_000);
        }

        $this->fail('Concurrent placement workers did not become ready.');
    }

    /**
     * Remove only the temporary files created by this test.
     */
    protected function removeTemporaryFiles(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($directory);
    }
}
