<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'account_id',
        'tax_rate_id',
        'description',
        'quantity',
        'unit_price',
        'discount_percent',
        'subtotal',
        'tax_amount',
        'total',
        'quantity_invoiced',
        'item_type',
        'cls_code_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity'          => 'decimal:3',
        'unit_price'        => 'decimal:2',
        'discount_percent'  => 'decimal:2',
        'subtotal'          => 'decimal:2',
        'tax_amount'        => 'decimal:2',
        'total'             => 'decimal:2',
        'quantity_invoiced' => 'decimal:3',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    /** Quantity still to be invoiced on this line. */
    public function quantityOutstanding(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_invoiced);
    }
}
