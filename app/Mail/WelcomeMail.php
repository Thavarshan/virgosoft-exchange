<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a welcome message showing the wallet assigned at registration.
     *
     * @param  array<string, numeric-string>  $assetBalances
     */
    public function __construct(
        public readonly string $accountName,
        public readonly string $usdBalance,
        public readonly array $assetBalances,
    ) {}

    /**
     * Give the new user a clear welcome subject.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.config('app.name'),
        );
    }

    /**
     * Render the welcome guide and registered account's starting funds.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.welcome',
            with: [
                'accountName' => $this->accountName,
                'usdBalance' => $this->usdBalance,
                'assetBalances' => $this->assetBalances,
            ],
        );
    }
}
