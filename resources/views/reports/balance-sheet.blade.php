<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Balance Sheet — {{ $company['name'] }}</title>
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
.sub-title{background:#f3f4f6;padding:4px 12px;font-weight:600;font-size:11px;color:#374151;margin-top:4px}
table{width:100%;border-collapse:collapse}
td,th{padding:5px 10px;border-bottom:1px solid #e5e7eb}
th{text-align:left;font-size:10px;text-transform:uppercase;color:#6b7280;background:#f9fafb}
.amount{text-align:right;font-variant-numeric:tabular-nums}
.total-row td{font-weight:700;border-top:2px solid #d1d5db;background:#f9fafb}
.grand-total td{font-weight:800;font-size:13px;border-top:3px solid #0f2044;padding:10px;color:#0f2044}
@media print{.print-bar{display:none!important}.doc{padding:16px}}
</style>
</head>
<body>
<div class="print-bar">
    <span style="font-weight:700;font-size:15px">{{ $company['name'] }} — Balance Sheet</span>
    <button onclick="window.print()">Print / Save PDF</button>
</div>
<div class="doc">
    <h1>Balance Sheet</h1>
    <p class="meta">{{ $company['name'] }} &nbsp;·&nbsp; As of {{ \Carbon\Carbon::parse($asOf)->format('d M Y') }}</p>

    <div class="section">
        <div class="section-title">Assets</div>
        @foreach(['current' => 'Current Assets', 'fixed' => 'Fixed Assets', 'other' => 'Other Assets'] as $key => $label)
        @if(!empty($assets[$key]))
        <div class="sub-title">{{ $label }}</div>
        <table>
            <tbody>
            @foreach($assets[$key] as $row)
                <tr><td>{{ $row['code'] }} — {{ $row['name'] }}</td><td class="amount">{{ number_format($row['balance'], 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @endif
        @endforeach
        <table><tfoot><tr class="total-row"><td><strong>Total Assets</strong></td><td class="amount"><strong>ZMW {{ number_format($totalAssets, 2) }}</strong></td></tr></tfoot></table>
    </div>

    <div class="section">
        <div class="section-title">Liabilities</div>
        @foreach(['current' => 'Current Liabilities', 'long_term' => 'Long-term Liabilities'] as $key => $label)
        @if(!empty($liabilities[$key]))
        <div class="sub-title">{{ $label }}</div>
        <table>
            <tbody>
            @foreach($liabilities[$key] as $row)
                <tr><td>{{ $row['code'] }} — {{ $row['name'] }}</td><td class="amount">{{ number_format($row['balance'], 2) }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @endif
        @endforeach
        <table><tfoot><tr class="total-row"><td><strong>Total Liabilities</strong></td><td class="amount"><strong>ZMW {{ number_format($totalLiabilities, 2) }}</strong></td></tr></tfoot></table>
    </div>

    <div class="section">
        <div class="section-title">Equity</div>
        <table>
            <tbody>
            @foreach($equity as $row)
                <tr><td>{{ $row['code'] }} — {{ $row['name'] }}</td><td class="amount">{{ number_format($row['balance'], 2) }}</td></tr>
            @endforeach
                <tr><td style="color:#6b7280">Retained Earnings</td><td class="amount" style="color:#6b7280">{{ number_format($retainedEarnings, 2) }}</td></tr>
            </tbody>
        </table>
        <table><tfoot><tr class="total-row"><td><strong>Total Equity</strong></td><td class="amount"><strong>ZMW {{ number_format($totalEquity, 2) }}</strong></td></tr></tfoot></table>
    </div>

    <table>
        <tfoot>
            <tr class="grand-total">
                <td>Total Liabilities & Equity</td>
                <td class="amount">ZMW {{ number_format($totalLiabilities + $totalEquity, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top:32px;color:#9ca3af;font-size:10px;text-align:right">Generated {{ now()->format('d M Y H:i') }} · {{ config('app.name') }}</p>
</div>
</body>
</html>
