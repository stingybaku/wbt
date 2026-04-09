<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'user_id', 'plan_id', 'status',
        'paypal_order_id', 'paypal_subscription_id',
        'modules_used', 'modules_rolled_over', 'bonus_modules',
        'current_period_start', 'current_period_end',
        'cancelled_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
        'cancelled_at'         => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function modulesAvailable(): int
    {
        $limit = $this->plan->module_limit + ($this->bonus_modules ?? 0) + $this->modules_rolled_over;
        return max(0, $limit - $this->modules_used);
    }

    public function seatsUsed(): int
    {
        return ProjectInvite::whereHas('project', fn ($q) => $q->where('user_id', $this->user_id))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count();
    }

    public function seatsAvailable(): int
    {
        return max(0, $this->plan->seat_limit - $this->seatsUsed());
    }
}
