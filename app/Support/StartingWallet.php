<?php

namespace App\Support;

use App\Enums\AssetSymbol;

/**
 * Starting funds used by new accounts and hosted demo accounts.
 */
class StartingWallet
{
    /** USD available to a newly registered account. */
    public const USD_BALANCE = '100000.00';

    /** @var array<string, numeric-string> */
    public const ASSET_BALANCES = [
        AssetSymbol::BTC->value => '10.00000000',
        AssetSymbol::ETH->value => '100.00000000',
    ];
}
