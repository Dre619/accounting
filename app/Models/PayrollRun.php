<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'company_id',
        'period',
        'period_start',
        'period_end',
        'status',
        'total_gross',
        'total_paye',
        'total_napsa_employee',
        'total_napsa_employer',
        'total_nhima_employee',
        'total_nhima_employer',
        'total_net',
        'notes',
        'processed_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_start'          => 'date',
        'period_end'            => 'date',
        'total_gross'           => 'decimal:2',
        'total_paye'            => 'decimal:2',
        'total_napsa_employee'  => 'decimal:2',
        'total_napsa_employer'  => 'decimal:2',
        'total_nhima_employee'  => 'decimal:2',
        'total_nhima_employer'  => 'decimal:2',
        'total_net'             => 'decimal:2',
        'approved_at'           => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'sourceable');
    }
}
