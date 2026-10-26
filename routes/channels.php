<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id): bool {
    return (int) $user->id === (int) $id;
});
