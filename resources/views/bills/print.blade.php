<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bill {{ $bill->bill_number ?? 'BILL-'.$bill->id }} — {{ $company->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; }
        .page { max-width: 820px; margin: 0 auto; padding: 48px 48px 64px; }

        .print-bar {
            position: fixed; top: 0; left: 0; right: 0;
            background: #0f2044; color: #fff;
            padding: 10px 24px;
            display: flex; align-items: center; justify-content: space-between;
            z-index: 100;
        }
        .print-bar span { font-size: 13px; opacity: 0.8; }
        .btn-print {
            background: #f97316; color: #fff; border: none;
            padding: 8px 20px; border-radius: 6px;
            font-size: 13px; font-weight: 600; cursor: pointer;
        }
        .btn-print:hover { background: #ea6c0a; }
        .page { margin-top: 52px; }

        .header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 40px; padding-bottom: 24px;
            border-bottom: 3px solid #0f2044;
        }
        .company-name { font-size: 20px; font-weight: 700; color: #0f2044; margin-bottom: 4px; }
        .company-detail { font-size: 12px; color: #555; line-height: 1.6; }
        .doc-title { font-size: 36px; font-weight: 800; color: #0f2044; text-align: right; letter-spacing: -1px; }
        .doc-meta { text-align: right; font-size: 12px; color: #555; line-height: 1.7; margin-top: 4px; }
        .doc-meta strong { color: #1a1a1a; }

        .from-block { margin-bottom: 32px; }
        .section-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; }
        .contact-name { font-size: 14px; font-weight: 600; }
        .contact-detail { font-size: 12px; color: #555; line-height: 1.6; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead th { background: #0f2044; color: #fff; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        thead th:first-child { text-align: left; border-radius: 4px 0 0 4px; }
        thead th:last-child { border-radius: 0 4px 4px 0; }
        thead th.r { text-align: right; }
        tbody tr { border-bottom: 1px solid #e5e7eb; }
        tbody tr:last-child { border-bottom: none; }
        tbody td { padding: 10px 12px; font-size: 12.5px; vertical-align: top; }
        tbody td.r { text-align: right; }
        .item-account { font-size: 11px; color: #888; margin-top: 2px; }

        .totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 32px; }
        .totals { width: 260px; }
        .totals-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 12.5px; }
        .totals-row.total { border-top: 2px solid #0f2044; margin-top: 4px; padding-top: 8px; font-size: 15px; font-weight: 700; color: #0f2044; }
        .totals-row.paid  { color: #16a34a; }
        .totals-row.due   { color: #d97706; font-weight: 600; }

        .accent-bar { height: 4px; background: linear-gradient(90deg, #f97316, #fb923c); border-radius: 2px; margin-bottom: 16px; }
        .notes { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #555; }
        .notes strong { color: #1a1a1a; display: block; margin-bottom: 4px; }
        .doc-footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; font-size: 11px; color: #aaa; }

        .status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-approved { background: #dbeafe; color: #1e40af; }
        .status-paid     { background: #dcfce7; color: #166534; }
        .status-partial  { background: #fef3c7; color: #92400e; }
        .status-overdue  { background: #fee2e2; color: #991b1b; }
        .status-draft    { background: #f3f4f6; color: #6b7280; }
        .status-void     { background: #f3f4f6; color: #6b7280; }

        @media print {
            .print-bar { display: none !important; }
            .page { margin-top: 0; padding: 32px 40px 48px; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="print-bar">
    <span>{{ $bill->bill_number ?? 'Bill' }} — {{ $company->name }}</span>
    <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="page">
    <div class="accent-bar"></div>

    <div class="header">
        <div>
            @if ($company->logo_path)
                <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" style="max-height:60px; margin-bottom:8px;" />
            @endif
            <div class="company-name">{{ $company->name }}</div>
            <div class="company-detail">
                @if ($company->address){{ $company->address }}@if ($company->city), {{ $company->city }}@endif<br>@endif
                @if ($company->tpin)TPIN: {{ $company->tpin }}<br>@endif
            </div>
        </div>
        <div>
            <div class="doc-title">BILL</div>
            <div class="doc-meta">
                @if ($bill->bill_number)<strong>{{ $bill->bill_number }}</strong><br>@endif
                @php $statusClass = 'status-' . $bill->status; @endphp
                <span class="status {{ $statusClass }}">{{ strtoupper($bill->status) }}</span><br><br>
                Date: <strong>{{ \Carbon\Carbon::parse($bill->issue_date)->format('d F Y') }}</strong><br>
                Due: <strong>{{ \Carbon\Carbon::parse($bill->due_date)->format('d F Y') }}</strong>
                @if ($bill->reference)<br>Ref: <strong>{{ $bill->reference }}</strong>@endif
            </div>
        </div>
    </div>

    <div class="from-block">
        <div class="section-label">From (Supplier)</div>
        <div class="contact-name">{{ $bill->contact->name }}</div>
        <div class="contact-detail">
            @if ($bill->contact->tpin)TPIN: {{ $bill->contact->tpin }}<br>@endif
            @if ($bill->contact->email){{ $bill->contact->email }}@endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="r" style="width:52px">Qty</th>
                <th class="r" style="width:100px">Unit Price</th>
                <th class="r" style="width:60px">Disc%</th>
                <th class="r" style="width:90px">VAT</th>
                <th class="r" style="width:110px">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($bill->items as $item)
            <tr>
                <td>
                    {{ $item->description }}
                    @if ($item->account)
                        <div class="item-account">{{ $item->account->code }} — {{ $item->account->name }}</div>
                    @endif
                </td>
                <td class="r">{{ $item->quantity }}</td>
                <td class="r">{{ number_format($item->unit_price, 2) }}</td>
                <td class="r">{{ $item->discount_percent > 0 ? $item->discount_percent.'%' : '—' }}</td>
                <td class="r">{{ $item->taxRate ? $item->taxRate->name : '—' }}</td>
                <td class="r"><strong>ZMW {{ number_format($item->total, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-wrap">
        <div class="totals">
            <div class="totals-row"><span>Subtotal</span><span>ZMW {{ number_format($bill->subtotal, 2) }}</span></div>
            <div class="totals-row"><span>VAT (16%)</span><span>ZMW {{ number_format($bill->tax_amount, 2) }}</span></div>
            @if ($bill->discount_amount > 0)
            <div class="totals-row" style="color:#dc2626;"><span>Discount</span><span>− ZMW {{ number_format($bill->discount_amount, 2) }}</span></div>
            @endif
            <div class="totals-row total"><span>Total</span><span>ZMW {{ number_format($bill->total, 2) }}</span></div>
            @if ($bill->amount_paid > 0)
            <div class="totals-row paid"><span>Amount Paid</span><span>ZMW {{ number_format($bill->amount_paid, 2) }}</span></div>
            @endif
            @if ($bill->amount_due > 0)
            <div class="totals-row due"><span>Balance Due</span><span>ZMW {{ number_format($bill->amount_due, 2) }}</span></div>
            @endif
        </div>
    </div>

    @if ($bill->notes)
    <div class="notes"><strong>Notes</strong>{{ $bill->notes }}</div>
    @endif

    <div class="doc-footer">
        <span>{{ $company->name }} · {{ $company->city ?? 'Zambia' }}</span>
        <span>Generated {{ now()->format('d M Y') }}</span>
    </div>
</div>
</body>
</html>
