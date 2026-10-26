<?php

namespace App\Console\Commands;

use App\Actions\Seeding\SeedDemoAccount;
use App\Models\User;
use App\Support\DemoAccounts;
use Illuminate\Console\Command;

class EnsureDemoAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ensure-demo-accounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the funded demo accounts when demo accounts are enabled';

    /**
     * Execute the console command.
     */
    public function handle(SeedDemoAccount $seedDemoAccount): int
    {
        if (! DemoAccounts::enabled()) {
            $this->warn('Demo accounts are not enabled; skipping.');

            return self::SUCCESS;
        }

        $created = 0;

        foreach (DemoAccounts::all() as $account) {
            $existed = User::query()
                ->where('email', $account['email'])
                ->exists();

            // Existing balances are never touched, so reruns on a live
            // environment cannot disturb reservations or open orders.
            $seedDemoAccount->execute(
                name: $account['name'],
                email: $account['email'],
                password: $account['password'],
                balance: DemoAccounts::BALANCE,
                assetBalances: DemoAccounts::ASSET_BALANCES,
            );

            $created += $existed ? 0 : 1;
        }

        $this->info($created > 0
            ? "Created {$created} demo account(s)."
            : 'Demo accounts already exist.');

        return self::SUCCESS;
    }
}
