<?php

namespace Tests\Feature\Wallet;

use App\Models\User;
use Illuminate\Database\QueryException;
use Tests\FeatureTestCase;

class UserBalanceTest extends FeatureTestCase
{
    public function test_new_users_start_with_zero_decimal_balances(): void
    {
        $user = User::factory()->create();

        $user->refresh();

        $this->assertSame('0.00', $user->balance);
        $this->assertSame('0.00', $user->locked_balance);
    }

    public function test_users_retain_nonzero_balances_at_cent_precision(): void
    {
        $user = User::factory()->create();

        $user->balance = '1234.56';
        $user->locked_balance = '78.90';
        $user->save();
        $user->refresh();

        $this->assertSame('1234.56', $user->balance);
        $this->assertSame('78.90', $user->locked_balance);
    }

    public function test_database_rejects_negative_balance(): void
    {
        $user = User::factory()->create();
        $user->balance = '-0.01';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/users_balance_nonnegative/');

        $user->save();
    }

    public function test_database_rejects_negative_locked_balance(): void
    {
        $user = User::factory()->create();
        $user->locked_balance = '-0.01';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/users_locked_balance_nonnegative/');

        $user->save();
    }
}
