<?php

namespace App\Notifications;

use App\Models\InviteProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ViewerCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly InviteProgress $progress) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project    = $this->progress->invite->project;
        $viewerName = $this->progress->viewer_name ?? $this->progress->invite->email;
        $url        = config('app.frontend_url', config('app.url')) . '/projects/' . $project->id . '?tab=report';

        return (new MailMessage)
            ->subject($viewerName . ' completed "' . $project->title . '"')
            ->view('emails.viewer-completed', [
                'viewerName' => $viewerName,
                'project'    => $project,
                'url'        => $url,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        $project    = $this->progress->invite->project;
        $viewerName = $this->progress->viewer_name ?? $this->progress->invite->email;

        return [
            'type'       => 'viewer_completed',
            'title'      => 'Learner completed a course',
            'body'       => $viewerName . ' completed "' . $project->title . '".',
            'project_id' => $project->id,
        ];
    }
}
