<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringInvoiceItem extends Model
{
    protected $fillable = [
        'recurring_invoice_id', 'description', 'account_id', 'tax_rate_id',
        'quantity', 'unit_price', 'discount_percent', 'sort_order',
    ];

    protected $casts = [
        'quantity'         => 'decimal:4',
        'unit_price'       => 'decimal:2',
        'discount_percent' => 'decimal:2',
    ];

    public function recurringInvoice(): BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
