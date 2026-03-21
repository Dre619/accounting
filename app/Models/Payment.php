<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'contact_id',
        'type',
        'payment_number',
        'payment_date',
        'amount',
        'withholding_tax_amount',
        'method',
        'reference',
        'deposit_account_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date'           => 'date',
        'amount'                 => 'decimal:2',
        'withholding_tax_amount' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function depositAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deposit_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'sourceable');
    }

    public function scopeReceipts($query)
    {
        return $query->where('type', 'receipt');
    }

    public function scopePayments($query)
    {
        return $query->where('type', 'payment');
    }

    public function getUnallocatedAmountAttribute(): float
    {
        return $this->amount - $this->allocations->sum('amount');
    }
}
