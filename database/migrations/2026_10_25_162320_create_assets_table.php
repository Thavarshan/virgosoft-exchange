<?php

use App\Enums\AssetSymbol;
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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained();
            // The database enum keeps persisted symbols within the domain.
            $table->enum('symbol', AssetSymbol::all());
            // Crypto quantities require eight decimal places of precision.
            $table->decimal('amount', 20, 8)->default(0);
            $table->decimal('locked_amount', 20, 8)->default(0);
            // A user may hold each supported symbol only once.
            $table->unique(['user_id', 'symbol'], 'assets_user_symbol_unique');
            $table->timestamps();
        });

        // Checks protect balances even when updates bypass Eloquent.
        DB::statement(
            'alter table "assets" add constraint "assets_amount_nonnegative" '
            .'check ("amount" >= 0)'
        );
        DB::statement(
            'alter table "assets" add constraint '
            .'"assets_locked_amount_nonnegative" check ("locked_amount" >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
