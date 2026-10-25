<?php

namespace Tests\Feature\Settings;

use Tests\FeatureTestCase;

class ProfileUpdateTest extends FeatureTestCase
{
    public function test_the_wallet_route_is_available_as_a_frontend_shell(): void
    {
        $this->get('/wallet')->assertOk();
    }
}
