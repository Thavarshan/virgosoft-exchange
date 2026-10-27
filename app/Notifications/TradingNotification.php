<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use LogicException;

#[Tries(3)]
#[Backoff(10, 30, 60)]
#[Timeout(45)]
abstract class TradingNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * Route transactional trading email through the shared low-priority queue.
     */
    public function __construct()
    {
        $this->onQueue('emails');
        $this->afterCommit();
    }

    /**
     * Limit trading activity notifications to email.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the explicit email recipient for a mail-only notification.
     */
    protected function recipient(object $notifiable): string
    {
        if (! $notifiable instanceof AnonymousNotifiable) {
            throw new LogicException('Trading notifications require an explicit mail route.');
        }

        $email = $notifiable->routeNotificationFor('mail');

        if (! is_string($email)) {
            throw new LogicException('Trading notifications require a valid mail recipient.');
        }

        return $email;
    }

    /**
     * Build the mail representation with its resolved recipient.
     */
    abstract public function toMail(object $notifiable): Mailable;
}
