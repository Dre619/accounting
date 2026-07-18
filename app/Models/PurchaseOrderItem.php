<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
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
        'quantity_received',
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
        'quantity_received' => 'decimal:3',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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

    /** Quantity still outstanding on this line. */
    public function quantityOutstanding(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_received);
    }
}
