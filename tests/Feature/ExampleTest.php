<?php

namespace Tests\Feature;

use Tests\FeatureTestCase;

class ExampleTest extends FeatureTestCase
{
    public function test_the_application_boots(): void
    {
        $this->get('/up')->assertOk();
    }
}
