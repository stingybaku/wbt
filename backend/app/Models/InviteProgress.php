<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteProgress extends Model
{
    protected $table = 'invite_progress';

    protected $fillable = [
        'invite_id',
        'current_screen_index',
        'total_screens',
        'completed_at',
        'last_active_at',
    ];

    protected $casts = [
        'completed_at'  => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function invite(): BelongsTo
    {
        return $this->belongsTo(ProjectInvite::class, 'invite_id');
    }
}
