<?php

namespace App\Console\Commands;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DefaultUserSeeder;
use Database\Seeders\DemoTradingSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('e2e:prepare {--reset : Truncate the existing schema instead of migrating it} {--activity : Add deterministic historical dashboard activity} {--history : Add deterministic order-history fixtures}')]
#[Description('Reset the isolated end-to-end test database')]
class PrepareE2eDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! app()->environment('testing')) {
            $this->error('The E2E database can only be prepared in testing.');

            return self::FAILURE;
        }

        if (config('database.default') !== 'pgsql') {
            $this->error('The E2E database must use PostgreSQL.');

            return self::FAILURE;
        }

        $database = config('database.connections.pgsql.database');

        if ($database !== 'virgosoft_e2e') {
            $this->error(
                'Refusing to reset a database other than virgosoft_e2e.',
            );

            return self::FAILURE;
        }

        if ($this->option('reset')) {
            $this->resetDatabase();

            if ($this->option('activity')) {
                $this->seedHistoricalActivity();
            }

            if ($this->option('history')) {
                $this->seedOrderHistory();
            }

            return self::SUCCESS;
        }

        return $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
    }

    /**
     * Clear application tables while keeping the schema available to workers.
     */
    protected function resetDatabase(): void
    {
        $tables = collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn (string $table): bool => $table === 'migrations')
            ->map(fn (string $table): string => '"'.str_replace('"', '""', $table).'"')
            ->implode(', ');

        if ($tables !== '') {
            DB::statement("TRUNCATE TABLE {$tables} RESTART IDENTITY CASCADE");
        }

        $this->call('db:seed', ['--force' => true]);
    }

    /**
     * Add activity on labelled and unlabelled dates for the 30-day chart.
     */
    protected function seedHistoricalActivity(): void
    {
        $buyer = User::query()
            ->where('email', DefaultUserSeeder::EMAIL)
            ->firstOrFail();
        $seller = User::query()
            ->where('email', DemoTradingSeeder::EMAIL)
            ->firstOrFail();
        $start = CarbonImmutable::now('UTC')->startOfDay()->subDays(29);

        foreach ([0, 1] as $offset) {
            $this->createHistoricalTrade($buyer, $seller, $start->addDays($offset));
        }
    }

    /**
     * Add owned orders covering the history filters and a second page.
     */
    protected function seedOrderHistory(): void
    {
        $user = User::query()
            ->where('email', DefaultUserSeeder::EMAIL)
            ->firstOrFail();

        Order::factory()->count(21)->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'status' => OrderStatus::OPEN,
        ]);
        Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::ETH,
            'side' => OrderSide::BUY,
            'status' => OrderStatus::FILLED,
        ]);
        Order::factory()->for($user)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'status' => OrderStatus::CANCELLED,
        ]);
    }

    /**
     * Create one completed trade without changing either demo account balance.
     */
    protected function createHistoricalTrade(
        User $buyer,
        User $seller,
        CarbonImmutable $createdAt,
    ): void {
        $buyOrder = Order::factory()->for($buyer)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::BUY,
            'price' => '100000.00',
            'amount' => '0.00100000',
            'status' => OrderStatus::FILLED,
        ]);
        $sellOrder = Order::factory()->for($seller)->create([
            'symbol' => AssetSymbol::BTC,
            'side' => OrderSide::SELL,
            'price' => '100000.00',
            'amount' => '0.00100000',
            'status' => OrderStatus::FILLED,
        ]);
        $trade = Trade::factory()->create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'symbol' => AssetSymbol::BTC,
            'price' => '100000.00',
            'amount' => '0.00100000',
            'trade_value' => '100.00',
            'commission' => '1.50',
        ]);

        $trade->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();
    }
}
