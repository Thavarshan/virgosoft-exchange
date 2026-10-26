<?php

namespace Tests\Feature\Trading;

use App\Models\Trade;
use Illuminate\Database\QueryException;
use Tests\FeatureTestCase;

class TradeTest extends FeatureTestCase
{
    public function test_database_rejects_nonpositive_price(): void
    {
        $trade = Trade::factory()->create();
        $trade->price = '0.00';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/trades_price_positive/');

        $trade->save();
    }

    public function test_database_rejects_nonpositive_amount(): void
    {
        $trade = Trade::factory()->create();
        $trade->amount = '0.00000000';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/trades_amount_positive/');

        $trade->save();
    }

    public function test_database_rejects_nonpositive_trade_value(): void
    {
        $trade = Trade::factory()->create();
        $trade->trade_value = '0.00';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/trades_trade_value_positive/');

        $trade->save();
    }

    public function test_database_rejects_negative_commission(): void
    {
        $trade = Trade::factory()->create();
        $trade->commission = '-0.01';

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/trades_commission_nonnegative/');

        $trade->save();
    }

    public function test_database_rejects_the_same_order_on_both_sides(): void
    {
        $trade = Trade::factory()->create();
        $trade->sell_order_id = $trade->buy_order_id;

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/trades_orders_distinct/');

        $trade->save();
    }

    public function test_database_rejects_reusing_a_buy_order(): void
    {
        $trade = Trade::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/trades_buy_order_id_unique/');

        Trade::factory()->create([
            'buy_order_id' => $trade->buy_order_id,
        ]);
    }

    public function test_database_rejects_reusing_a_sell_order(): void
    {
        $trade = Trade::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/trades_sell_order_id_unique/');

        Trade::factory()->create([
            'sell_order_id' => $trade->sell_order_id,
        ]);
    }
}
