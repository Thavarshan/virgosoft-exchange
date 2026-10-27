<?php

namespace App\Actions\Notifications;

use App\Models\User;
use App\Support\TradingNotificationRecipient;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class SendTradingNotification
{
    /**
     * Create the notification dispatcher with the app's demo routing policy.
     */
    public function __construct(
        protected readonly TradingNotificationRecipient $recipient,
    ) {}

    /**
     * Queue a mail-only trading notification at its resolved destination.
     */
    public function execute(User $user, Notification $notification): void
    {
        $email = $this->recipient->for($user);

        if ($email === null) {
            return;
        }

        NotificationFacade::route('mail', $email)->notify($notification);
    }
}
