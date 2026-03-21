<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillItem extends Model
{
    protected $fillable = [
        'bill_id',
        'account_id',
        'tax_rate_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'subtotal',
        'tax_amount',
        'total',
        'sort_order',
    ];

    protected $casts = [
        'quantity'         => 'decimal:3',
        'unit_price'       => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'subtotal'         => 'decimal:2',
        'tax_amount'       => 'decimal:2',
        'total'            => 'decimal:2',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function calculate(): void
    {
        $gross              = $this->quantity * $this->unit_price;
        $discount           = $gross * ($this->discount_percent / 100);
        $this->subtotal     = $gross - $discount;
        $this->tax_amount   = $this->taxRate
            ? round($this->subtotal * ($this->taxRate->rate / 100), 2)
            : 0;
        $this->total        = $this->subtotal + $this->tax_amount;
    }
}
