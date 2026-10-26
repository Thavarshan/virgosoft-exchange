<?php

namespace App\Console\Commands;

use App\Actions\Seeding\SeedDemoAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EnsureSmokeUser extends Command
{
    public const NAME = 'Smoke Check';

    public const BALANCE = '10.00';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ensure-smoke-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or refresh the account used by post-deployment smoke checks';

    /**
     * Execute the console command.
     */
    public function handle(SeedDemoAccount $seedDemoAccount): int
    {
        $email = config('services.smoke.email');
        $password = config('services.smoke.password');

        if (blank($email) && blank($password)) {
            $this->warn('Smoke user is not configured; skipping.');

            return self::SUCCESS;
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'min:12'],
            ],
        );

        if ($validator->fails()) {
            $this->error(
                'The smoke user needs a valid email and a password of at least 12 characters.',
            );

            return self::FAILURE;
        }

        // No asset balances: smoke orders are buys that are cancelled again.
        $user = $seedDemoAccount->execute(
            name: self::NAME,
            email: (string) $email,
            password: (string) $password,
            balance: self::BALANCE,
            assetBalances: [],
        );

        // Existing financial state is never touched, but a rotated secret
        // must keep working for the browser check.
        if (! Hash::check((string) $password, $user->password)) {
            $user->password = (string) $password;
            $user->save();
        }

        $this->info($user->wasRecentlyCreated
            ? 'Smoke user created.'
            : 'Smoke user already exists.');

        return self::SUCCESS;
    }
}
