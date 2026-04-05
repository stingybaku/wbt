<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GenerationFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Project $project) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'generation_failed',
            'title'      => 'Generation failed',
            'body'       => 'Course generation for "' . $this->project->title . '" failed. You can retry from the project page.',
            'project_id' => $this->project->id,
        ];
    }
}
