<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Profit & Loss — {{ $company['name'] }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#1a1a2e;background:#fff}
.print-bar{background:#0f2044;color:#fff;padding:10px 24px;display:flex;align-items:center;justify-content:space-between;print-color-adjust:exact;-webkit-print-color-adjust:exact}
.print-bar button{background:#f97316;color:#fff;border:none;padding:7px 18px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600}
.doc{max-width:820px;margin:0 auto;padding:32px 24px}
h1{font-size:22px;color:#0f2044;margin-bottom:4px}
.meta{color:#666;font-size:11px;margin-bottom:24px}
.section{margin-bottom:20px}
.section-title{background:#0f2044;color:#fff;padding:6px 12px;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.5px;print-color-adjust:exact;-webkit-print-color-adjust:exact}
table{width:100%;border-collapse:collapse}
td,th{padding:5px 10px;border-bottom:1px solid #e5e7eb}
th{text-align:left;font-size:10px;text-transform:uppercase;color:#6b7280;background:#f9fafb}
.amount{text-align:right;font-variant-numeric:tabular-nums}
.total-row td{font-weight:700;border-top:2px solid #0f2044;border-bottom:none}
.income .total-row td{color:#16a34a}
.expense .total-row td{color:#dc2626}
.net-row td{font-size:15px;font-weight:800;border-top:3px solid #0f2044;padding:10px}
.positive{color:#16a34a}.negative{color:#dc2626}
@media print{.print-bar{display:none!important}.doc{padding:16px}}
</style>
</head>
<body>
<div class="print-bar">
    <span style="font-weight:700;font-size:15px">{{ $company['name'] }} — Profit & Loss</span>
    <button onclick="window.print()">Print / Save PDF</button>
</div>
<div class="doc">
    <h1>Profit & Loss Statement</h1>
    <p class="meta">{{ $company['name'] }} &nbsp;·&nbsp; Period: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>

    <div class="section income">
        <div class="section-title">Income</div>
        <table>
            <thead><tr><th>Account</th><th class="amount">Amount (ZMW)</th></tr></thead>
            <tbody>
            @foreach($income as $row)
                <tr><td>{{ $row['code'] }} — {{ $row['name'] }}</td><td class="amount">{{ number_format($row['balance'], 2) }}</td></tr>
            @endforeach
            @if(empty($income))
                <tr><td colspan="2" style="color:#9ca3af;text-align:center;padding:16px">No income for this period</td></tr>
            @endif
            </tbody>
            <tfoot><tr class="total-row"><td>Total Income</td><td class="amount">{{ number_format($totalIncome, 2) }}</td></tr></tfoot>
        </table>
    </div>

    <div class="section expense">
        <div class="section-title">Expenses</div>
        <table>
            <thead><tr><th>Account</th><th class="amount">Amount (ZMW)</th></tr></thead>
            <tbody>
            @foreach($expenses as $row)
                <tr><td>{{ $row['code'] }} — {{ $row['name'] }}</td><td class="amount">{{ number_format($row['balance'], 2) }}</td></tr>
            @endforeach
            @if(empty($expenses))
                <tr><td colspan="2" style="color:#9ca3af;text-align:center;padding:16px">No expenses for this period</td></tr>
            @endif
            </tbody>
            <tfoot><tr class="total-row"><td>Total Expenses</td><td class="amount">{{ number_format($totalExpenses, 2) }}</td></tr></tfoot>
        </table>
    </div>

    <table>
        <tfoot>
            <tr class="net-row">
                <td>Net Profit / (Loss)</td>
                <td class="amount {{ $netProfit >= 0 ? 'positive' : 'negative' }}">
                    ZMW {{ number_format(abs($netProfit), 2) }}{{ $netProfit < 0 ? ' (Loss)' : '' }}
                </td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top:32px;color:#9ca3af;font-size:10px;text-align:right">Generated {{ now()->format('d M Y H:i') }} · {{ config('app.name') }}</p>
</div>
</body>
</html>
