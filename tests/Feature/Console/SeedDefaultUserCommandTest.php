<?php

namespace Tests\Feature\Console;

use Database\Seeders\DefaultUserSeeder;
use Tests\FeatureTestCase;

class SeedDefaultUserCommandTest extends FeatureTestCase
{
    public function test_the_default_user_command_is_idempotent(): void
    {
        $this->artisan('app:seed-default-user')
            ->expectsOutput('Default user created.')
            ->assertExitCode(0);

        $this->artisan('app:seed-default-user')
            ->expectsOutput('Default user already exists.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', [
            'name' => DefaultUserSeeder::NAME,
            'email' => DefaultUserSeeder::EMAIL,
        ]);
        $this->assertDatabaseCount('users', 1);
    }
}
