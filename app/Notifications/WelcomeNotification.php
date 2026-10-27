<?php

namespace App\Notifications;

use App\Mail\WelcomeMail;
use App\Models\User;
use App\Support\StartingWallet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use LogicException;

#[Tries(3)]
#[Backoff(10, 30, 60)]
#[Timeout(45)]
final class WelcomeNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Capture the account name for the queued welcome message.
     */
    public function __construct(public readonly string $accountName)
    {
        $this->onQueue('emails');
        $this->afterCommit();
    }

    /**
     * Keep account welcome messages on the email channel only.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        if (! $notifiable instanceof User) {
            throw new LogicException('Welcome email requires a user email address.');
        }

        return (new WelcomeMail(
            accountName: $this->accountName,
            usdBalance: StartingWallet::USD_BALANCE,
            assetBalances: StartingWallet::ASSET_BALANCES,
        ))->to($notifiable->email);
    }
}
