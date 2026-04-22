<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'rights_attestation',
        'user_id',
        'source_type',
        'source_url',
        'source_text',
        'source_character_count',
        'source_status',
        'source_error',
        'source_temp_path',
        'gen_duration_minutes',
        'gen_learning_objectives',
        'gen_assessment_count',
    ];

    protected $casts = [
        'rights_attestation' => 'boolean'
    ];

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function module(): HasOne
    {
        return $this->hasOne(Module::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(ProjectInvite::class);
    }

    public function flags(): HasMany
    {
        return $this->hasMany(ModuleFlag::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
