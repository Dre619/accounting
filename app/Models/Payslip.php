<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'basic_salary',
        'gross_salary',
        'paye',
        'napsa_employee',
        'napsa_employer',
        'nhima_employee',
        'nhima_employer',
        'other_deductions',
        'total_deductions',
        'net_salary',
    ];

    protected $casts = [
        'basic_salary'     => 'decimal:2',
        'gross_salary'     => 'decimal:2',
        'paye'             => 'decimal:2',
        'napsa_employee'   => 'decimal:2',
        'napsa_employer'   => 'decimal:2',
        'nhima_employee'   => 'decimal:2',
        'nhima_employer'   => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary'       => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
