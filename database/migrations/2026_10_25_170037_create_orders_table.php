<?php

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\User;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained();
            $table->enum('symbol', AssetSymbol::all());
            $table->enum('side', OrderSide::all());
            // Price and amount are required; validation rules come next.
            $table->decimal('price', 20, 2);
            $table->decimal('amount', 20, 8);
            // Integer storage keeps lifecycle values stable and compact.
            $table->unsignedTinyInteger('status')
                ->default(OrderStatus::OPEN->value);
            $table->timestamps();
        });

        DB::statement(
            'alter table "orders" add constraint "orders_price_positive" '
            .'check ("price" > 0)'
        );
        DB::statement(
            'alter table "orders" add constraint "orders_amount_positive" '
            .'check ("amount" > 0)'
        );
        DB::statement(
            'alter table "orders" add constraint "orders_status_valid" '
            .'check ("status" in (1, 2, 3))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
