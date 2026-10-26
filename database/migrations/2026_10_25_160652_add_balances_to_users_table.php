<?php

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
        Schema::table('users', function (Blueprint $table): void {
            // USD balances are stored at cent precision for exact money math.
            $table->decimal('balance', 20, 2)->default(0);
            $table->decimal('locked_balance', 20, 2)->default(0);
        });

        // Database checks also protect balances changed outside Eloquent.
        DB::statement(
            'alter table "users" add constraint "users_balance_nonnegative" '
            .'check ("balance" >= 0)'
        );
        DB::statement(
            'alter table "users" add constraint "users_locked_balance_nonnegative" '
            .'check ("locked_balance" >= 0)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(
            'alter table "users" drop constraint if exists '
            .'"users_locked_balance_nonnegative"'
        );
        DB::statement(
            'alter table "users" drop constraint if exists '
            .'"users_balance_nonnegative"'
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['balance', 'locked_balance']);
        });
    }
};
