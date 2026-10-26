<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Tests\FeatureTestCase;

class PrepareE2eDatabaseCommandTest extends FeatureTestCase
{
    /**
     * The command refuses to reset the PHPUnit database.
     */
    public function test_it_refuses_to_reset_a_non_e2e_database(): void
    {
        $user = User::factory()->create();

        $this->artisan('e2e:prepare')
            ->expectsOutputToContain(
                'Refusing to reset a database other than virgosoft_e2e.',
            )
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
