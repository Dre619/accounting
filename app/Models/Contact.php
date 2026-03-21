<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'tpin',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'default_receivable_account_id',
        'default_payable_account_id',
        'default_tax_rate_id',
        'withholding_tax_applicable',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'withholding_tax_applicable' => 'boolean',
        'is_active'                  => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_receivable_account_id');
    }

    public function defaultPayableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_payable_account_id');
    }

    public function defaultTaxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'default_tax_rate_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeCustomers($query)
    {
        return $query->whereIn('type', ['customer', 'both']);
    }

    public function scopeSuppliers($query)
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
