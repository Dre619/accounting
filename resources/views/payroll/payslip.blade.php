<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payslip — {{ $payslip->employee->full_name }} — {{ $payroll->period }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; }

        .print-bar {
            position: fixed; top: 0; left: 0; right: 0;
            background: #0f2044; color: #fff;
            padding: 10px 24px; display: flex;
            align-items: center; justify-content: space-between; z-index: 100;
        }
        .print-bar span { font-size: 13px; opacity: 0.8; }
        .btn-print {
            background: #f97316; color: #fff; border: none;
            padding: 8px 20px; border-radius: 6px; font-size: 13px;
            font-weight: 600; cursor: pointer;
        }

        .page { max-width: 680px; margin: 64px auto 0; padding: 32px 40px 48px; }

        .accent { height: 4px; background: linear-gradient(90deg,#f97316,#fb923c); border-radius: 2px; margin-bottom: 20px; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #0f2044; }
        .company-name { font-size: 18px; font-weight: 700; color: #0f2044; }
        .company-sub  { font-size: 11px; color: #666; margin-top: 2px; }
        .slip-title   { text-align: right; }
        .slip-title h2 { font-size: 22px; font-weight: 800; color: #0f2044; }
        .slip-title p  { font-size: 12px; color: #555; margin-top: 2px; }

        .employee-block { display: flex; justify-content: space-between; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px 18px; margin-bottom: 24px; }
        .employee-block .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #888; margin-bottom: 4px; }
        .employee-block .value { font-size: 13px; font-weight: 600; }
        .employee-block .sub   { font-size: 11px; color: #666; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12.5px; }
        thead th { background: #0f2044; color: #fff; padding: 8px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
        thead th:first-child { text-align: left; border-radius: 4px 0 0 4px; }
        thead th:last-child  { border-radius: 0 4px 4px 0; }
        thead th.r { text-align: right; }
        tbody tr { border-bottom: 1px solid #e5e7eb; }
        tbody tr:last-child { border-bottom: none; }
        tbody td { padding: 9px 12px; }
        tbody td.r { text-align: right; }
        tbody td.sub { color: #6b7280; font-size: 11px; }
        .total-row td { font-weight: 700; font-size: 14px; color: #0f2044; border-top: 2px solid #0f2044; padding-top: 10px; }

        .net-box { background: #0f2044; color: #fff; border-radius: 8px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .net-box .label { font-size: 12px; opacity: 0.8; }
        .net-box .amount { font-size: 22px; font-weight: 800; }

        .footer { display: flex; justify-content: space-between; font-size: 11px; color: #aaa; padding-top: 12px; border-top: 1px solid #e5e7eb; }

        @media print {
            .print-bar { display: none !important; }
            .page { margin-top: 0; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="print-bar">
    <span>Payslip — {{ $payslip->employee->full_name }} — {{ $payroll->period }}</span>
    <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="page">
    <div class="accent"></div>

    <div class="header">
        <div>
            <div class="company-name">{{ $company->name }}</div>
            <div class="company-sub">
                @if ($company->address){{ $company->address }}@if ($company->city), {{ $company->city }}@endif<br>@endif
                @if ($company->tpin)TPIN: {{ $company->tpin }}@endif
            </div>
        </div>
        <div class="slip-title">
            <h2>PAYSLIP</h2>
            <p>Period: <strong>{{ $payroll->period }}</strong></p>
            <p>{{ \Carbon\Carbon::parse($payroll->period_start)->format('d M Y') }} — {{ \Carbon\Carbon::parse($payroll->period_end)->format('d M Y') }}</p>
        </div>
    </div>

    <!-- Employee info -->
    <div class="employee-block">
        <div>
            <div class="label">Employee</div>
            <div class="value">{{ $payslip->employee->full_name }}</div>
            <div class="sub">{{ $payslip->employee->employee_number }}@if($payslip->employee->job_title) · {{ $payslip->employee->job_title }}@endif</div>
        </div>
        <div>
            <div class="label">TPIN</div>
            <div class="value">{{ $payslip->employee->tpin ?? '—' }}</div>
            <div class="sub">NAPSA: {{ $payslip->employee->napsa_number ?? '—' }}</div>
        </div>
        <div>
            <div class="label">Bank</div>
            <div class="value">{{ $payslip->employee->bank_name ?? '—' }}</div>
            <div class="sub">{{ $payslip->employee->bank_account ?? '' }}</div>
        </div>
    </div>

    <!-- Earnings -->
    <table>
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
            <tr>
                <td class="sub" colspan="2">Gross Salary</td>
            </tr>
            <tr class="total-row">
                <td>Total Gross</td>
                <td class="r">{{ number_format($payslip->gross_salary, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Deductions -->
    <table>
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
    <div class="net-box">
        <div>
            <div class="label">NET PAY</div>
            <div style="font-size:11px;opacity:.7;margin-top:2px;">{{ $payroll->period }}</div>
        </div>
        <div class="amount">ZMW {{ number_format($payslip->net_salary, 2) }}</div>
    </div>

    <div class="footer">
        <span>{{ $company->name }} · {{ $company->city ?? 'Zambia' }}</span>
        <span>Generated {{ now()->format('d M Y') }}</span>
    </div>
</div>
</body>
</html>
