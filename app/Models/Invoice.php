<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'contact_id',
        'invoice_number',
        'status',
        'issue_date',
        'due_date',
        'reference',
        'notes',
        'footer',
        'subtotal',
        'tax_amount',
        'withholding_tax_amount',
        'discount_amount',
        'total',
        'amount_paid',
        'amount_due',
        'receivable_account_id',
        'created_by',
        'sent_at',
        'voided_at',
        'zra_submitted_at',
        'zra_rcpt_no',
        'zra_internal_data',
        'zra_rcpt_sign',
        'zra_sdc_id',
        'zra_mrc_no',
        'zra_invoice_path',
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
        'sent_at'                => 'datetime',
        'voided_at'              => 'datetime',
        'zra_submitted_at'       => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function paymentAllocations(): MorphMany
    {
        return $this->morphMany(PaymentAllocation::class, 'allocatable');
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'sourceable');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'sent')
            ->orWhere('status', 'partial')
            ->where('due_date', '<', now());
    }

    public function recalculate(): void
    {
        $this->subtotal    = $this->items->sum('subtotal');
        $this->tax_amount  = $this->items->sum('tax_amount');
        $this->total       = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->amount_due  = $this->total - $this->amount_paid - $this->withholding_tax_amount;
        $this->save();
    }
}
