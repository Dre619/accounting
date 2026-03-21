<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'company_id',
        'employee_number',
        'first_name',
        'last_name',
        'job_title',
        'department',
        'employment_type',
        'basic_salary',
        'hire_date',
        'termination_date',
        'tpin',
        'napsa_number',
        'nhima_number',
        'email',
        'phone',
        'bank_name',
        'bank_account',
        'bank_branch',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'basic_salary'      => 'decimal:2',
        'hire_date'         => 'date',
        'termination_date'  => 'date',
        'is_active'         => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
