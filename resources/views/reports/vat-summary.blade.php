<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>VAT Summary — {{ $company['name'] }}</title>
<style>
@page { margin: 0; }
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DejaVu Sans',sans-serif;font-size:12px;color:#1a1a2e}
.doc{padding:36px 44px 48px}
h1{font-size:21px;color:#0f2044;margin-bottom:4px}
.meta{color:#666;font-size:11px;margin-bottom:22px}
.section{margin-bottom:22px}
.section-title{background:#0f2044;color:#fff;padding:6px 12px;font-weight:bold;font-size:11px;text-transform:uppercase;letter-spacing:.5px}
table{width:100%;border-collapse:collapse}
td,th{padding:5px 10px;border-bottom:1px solid #e5e7eb}
th{text-align:left;font-size:10px;text-transform:uppercase;color:#6b7280;background:#f9fafb}
.amount{text-align:right}
.total-row td{font-weight:bold;border-top:2px solid #d1d5db;background:#f9fafb}
.summary-box{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:14px 16px;margin-top:16px}
.summary-table{width:100%;border-collapse:collapse}
.summary-table td{border:none;padding:4px 0;font-size:13px}
.summary-table td.amount{text-align:right}
.summary-table tr.net td{font-weight:bold;font-size:16px;border-top:2px solid #d1d5db;padding-top:8px}
.net-payable td{color:#dc2626}
.net-refund td{color:#16a34a}
</style>
</head>
<body>
<div class="doc">
    @if(!empty($logoSrc))
        <img src="{{ $logoSrc }}" alt="{{ $company['name'] }}" style="max-height:52px;margin-bottom:12px">
    @endif
    <h1>VAT Summary Report</h1>
    <p class="meta">{{ $company['name'] }} &nbsp;·&nbsp; Period: {{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>

    <div class="section">
        <div class="section-title">Output VAT — Sales / Invoices</div>
        <table>
            <thead><tr><th>Invoice #</th><th>Date</th><th class="amount">Subtotal</th><th class="amount">VAT</th><th class="amount">Total</th></tr></thead>
            <tbody>
            @foreach($invoices as $inv)
                <tr>
                    <td>{{ $inv->invoice_number }}</td>
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
                    <td>{{ $bill->bill_number ?? '—' }}</td>
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
        <table class="summary-table">
            <tr><td>Output VAT (Sales)</td><td class="amount">ZMW {{ number_format($outputVat, 2) }}</td></tr>
            <tr><td>Input VAT (Purchases)</td><td class="amount">− ZMW {{ number_format($inputVat, 2) }}</td></tr>
            <tr class="net {{ $vatPayable >= 0 ? 'net-payable' : 'net-refund' }}">
                <td>{{ $vatPayable >= 0 ? 'Net VAT Payable to ZRA' : 'VAT Refund Due' }}</td>
                <td class="amount">ZMW {{ number_format(abs($vatPayable), 2) }}</td>
            </tr>
        </table>
    </div>

    <p style="margin-top:32px;color:#9ca3af;font-size:10px;text-align:right">Generated {{ now()->format('d M Y H:i') }} · {{ config('app.name') }}</p>
</div>
</body>
</html>
