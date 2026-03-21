<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>VAT Summary — {{ $company['name'] }}</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:12px;color:#1a1a2e;background:#fff}
.print-bar{background:#0f2044;color:#fff;padding:10px 24px;display:flex;align-items:center;justify-content:space-between;print-color-adjust:exact;-webkit-print-color-adjust:exact}
.print-bar button{background:#f97316;color:#fff;border:none;padding:7px 18px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600}
.doc{max-width:820px;margin:0 auto;padding:32px 24px}
h1{font-size:22px;color:#0f2044;margin-bottom:4px}
.meta{color:#666;font-size:11px;margin-bottom:24px}
.section{margin-bottom:24px}
.section-title{background:#0f2044;color:#fff;padding:6px 12px;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.5px;print-color-adjust:exact;-webkit-print-color-adjust:exact}
table{width:100%;border-collapse:collapse}
td,th{padding:5px 10px;border-bottom:1px solid #e5e7eb}
th{text-align:left;font-size:10px;text-transform:uppercase;color:#6b7280;background:#f9fafb}
.amount{text-align:right;font-variant-numeric:tabular-nums}
.total-row td{font-weight:700;border-top:2px solid #d1d5db;background:#f9fafb}
.summary-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px;margin-top:16px}
.summary-row{display:flex;justify-content:space-between;padding:4px 0;font-size:13px}
.net-payable{font-weight:800;font-size:16px;color:#dc2626;border-top:2px solid #d1d5db;padding-top:8px;margin-top:8px}
.net-refund{font-weight:800;font-size:16px;color:#16a34a;border-top:2px solid #d1d5db;padding-top:8px;margin-top:8px}
@media print{.print-bar{display:none!important}.doc{padding:16px}}
</style>
</head>
<body>
<div class="print-bar">
    <span style="font-weight:700;font-size:15px">{{ $company['name'] }} — VAT Summary</span>
    <button onclick="window.print()">Print / Save PDF</button>
</div>
<div class="doc">
    <h1>VAT Summary Report</h1>
    <p class="meta">{{ $company['name'] }} &nbsp;·&nbsp; Period: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>

    <div class="section">
        <div class="section-title">Output VAT — Sales / Invoices</div>
        <table>
            <thead><tr><th>Invoice #</th><th>Date</th><th class="amount">Subtotal</th><th class="amount">VAT</th><th class="amount">Total</th></tr></thead>
            <tbody>
            @foreach($invoices as $inv)
                <tr>
                    <td class="font-mono">{{ $inv->invoice_number }}</td>
                    <td>{{ \Carbon\Carbon::parse($inv->issue_date)->format('d M Y') }}</td>
                    <td class="amount">{{ number_format($inv->subtotal, 2) }}</td>
                    <td class="amount">{{ number_format($inv->tax_amount, 2) }}</td>
                    <td class="amount">{{ number_format($inv->total, 2) }}</td>
                </tr>
            @endforeach
            @if($invoices->isEmpty())
                <tr><td colspan="5" style="color:#9ca3af;text-align:center;padding:16px">No invoices for this period</td></tr>
            @endif
            </tbody>
            <tfoot><tr class="total-row"><td colspan="3"></td><td class="amount"><strong>Output VAT</strong></td><td class="amount"><strong>ZMW {{ number_format($outputVat, 2) }}</strong></td></tr></tfoot>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Input VAT — Purchases / Bills</div>
        <table>
            <thead><tr><th>Bill #</th><th>Date</th><th class="amount">Subtotal</th><th class="amount">VAT</th><th class="amount">Total</th></tr></thead>
            <tbody>
            @foreach($bills as $bill)
                <tr>
                    <td class="font-mono">{{ $bill->bill_number ?? '—' }}</td>
                    <td>{{ \Carbon\Carbon::parse($bill->issue_date)->format('d M Y') }}</td>
                    <td class="amount">{{ number_format($bill->subtotal, 2) }}</td>
                    <td class="amount">{{ number_format($bill->tax_amount, 2) }}</td>
                    <td class="amount">{{ number_format($bill->total, 2) }}</td>
                </tr>
            @endforeach
            @if($bills->isEmpty())
                <tr><td colspan="5" style="color:#9ca3af;text-align:center;padding:16px">No bills for this period</td></tr>
            @endif
            </tbody>
            <tfoot><tr class="total-row"><td colspan="3"></td><td class="amount"><strong>Input VAT</strong></td><td class="amount"><strong>ZMW {{ number_format($inputVat, 2) }}</strong></td></tr></tfoot>
        </table>
    </div>

    <div class="summary-box">
        <div class="summary-row"><span>Output VAT (Sales)</span><span>ZMW {{ number_format($outputVat, 2) }}</span></div>
        <div class="summary-row"><span>Input VAT (Purchases)</span><span>− ZMW {{ number_format($inputVat, 2) }}</span></div>
        <div class="{{ $vatPayable >= 0 ? 'net-payable' : 'net-refund' }} summary-row">
            <span>{{ $vatPayable >= 0 ? 'Net VAT Payable to ZRA' : 'VAT Refund Due' }}</span>
            <span>ZMW {{ number_format(abs($vatPayable), 2) }}</span>
        </div>
    </div>

    <p style="margin-top:32px;color:#9ca3af;font-size:10px;text-align:right">Generated {{ now()->format('d M Y H:i') }} · {{ config('app.name') }}</p>
</div>
</body>
</html>
