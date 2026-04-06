<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteProgress extends Model
{
    protected $table = 'invite_progress';

    protected $fillable = [
        'invite_id',
        'viewer_name',
        'current_screen_index',
        'total_screens',
        'completed_at',
        'certificate_path',
        'last_active_at',
        'question_answers',
        'visited_screens',
    ];

    protected $casts = [
        'completed_at'     => 'datetime',
        'last_active_at'   => 'datetime',
        'question_answers' => 'array',
        'visited_screens'  => 'array',
    ];

    public function invite(): BelongsTo
    {
        return $this->belongsTo(ProjectInvite::class, 'invite_id');
    }
}
