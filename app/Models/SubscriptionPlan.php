<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
        'price_monthly', 'price_annual', 'currency',
        'max_users', 'features', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_annual'  => 'decimal:2',
        'features'      => 'array',
        'is_active'     => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
