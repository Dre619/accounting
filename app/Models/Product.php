<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'sku',
        'name',
        'description',
        'type',
        'unit_of_measure',
        'sales_price',
        'sales_account_id',
        'purchase_account_id',
        'inventory_account_id',
        'cogs_account_id',
        'tax_rate_id',
        'item_type',
        'cls_code_id',
        'quantity_on_hand',
        'average_cost',
        'reorder_point',
        'is_active',
    ];

    protected $casts = [
        'sales_price'      => 'decimal:2',
        'quantity_on_hand' => 'decimal:3',
        'average_cost'     => 'decimal:4',
        'reorder_point'    => 'decimal:3',
        'is_active'        => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'sales_account_id');
    }

    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'purchase_account_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'inventory_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cogs_account_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function goodsCode(): BelongsTo
    {
        return $this->belongsTo(GoodsCode::class, 'cls_code_id');
    }

    public function serviceCode(): BelongsTo
    {
        return $this->belongsTo(ServiceCode::class, 'cls_code_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Only inventory-type products carry stock; services/non-inventory do not. */
    public function tracksStock(): bool
    {
        return $this->type === 'inventory';
    }

    /** True when a reorder point is set and on-hand has reached or fallen below it. */
    public function isLowStock(): bool
    {
        return $this->reorder_point !== null
            && $this->quantity_on_hand <= $this->reorder_point;
    }
}
