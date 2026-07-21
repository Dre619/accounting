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
        'effective_from',
        'effective_to',
        'is_compound',
        'is_active',
    ];

    protected $casts = [
        'rate'           => 'decimal:2',
        'effective_from' => 'date',
        'effective_to'   => 'date',
        'is_compound'    => 'boolean',
        'is_active'      => 'boolean',
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

    public function scopeTurnover($query)
    {
        return $query->where('type', 'turnover');
    }

    /**
     * Rates whose effective window overlaps the given period at all.
     * A null effective_from means "since forever"; null effective_to means
     * "still current".
     */
    public function scopeOverlapping($query, string $from, string $to)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $to))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from));
    }

    /** Human-readable effective window, e.g. "from 01 Jan 2026". */
    public function periodLabel(): string
    {
        return match (true) {
            $this->effective_from === null && $this->effective_to === null => 'Always',
            $this->effective_from === null => 'Until ' . $this->effective_to->format('d M Y'),
            $this->effective_to === null   => 'From ' . $this->effective_from->format('d M Y'),
            default => $this->effective_from->format('d M Y') . ' – ' . $this->effective_to->format('d M Y'),
        };
    }
}
