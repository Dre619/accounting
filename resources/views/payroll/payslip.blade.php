<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Payslip — {{ $payslip->employee->full_name }} — {{ $payroll->period }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1a1a1a; }
        .page { padding: 36px 44px 48px; }

        table.layout { width: 100%; border-collapse: collapse; }
        table.layout td { vertical-align: top; }

        .accent { height: 4px; background: #f97316; margin-bottom: 20px; }

        .header { margin-bottom: 22px; padding-bottom: 14px; border-bottom: 2px solid #0f2044; }
        .company-name { font-size: 17px; font-weight: bold; color: #0f2044; }
        .company-sub  { font-size: 10px; color: #666; margin-top: 2px; line-height: 1.5; }
        .header .right { text-align: right; }
        .slip-title h2 { font-size: 21px; font-weight: bold; color: #0f2044; }
        .slip-title p  { font-size: 11px; color: #555; margin-top: 2px; }

        .employee-block { background: #f8fafc; border: 1px solid #e5e7eb; padding: 12px 16px; margin-bottom: 22px; }
        .employee-block td { width: 33%; vertical-align: top; }
        .employee-block .label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.8px; color: #888; margin-bottom: 4px; }
        .employee-block .value { font-size: 12px; font-weight: bold; }
        .employee-block .sub   { font-size: 10px; color: #666; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 11.5px; }
        table.data thead th { background: #0f2044; color: #fff; padding: 8px 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; text-align: left; }
        table.data thead th.r { text-align: right; }
        table.data tbody td { padding: 8px 12px; border-bottom: 1px solid #e5e7eb; }
        table.data tbody td.r { text-align: right; }
        table.data tbody td.sub { color: #6b7280; font-size: 10px; }
        table.data tr.total-row td { font-weight: bold; font-size: 13px; color: #0f2044; border-top: 2px solid #0f2044; border-bottom: none; padding-top: 10px; }

        .net-box { background: #0f2044; color: #fff; padding: 14px 20px; margin-bottom: 22px; }
        .net-box td { vertical-align: middle; }
        .net-box .label { font-size: 11px; color: #cbd5e1; }
        .net-box .period { font-size: 10px; color: #94a3b8; margin-top: 2px; }
        .net-box .amount { font-size: 21px; font-weight: bold; text-align: right; }

        .footer { font-size: 10px; color: #aaa; padding-top: 12px; border-top: 1px solid #e5e7eb; }
        .footer .right { text-align: right; }
    </style>
</head>
<body>
<div class="page">
    <div class="accent"></div>

    <table class="layout header">
        <tr>
            <td>
                <div class="company-name">{{ $company->name }}</div>
                <div class="company-sub">
                    @if ($company->address){{ $company->address }}@if ($company->city), {{ $company->city }}@endif<br>@endif
                    @if ($company->tpin)TPIN: {{ $company->tpin }}@endif
                </div>
            </td>
            <td class="right slip-title">
                <h2>PAYSLIP</h2>
                <p>Period: <strong>{{ $payroll->period }}</strong></p>
                <p>{{ \Carbon\Carbon::parse($payroll->period_start)->format('d M Y') }} — {{ \Carbon\Carbon::parse($payroll->period_end)->format('d M Y') }}</p>
            </td>
        </tr>
    </table>

    <!-- Employee info -->
    <table class="layout employee-block">
        <tr>
            <td>
                <div class="label">Employee</div>
                <div class="value">{{ $payslip->employee->full_name }}</div>
                <div class="sub">{{ $payslip->employee->employee_number }}@if($payslip->employee->job_title) · {{ $payslip->employee->job_title }}@endif</div>
            </td>
            <td>
                <div class="label">TPIN</div>
                <div class="value">{{ $payslip->employee->tpin ?? '—' }}</div>
                <div class="sub">NAPSA: {{ $payslip->employee->napsa_number ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Bank</div>
                <div class="value">{{ $payslip->employee->bank_name ?? '—' }}</div>
                <div class="sub">{{ $payslip->employee->bank_account ?? '' }}</div>
            </td>
        </tr>
    </table>

    <!-- Earnings -->
    <table class="data">
        <thead>
            <tr>
                <th>Earnings</th>
                <th class="r">Amount (ZMW)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Basic Salary</td>
                <td class="r">{{ number_format($payslip->basic_salary, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Total Gross</td>
                <td class="r">{{ number_format($payslip->gross_salary, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Deductions -->
    <table class="data">
        <thead>
            <tr>
                <th>Deductions</th>
                <th class="r">Employee (ZMW)</th>
                <th class="r">Employer (ZMW)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>PAYE</td>
                <td class="r">{{ number_format($payslip->paye, 2) }}</td>
                <td class="r">—</td>
            </tr>
            <tr>
                <td>NAPSA (5%)</td>
                <td class="r">{{ number_format($payslip->napsa_employee, 2) }}</td>
                <td class="r">{{ number_format($payslip->napsa_employer, 2) }}</td>
            </tr>
            <tr>
                <td>NHIMA (1%)</td>
                <td class="r">{{ number_format($payslip->nhima_employee, 2) }}</td>
                <td class="r">{{ number_format($payslip->nhima_employer, 2) }}</td>
            </tr>
            @if ($payslip->other_deductions > 0)
            <tr>
                <td>Other Deductions</td>
                <td class="r">{{ number_format($payslip->other_deductions, 2) }}</td>
                <td class="r">—</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total Deductions</td>
                <td class="r">{{ number_format($payslip->total_deductions, 2) }}</td>
                <td class="r">{{ number_format($payslip->napsa_employer + $payslip->nhima_employer, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Net pay box -->
    <table class="layout net-box">
        <tr>
            <td>
                <div class="label">NET PAY</div>
                <div class="period">{{ $payroll->period }}</div>
            </td>
            <td class="amount">ZMW {{ number_format($payslip->net_salary, 2) }}</td>
        </tr>
    </table>

    <table class="layout footer">
        <tr>
            <td>{{ $company->name }} · {{ $company->city ?? 'Zambia' }}</td>
            <td class="right">Generated {{ now()->format('d M Y') }}</td>
        </tr>
    </table>
</div>
</body>
</html>
