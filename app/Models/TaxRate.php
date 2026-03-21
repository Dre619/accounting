<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRate extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'type',
        'rate',
        'is_compound',
        'is_active',
    ];

    protected $casts = [
        'rate'        => 'decimal:2',
        'is_compound' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVat($query)
    {
        return $query->where('type', 'vat');
    }

    public function scopeWithholding($query)
    {
        return $query->where('type', 'withholding');
    }
}
