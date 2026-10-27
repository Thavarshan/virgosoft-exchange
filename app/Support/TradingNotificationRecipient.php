<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

final class TradingNotificationRecipient
{
    /**
     * Resolve a user's destination without changing authentication mail routing.
     */
    public function for(User $user): ?string
    {
        if (! $user->is_demo) {
            return $user->email;
        }

        $email = config('services.trading_notifications.demo_email');

        if (is_string($email) && filled($email)) {
            return $email;
        }

        Log::warning('Demo trading email skipped because no monitoring address is configured.', [
            'user_id' => $user->id,
        ]);

        return null;
    }
}
