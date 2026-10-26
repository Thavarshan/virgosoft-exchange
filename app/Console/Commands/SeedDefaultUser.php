<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\DefaultUserSeeder;
use Illuminate\Console\Command;

class SeedDefaultUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed-default-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the default local development user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->laravel->environment(['local', 'testing'])) {
            $this->error(
                'Demo users can only be seeded in local or testing environments.',
            );

            return self::FAILURE;
        }

        $wasExisting = User::query()
            ->where('email', DefaultUserSeeder::EMAIL)
            ->exists();

        $this->call(DefaultUserSeeder::class);

        $this->info($wasExisting ? 'Default user already exists.' : 'Default user created.');

        return self::SUCCESS;
    }
}
