<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'type', 'price_cents', 'billing_period',
        'module_limit', 'seat_limit', 'rollover', 'paypal_plan_id', 'is_active',
    ];

    protected $casts = [
        'rollover'  => 'boolean',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function isOneTime(): bool
    {
        return $this->type === 'one_time';
    }

    public function isSubscription(): bool
    {
        return $this->type === 'subscription';
    }

    public function priceInDollars(): float
    {
        return $this->price_cents / 100;
    }
}
