<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Transaction $transaction)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your WBT Forge purchase is confirmed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.purchase_confirmation',
            with: [
                'userName'       => $this->transaction->user->name,
                'planName'       => $this->transaction->plan_name,
                'purchaseDate'   => $this->transaction->created_at->format('F j, Y'),
                'amountFormatted' => $this->transaction->amountFormatted(),
                'billingUrl'     => config('app.frontend_url') . '/billing',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
