<?php

namespace App\Mail;

use App\Models\InviteProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public InviteProgress $progress) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Certificate of Completion — ' . $this->progress->invite->project->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.certificate');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->progress->certificate_path)
                ->as('certificate.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
