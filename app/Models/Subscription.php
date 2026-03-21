<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'company_id', 'plan_id', 'status',
        'billing_cycle', 'starts_at', 'ends_at', 'cancelled_at',
    ];

    protected $casts = [
        'starts_at'    => 'date',
        'ends_at'      => 'date',
        'cancelled_at' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'])
            && $this->ends_at->isFuture();
    }

    public function daysRemaining(): int
    {
        return max(0, now()->diffInDays($this->ends_at, false));
    }
}
