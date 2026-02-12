<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleVersion extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'module_id',
        'version',
        'module_json'
    ];

    protected $casts = [
        'module_json' => 'array'
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
