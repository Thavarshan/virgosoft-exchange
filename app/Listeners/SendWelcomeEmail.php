<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Auth\Events\Registered;

class SendWelcomeEmail
{
    /**
     * Queue the welcome guide to the new account's own email address.
     */
    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->notify(new WelcomeNotification(
            accountName: $event->user->name,
        ));
    }
}
