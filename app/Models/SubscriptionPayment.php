<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'company_id', 'subscription_id', 'plan_id',
        'amount', 'currency', 'billing_cycle',
        'method', 'status', 'reference',
        'proof_path', 'notes',
        'paid_at', 'verified_at', 'verified_by',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
