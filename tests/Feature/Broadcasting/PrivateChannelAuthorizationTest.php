<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

test('users can only authorize their own private channel', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $authorize = Broadcast::getChannels()->get('user.{id}');

    expect($authorize($user, $user->id))->toBeTrue();
    expect($authorize($user, $otherUser->id))->toBeFalse();
});
