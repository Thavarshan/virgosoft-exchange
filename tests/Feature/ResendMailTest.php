<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    /**
     * Render the existing reset notification without contacting Resend.
     */
    public function test_password_reset_email_uses_the_configured_resend_mailer(): void
    {
        config([
            'mail.default' => 'resend',
            'mail.from.address' => 'hello@example.test',
            'mail.from.name' => 'Virgosoft Exchange',
            'services.resend.key' => 're_test_key',
        ]);
        $user = User::factory()->make(['email' => 'trader@example.test']);
        $message = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$message): bool {
            $message = $event->message;

            // Stop at Laravel's delivery boundary so no real email is sent.
            return false;
        });

        $user->sendPasswordResetNotification('test-reset-token');

        $this->assertInstanceOf(ResendTransport::class, Mail::mailer()->getSymfonyTransport());
        $this->assertInstanceOf(Email::class, $message);
        $this->assertSame('hello@example.test', $message->getFrom()[0]->getAddress());
        $this->assertSame('Virgosoft Exchange', $message->getFrom()[0]->getName());
        $this->assertSame('trader@example.test', $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('/reset-password/test-reset-token', (string) $message->getHtmlBody());
    }
}
