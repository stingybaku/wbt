<?php

namespace App\Mail;

use App\Models\ProjectInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ViewerInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $viewerUrl;

    public function __construct(public ProjectInvite $invite)
    {
        $frontendBase = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $this->viewerUrl = "{$frontendBase}/view/{$invite->token}";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to view: {$this->invite->project->title}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.viewer-invite');
    }
}
