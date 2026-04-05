<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GenerationCompleteNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Project $project) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.frontend_url', config('app.url')) . '/projects/' . $this->project->id;

        return (new MailMessage)
            ->subject('Your course "' . $this->project->title . '" is ready')
            ->view('emails.generation-complete', [
                'project' => $this->project,
                'url'     => $url,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'generation_complete',
            'title'      => 'Course ready',
            'body'       => '"' . $this->project->title . '" has been generated and is ready to review.',
            'project_id' => $this->project->id,
        ];
    }
}
