<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'contact_id',
        'order_number',
        'reference',
        'status',
        'order_date',
        'valid_until',
        'notes',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'created_by',
        'sent_at',
        'cancelled_at',
    ];

    protected $casts = [
        'order_date'      => 'date',
        'valid_until'     => 'date',
        'subtotal'        => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total'           => 'decimal:2',
        'sent_at'         => 'datetime',
        'cancelled_at'    => 'datetime',
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
        return $this->hasMany(SalesOrderItem::class)->orderBy('sort_order');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function opportunity(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Opportunity::class);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function recalculate(): void
    {
        $this->subtotal   = $this->items->sum('subtotal');
        $this->tax_amount = $this->items->sum('tax_amount');
        $this->total      = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->save();
    }

    /** True when every line has been fully invoiced. */
    public function isFullyInvoiced(): bool
    {
        return $this->items->every(fn ($item) => $item->quantity_invoiced >= $item->quantity);
    }
}
