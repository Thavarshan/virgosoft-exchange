<?php

namespace Tests\Feature\Broadcasting;

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Tests\FeatureTestCase;

class PrivateChannelAuthorizationTest extends FeatureTestCase
{
    public function test_users_can_only_authorize_their_own_private_channel(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $authorize = Broadcast::getChannels()->get('user.{id}');

        $this->assertNotNull($authorize);
        $this->assertTrue($authorize($user, $user->id));
        $this->assertFalse($authorize($user, $otherUser->id));
    }
}
