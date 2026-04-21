<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourcedImage extends Model
{
    protected $fillable = [
        'source', 'url', 'stored_path', 'photographer', 'license',
        'topic', 'keywords', 'external_id',
    ];

    public function storedUrl(): ?string
    {
        if (!$this->stored_path) return null;
        return \Illuminate\Support\Facades\Storage::disk('r2')->url($this->stored_path);
    }

    public function resolvedUrl(): string
    {
        return $this->storedUrl() ?? $this->url;
    }

    protected $casts = [
        'keywords' => 'array',
    ];
}
