<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringInvoice extends Model
{
    protected $fillable = [
        'company_id', 'contact_id', 'created_by',
        'frequency', 'day_of_month', 'days_due',
        'reference', 'notes', 'discount_amount',
        'is_active', 'next_run_at', 'last_run_at',
    ];

    protected $casts = [
        'day_of_month'    => 'integer',
        'days_due'        => 'integer',
        'discount_amount' => 'decimal:2',
        'is_active'       => 'boolean',
        'next_run_at'     => 'date',
        'last_run_at'     => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecurringInvoiceItem::class)->orderBy('sort_order');
    }
}
