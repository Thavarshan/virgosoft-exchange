<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public const NAME = 'Demo Trader';

    public const EMAIL = 'demo@example.com';

    public const PASSWORD = 'password';

    /**
     * Seed the default local development user.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => self::NAME,
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
            ],
        );
    }
}
