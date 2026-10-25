<?php

namespace App\Actions\Trading;

use App\Enums\AssetSymbol;
use Illuminate\Support\Facades\DB;

/**
 * Serialize trading mutations for one market within the current transaction.
 */
final class AcquireMarketLock
{
    /**
     * Acquire a PostgreSQL transaction-scoped advisory lock for a symbol.
     *
     * The lock is released automatically when the surrounding transaction
     * commits or rolls back, so it cannot leak between requests.
     */
    public function execute(AssetSymbol $symbol): void
    {
        DB::select(
            'SELECT pg_advisory_xact_lock(CAST(? AS BIGINT))',
            [$this->keyFor($symbol)],
        );
    }

    /**
     * Derive a stable positive 60-bit PostgreSQL advisory-lock key.
     */
    protected function keyFor(AssetSymbol $symbol): int
    {
        return (int) hexdec(substr(hash('sha256', $symbol->value), 0, 15));
    }
}
