<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourcedImage extends Model
{
    protected $fillable = [
        'source', 'url', 'photographer', 'license',
        'topic', 'keywords', 'external_id',
    ];

    protected $casts = [
        'keywords' => 'array',
    ];
}
