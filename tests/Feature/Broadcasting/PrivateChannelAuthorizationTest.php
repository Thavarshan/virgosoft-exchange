<?php

namespace Tests\Feature\Broadcasting;

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Tests\FeatureTestCase;

class PrivateChannelAuthorizationTest extends FeatureTestCase
{
    public function test_users_can_authorize_their_own_private_channel_endpoint(): void
    {
        $user = User::factory()->create();
        $this->usePusherBroadcaster();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-user.'.$user->id,
        ]);

        $response->assertOk();
    }

    public function test_users_cannot_authorize_another_users_private_channel_endpoint(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->usePusherBroadcaster();

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-user.'.$otherUser->id,
        ]);

        $response->assertForbidden();
    }

    public function test_guests_cannot_authorize_a_private_channel_endpoint(): void
    {
        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-user.1',
        ]);

        $response->assertUnauthorized();
    }

    public function test_users_can_only_authorize_their_own_private_channel(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $authorize = Broadcast::getChannels()->get('user.{id}');

        $this->assertNotNull($authorize);
        $this->assertTrue($authorize($user, $user->id));
        $this->assertFalse($authorize($user, $otherUser->id));
    }

    /**
     * Use Pusher's local signing implementation without contacting Pusher.
     */
    protected function usePusherBroadcaster(): void
    {
        Config::set([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app',
        ]);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
    }
}
