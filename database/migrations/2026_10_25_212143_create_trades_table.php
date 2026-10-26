<?php

use App\Enums\AssetSymbol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();

            // Execution history must remain valid if an order is deleted.
            $table
                ->foreignId('buy_order_id')
                ->constrained('orders', 'id', 'trades_buy_order_id_foreign')
                ->restrictOnDelete();
            $table
                ->foreignId('sell_order_id')
                ->constrained('orders', 'id', 'trades_sell_order_id_foreign')
                ->restrictOnDelete();

            // Full matching allows each order to execute in only one trade.
            $table->unique('buy_order_id', 'trades_buy_order_id_unique');
            $table->unique('sell_order_id', 'trades_sell_order_id_unique');

            // The enum keeps trade symbols aligned with the order domain.
            $table->enum('symbol', AssetSymbol::all());
            // USD values use cents; asset quantities retain eight decimals.
            $table->decimal('price', 20, 2);
            $table->decimal('amount', 20, 8);
            $table->decimal('trade_value', 20, 2);
            $table->decimal('commission', 20, 2);
            $table->timestamps();
        });

        // These checks protect immutable execution records from invalid writes.
        DB::statement(
            'alter table "trades" add constraint "trades_price_positive" '
            .'check ("price" > 0)'
        );
        DB::statement(
            'alter table "trades" add constraint "trades_amount_positive" '
            .'check ("amount" > 0)'
        );
        DB::statement(
            'alter table "trades" add constraint "trades_trade_value_positive" '
            .'check ("trade_value" > 0)'
        );
        DB::statement(
            'alter table "trades" add constraint "trades_commission_nonnegative" '
            .'check ("commission" >= 0)'
        );
        DB::statement(
            'alter table "trades" add constraint "trades_orders_distinct" '
            .'check ("buy_order_id" <> "sell_order_id")'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'alter table "trades" drop constraint if exists '
            .'"trades_orders_distinct"'
        );
        DB::statement(
            'alter table "trades" drop constraint if exists '
            .'"trades_commission_nonnegative"'
        );
        DB::statement(
            'alter table "trades" drop constraint if exists '
            .'"trades_trade_value_positive"'
        );
        DB::statement(
            'alter table "trades" drop constraint if exists '
            .'"trades_amount_positive"'
        );
        DB::statement(
            'alter table "trades" drop constraint if exists '
            .'"trades_price_positive"'
        );

        Schema::dropIfExists('trades');
    }
};
