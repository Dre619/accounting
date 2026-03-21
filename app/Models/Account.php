<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'account_category_id',
        'parent_id',
        'code',
        'name',
        'description',
        'type',
        'subtype',
        'is_bank_account',
        'bank_name',
        'bank_account_number',
        'is_system',
        'is_active',
        'opening_balance',
        'opening_balance_date',
    ];

    protected $casts = [
        'is_bank_account'      => 'boolean',
        'is_system'            => 'boolean',
        'is_active'            => 'boolean',
        'opening_balance'      => 'decimal:2',
        'opening_balance_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AccountCategory::class, 'account_category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBankAccounts($query)
    {
        return $query->where('is_bank_account', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Calculate the current balance from journal lines.
     * Assets & Expenses: debit-normal; Liabilities, Equity & Income: credit-normal.
     */
    public function getBalanceAttribute(): float
    {
        $debits  = $this->journalLines()->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))->sum('debit');
        $credits = $this->journalLines()->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))->sum('credit');

        return in_array($this->type, ['asset', 'expense'])
            ? ($this->opening_balance + $debits - $credits)
            : ($this->opening_balance + $credits - $debits);
    }
}
