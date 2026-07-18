<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Balance Sheet — {{ $company['name'] }}</title>
<style>
@page { margin: 0; }
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DejaVu Sans',sans-serif;font-size:12px;color:#1a1a2e}
.doc{padding:36px 44px 48px}
h1{font-size:21px;color:#0f2044;margin-bottom:4px}
.meta{color:#666;font-size:11px;margin-bottom:22px}
.section{margin-bottom:18px}
.section-title{background:#0f2044;color:#fff;padding:6px 12px;font-weight:bold;font-size:11px;text-transform:uppercase;letter-spacing:.5px}
.sub-title{background:#f3f4f6;padding:4px 12px;font-weight:bold;font-size:11px;color:#374151;margin-top:4px}
table{width:100%;border-collapse:collapse}
td,th{padding:5px 10px;border-bottom:1px solid #e5e7eb}
th{text-align:left;font-size:10px;text-transform:uppercase;color:#6b7280;background:#f9fafb}
.amount{text-align:right}
.total-row td{font-weight:bold;border-top:2px solid #d1d5db;background:#f9fafb}
.grand-total td{font-weight:bold;font-size:13px;border-top:3px solid #0f2044;padding:10px;color:#0f2044}
</style>
</head>
<body>
<div class="doc">
    @if(!empty($logoSrc))
        <img src="{{ $logoSrc }}" alt="{{ $company['name'] }}" style="max-height:52px;margin-bottom:12px">
    @endif
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
                <td>Total Liabilities &amp; Equity</td>
                <td class="amount">ZMW {{ number_format($totalLiabilities + $totalEquity, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top:32px;color:#9ca3af;font-size:10px;text-align:right">Generated {{ now()->format('d M Y H:i') }} · {{ config('app.name') }}</p>
</div>
</body>
</html>
