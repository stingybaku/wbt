<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'plan_name',
        'plan_type',
        'amount_cents',
        'currency',
        'payment_reference',
        'status',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function amountFormatted(): string
    {
        if ($this->amount_cents === 0) {
            return 'Free';
        }
        return '$' . number_format($this->amount_cents / 100, 2);
    }
}
