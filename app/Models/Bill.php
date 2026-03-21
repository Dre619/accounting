<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'contact_id',
        'bill_number',
        'reference',
        'status',
        'issue_date',
        'due_date',
        'notes',
        'subtotal',
        'tax_amount',
        'withholding_tax_amount',
        'discount_amount',
        'total',
        'amount_paid',
        'amount_due',
        'payable_account_id',
        'created_by',
        'approved_at',
        'voided_at',
    ];

    protected $casts = [
        'issue_date'             => 'date',
        'due_date'               => 'date',
        'subtotal'               => 'decimal:2',
        'tax_amount'             => 'decimal:2',
        'withholding_tax_amount' => 'decimal:2',
        'discount_amount'        => 'decimal:2',
        'total'                  => 'decimal:2',
        'amount_paid'            => 'decimal:2',
        'amount_due'             => 'decimal:2',
        'approved_at'            => 'datetime',
        'voided_at'              => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class)->orderBy('sort_order');
    }

    public function paymentAllocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'sourceable');
    }

    public function recalculate(): void
    {
        $this->subtotal   = $this->items->sum('subtotal');
        $this->tax_amount = $this->items->sum('tax_amount');
        $this->total      = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->amount_due = $this->total - $this->amount_paid - $this->withholding_tax_amount;
        $this->save();
    }
}
