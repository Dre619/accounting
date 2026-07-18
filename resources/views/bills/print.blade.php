<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Bill {{ $bill->bill_number ?? 'BILL-'.$bill->id }} — {{ $company->name }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1a1a1a; }
        .page { padding: 40px 44px 56px; }

        table.layout { width: 100%; border-collapse: collapse; }
        table.layout td { vertical-align: top; }

        .accent-bar { height: 4px; background: #f97316; margin-bottom: 16px; }

        .header { margin-bottom: 32px; padding-bottom: 20px; border-bottom: 3px solid #0f2044; }
        .company-name { font-size: 19px; font-weight: bold; color: #0f2044; margin-bottom: 4px; }
        .company-detail { font-size: 11px; color: #555; line-height: 1.6; }
        .header .right { text-align: right; }
        .doc-title { font-size: 34px; font-weight: bold; color: #0f2044; }
        .doc-meta { font-size: 11px; color: #555; line-height: 1.7; margin-top: 4px; }
        .doc-meta strong { color: #1a1a1a; }

        .from-block { margin-bottom: 28px; }
        .section-label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 6px; }
        .contact-name { font-size: 13px; font-weight: bold; }
        .contact-detail { font-size: 11px; color: #555; line-height: 1.6; }

        table.items { width: 100%; border-collapse: collapse; margin-bottom: 22px; }
        table.items thead th { background: #0f2044; color: #fff; padding: 9px 11px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table.items thead th.r { text-align: right; }
        table.items tbody td { padding: 9px 11px; font-size: 11.5px; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        table.items tbody td.r { text-align: right; }
        .item-account { font-size: 10px; color: #888; margin-top: 2px; }

        .totals-table { width: 260px; border-collapse: collapse; }
        .totals-table td { padding: 5px 0; font-size: 11.5px; }
        .totals-table td.tr { text-align: right; }
        .totals-table tr.total td { border-top: 2px solid #0f2044; padding-top: 8px; font-size: 14px; font-weight: bold; color: #0f2044; }
        .totals-table tr.paid td { color: #16a34a; }
        .totals-table tr.due td { color: #d97706; font-weight: bold; }
        .totals-table tr.discount td { color: #dc2626; }

        .notes { margin-top: 22px; padding-top: 18px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #555; }
        .notes strong { color: #1a1a1a; display: block; margin-bottom: 4px; }

        .doc-footer { margin-top: 36px; padding-top: 14px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #aaa; }
        .doc-footer .right { text-align: right; }

        .status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-approved { background: #dbeafe; color: #1e40af; }
        .status-paid     { background: #dcfce7; color: #166534; }
        .status-partial  { background: #fef3c7; color: #92400e; }
        .status-overdue  { background: #fee2e2; color: #991b1b; }
        .status-draft    { background: #f3f4f6; color: #6b7280; }
        .status-void     { background: #f3f4f6; color: #6b7280; }
    </style>
</head>
<body>
<div class="page">
    <div class="accent-bar"></div>

    <table class="layout header">
        <tr>
            <td>
                @if (!empty($logoSrc))
                    <img src="{{ $logoSrc }}" alt="{{ $company->name }}" style="max-height:60px; margin-bottom:8px;" />
                @endif
                <div class="company-name">{{ $company->name }}</div>
                <div class="company-detail">
                    @if ($company->address){{ $company->address }}@if ($company->city), {{ $company->city }}@endif<br>@endif
                    @if ($company->tpin)TPIN: {{ $company->tpin }}<br>@endif
                </div>
            </td>
            <td class="right">
                <div class="doc-title">BILL</div>
                <div class="doc-meta">
                    @if ($bill->bill_number)<strong>{{ $bill->bill_number }}</strong><br>@endif
                    <span class="status status-{{ $bill->status }}">{{ strtoupper($bill->status) }}</span><br><br>
                    Date: <strong>{{ \Carbon\Carbon::parse($bill->issue_date)->format('d F Y') }}</strong><br>
                    Due: <strong>{{ \Carbon\Carbon::parse($bill->due_date)->format('d F Y') }}</strong>
                    @if ($bill->reference)<br>Ref: <strong>{{ $bill->reference }}</strong>@endif
                </div>
            </td>
        </tr>
    </table>

    <div class="from-block">
        <div class="section-label">From (Supplier)</div>
        <div class="contact-name">{{ $bill->contact->name }}</div>
        <div class="contact-detail">
            @if ($bill->contact->tpin)TPIN: {{ $bill->contact->tpin }}<br>@endif
            @if ($bill->contact->email){{ $bill->contact->email }}@endif
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Description</th>
                <th class="r" style="width:48px">Qty</th>
                <th class="r" style="width:92px">Unit Price</th>
                <th class="r" style="width:54px">Disc%</th>
                <th class="r" style="width:84px">VAT</th>
                <th class="r" style="width:104px">Total</th>
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

    <table class="layout" style="margin-bottom:28px;">
        <tr>
            <td></td>
            <td style="width:260px">
                <table class="totals-table">
                    <tr><td>Subtotal</td><td class="tr">ZMW {{ number_format($bill->subtotal, 2) }}</td></tr>
                    <tr><td>VAT (16%)</td><td class="tr">ZMW {{ number_format($bill->tax_amount, 2) }}</td></tr>
                    @if ($bill->discount_amount > 0)
                    <tr class="discount"><td>Discount</td><td class="tr">− ZMW {{ number_format($bill->discount_amount, 2) }}</td></tr>
                    @endif
                    <tr class="total"><td>Total</td><td class="tr">ZMW {{ number_format($bill->total, 2) }}</td></tr>
                    @if ($bill->amount_paid > 0)
                    <tr class="paid"><td>Amount Paid</td><td class="tr">ZMW {{ number_format($bill->amount_paid, 2) }}</td></tr>
                    @endif
                    @if ($bill->amount_due > 0)
                    <tr class="due"><td>Balance Due</td><td class="tr">ZMW {{ number_format($bill->amount_due, 2) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if ($bill->notes)
    <div class="notes"><strong>Notes</strong>{{ $bill->notes }}</div>
    @endif

    <table class="layout doc-footer">
        <tr>
            <td>{{ $company->name }} · {{ $company->city ?? 'Zambia' }}</td>
            <td class="right">Generated {{ now()->format('d M Y') }}</td>
        </tr>
    </table>
</div>
</body>
</html>
