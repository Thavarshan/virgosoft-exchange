<?php

namespace App\Mail;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class OrderOpenedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create the message from the committed order reservation snapshot.
     *
     * @param  numeric-string  $price
     * @param  numeric-string  $amount
     * @param  numeric-string  $reservedAmount
     */
    public function __construct(
        public readonly string $accountName,
        public readonly string $accountEmail,
        public readonly bool $isDemo,
        public readonly AssetSymbol $symbol,
        public readonly OrderSide $side,
        public readonly string $price,
        public readonly string $amount,
        public readonly string $reservedAmount,
    ) {}

    /**
     * Set a subject that identifies the order direction and market.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ucfirst($this->side->value).' '.$this->symbol->value.' order is open',
        );
    }

    /**
     * Render the order and exact reserved amount in the opened-order template.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.trading.order-opened',
            with: [
                'accountName' => $this->accountName,
                'accountEmail' => $this->accountEmail,
                'isDemo' => $this->isDemo,
                'symbolValue' => $this->symbol->value,
                'sideValue' => $this->side->value,
                'price' => $this->price,
                'amount' => $this->amount,
                'reservedAmount' => $this->reservedAmount,
                'reservationCurrency' => $this->side === OrderSide::BUY
                    ? 'USD'
                    : $this->symbol->value,
            ],
        );
    }
}
