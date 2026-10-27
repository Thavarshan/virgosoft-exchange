<?php

namespace App\Mail;

use App\Enums\AssetSymbol;
use App\Enums\OrderSide;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class OrderMatchedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create the message from the participant's committed fill snapshot.
     *
     * @param  numeric-string  $orderPrice
     * @param  numeric-string  $amount
     * @param  numeric-string  $executionPrice
     * @param  numeric-string  $tradeValue
     * @param  numeric-string  $commission
     */
    public function __construct(
        public readonly string $accountName,
        public readonly string $accountEmail,
        public readonly bool $isDemo,
        public readonly AssetSymbol $symbol,
        public readonly OrderSide $side,
        public readonly string $orderPrice,
        public readonly string $amount,
        public readonly string $executionPrice,
        public readonly string $tradeValue,
        public readonly string $commission,
    ) {}

    /**
     * Set a subject that identifies the completed market order.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->symbol->value.' order filled',
        );
    }

    /**
     * Render the actual execution terms and buyer-paid commission.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.trading.order-matched',
            with: [
                'accountName' => $this->accountName,
                'accountEmail' => $this->accountEmail,
                'isDemo' => $this->isDemo,
                'symbolValue' => $this->symbol->value,
                'sideValue' => $this->side->value,
                'orderPrice' => $this->orderPrice,
                'amount' => $this->amount,
                'executionPrice' => $this->executionPrice,
                'tradeValue' => $this->tradeValue,
                'commission' => $this->commission,
            ],
        );
    }
}
