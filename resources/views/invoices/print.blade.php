<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Invoice {{ $invoice->invoice_number }} — {{ $company->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #fff;
            padding: 0;
        }

        .page {
            max-width: 820px;
            margin: 0 auto;
            padding: 48px 48px 64px;
        }

        /* Print button — hidden when printing */
        .print-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: #0f2044;
            color: #fff;
            padding: 10px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 100;
        }
        .print-bar span { font-size: 13px; opacity: 0.8; }
        .btn-print {
            background: #f97316;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-print:hover { background: #ea6c0a; }

        .page { margin-top: 52px; }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 3px solid #0f2044;
        }
        .company-name { font-size: 20px; font-weight: 700; color: #0f2044; margin-bottom: 4px; }
        .company-detail { font-size: 12px; color: #555; line-height: 1.6; }
        .invoice-title { font-size: 36px; font-weight: 800; color: #0f2044; text-align: right; letter-spacing: -1px; }
        .invoice-meta { text-align: right; font-size: 12px; color: #555; line-height: 1.7; margin-top: 4px; }
        .invoice-meta strong { color: #1a1a1a; }

        /* Bill-to */
        .bill-to { margin-bottom: 32px; }
        .section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #888;
            margin-bottom: 6px;
        }
        .bill-to-name { font-size: 14px; font-weight: 600; }
        .bill-to-detail { font-size: 12px; color: #555; line-height: 1.6; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead th {
            background: #0f2044;
            color: #fff;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        thead th:first-child { text-align: left; border-radius: 4px 0 0 4px; }
        thead th:last-child  { border-radius: 0 4px 4px 0; }
        thead th.r { text-align: right; }

        tbody tr { border-bottom: 1px solid #e5e7eb; }
        tbody tr:last-child { border-bottom: none; }
        tbody td { padding: 10px 12px; font-size: 12.5px; vertical-align: top; }
        tbody td.r { text-align: right; }
        .item-account { font-size: 11px; color: #888; margin-top: 2px; }

        /* Totals */
        .totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 32px; }
        .totals { width: 260px; }
        .totals-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 12.5px; }
        .totals-row.total {
            border-top: 2px solid #0f2044;
            margin-top: 4px;
            padding-top: 8px;
            font-size: 15px;
            font-weight: 700;
            color: #0f2044;
        }
        .totals-row.paid  { color: #16a34a; }
        .totals-row.due   { color: #d97706; font-weight: 600; }

        /* Orange accent bar */
        .accent-bar {
            height: 4px;
            background: linear-gradient(90deg, #f97316, #fb923c);
            border-radius: 2px;
            margin-bottom: 16px;
        }

        /* ZRA receipt block */
        .zra-block {
            margin-top: 24px;
            padding: 12px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f9fafb;
            font-size: 11px;
            color: #374151;
        }
        .zra-block .zra-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .zra-block table { width: auto; margin: 0; }
        .zra-block td { padding: 2px 8px 2px 0; font-size: 11px; }
        .zra-block td.key { color: #6b7280; white-space: nowrap; }
        .zra-block .sig { margin-top: 6px; word-break: break-all; color: #374151; }

        /* Notes */
        .notes { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #555; }
        .notes strong { color: #1a1a1a; display: block; margin-bottom: 4px; }

        /* Footer */
        .doc-footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #aaa;
        }

        /* Status badge */
        .status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-paid     { background: #dcfce7; color: #166534; }
        .status-sent     { background: #dbeafe; color: #1e40af; }
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
    <span>{{ $invoice->invoice_number }} — {{ $company->name }}</span>
    <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="page">

    <div class="accent-bar"></div>

    <!-- Header -->
    <div class="header">
        <div>
            @if ($company->logo_path)
                <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" style="max-height:60px; margin-bottom:8px;" />
            @endif
            <div class="company-name">{{ $company->name }}</div>
            <div class="company-detail">
                @if ($company->address){{ $company->address }}@if ($company->city), {{ $company->city }}@endif<br>@endif
                @if ($company->tpin)TPIN: {{ $company->tpin }}<br>@endif
                @if ($company->vat_number)VAT: {{ $company->vat_number }}<br>@endif
                @if ($company->email){{ $company->email }}<br>@endif
                @if ($company->phone){{ $company->phone }}@endif
            </div>
        </div>
        <div>
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-meta">
                <strong>{{ $invoice->invoice_number }}</strong><br>
                @php
                    $statusClass = 'status-' . $invoice->status;
                @endphp
                <span class="status {{ $statusClass }}">{{ strtoupper($invoice->status) }}</span><br>
                <br>
                Issued: <strong>{{ \Carbon\Carbon::parse($invoice->issue_date)->format('d F Y') }}</strong><br>
                Due: <strong>{{ \Carbon\Carbon::parse($invoice->due_date)->format('d F Y') }}</strong>
                @if ($invoice->reference)<br>Ref: <strong>{{ $invoice->reference }}</strong>@endif
            </div>
        </div>
    </div>

    <!-- Bill To -->
    <div class="bill-to">
        <div class="section-label">Bill To</div>
        <div class="bill-to-name">{{ $invoice->contact->name }}</div>
        <div class="bill-to-detail">
            @if ($invoice->contact->address){{ $invoice->contact->address }}<br>@endif
            @if ($invoice->contact->tpin)TPIN: {{ $invoice->contact->tpin }}<br>@endif
            @if ($invoice->contact->email){{ $invoice->contact->email }}@endif
        </div>
    </div>

    <!-- Line items -->
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
            @foreach ($invoice->items as $item)
            <tr>
                <td>
                    {{ $item->description }}
                    @if ($item->account)
                        <div class="item-account">{{ $item->account->code }} — {{ $item->account->name }}</div>
                    @endif
                </td>
                <td class="r">{{ $item->quantity }}</td>
                <td class="r">{{ number_format($item->unit_price, 2) }}</td>
                <td class="r">{{ $item->discount_percent > 0 ? $item->discount_percent . '%' : '—' }}</td>
                <td class="r">{{ $item->taxRate ? $item->taxRate->name : '—' }}</td>
                <td class="r"><strong>ZMW {{ number_format($item->total, 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-wrap">
        <div class="totals">
            <div class="totals-row">
                <span>Subtotal</span>
                <span>ZMW {{ number_format($invoice->subtotal, 2) }}</span>
            </div>
            <div class="totals-row">
                <span>VAT (16%)</span>
                <span>ZMW {{ number_format($invoice->tax_amount, 2) }}</span>
            </div>
            @if ($invoice->discount_amount > 0)
            <div class="totals-row" style="color:#dc2626;">
                <span>Discount</span>
                <span>− ZMW {{ number_format($invoice->discount_amount, 2) }}</span>
            </div>
            @endif
            <div class="totals-row total">
                <span>Total</span>
                <span>ZMW {{ number_format($invoice->total, 2) }}</span>
            </div>
            @if ($invoice->amount_paid > 0)
            <div class="totals-row paid">
                <span>Amount Paid</span>
                <span>ZMW {{ number_format($invoice->amount_paid, 2) }}</span>
            </div>
            @endif
            @if ($invoice->amount_due > 0)
            <div class="totals-row due">
                <span>Balance Due</span>
                <span>ZMW {{ number_format($invoice->amount_due, 2) }}</span>
            </div>
            @endif
        </div>
    </div>

    <!-- ZRA Smart Invoice receipt -->
    @if ($invoice->zra_submitted_at)
    <div class="zra-block">
        <div class="zra-label">ZRA Smart Invoice</div>
        <table>
            <tr>
                <td class="key">Receipt No.</td>
                <td><strong>{{ $invoice->zra_rcpt_no }}</strong></td>
            </tr>
            <tr>
                <td class="key">SDC ID</td>
                <td>{{ $invoice->zra_sdc_id }}</td>
            </tr>
            <tr>
                <td class="key">MRC No.</td>
                <td>{{ $invoice->zra_mrc_no }}</td>
            </tr>
            <tr>
                <td class="key">Date Submitted</td>
                <td>{{ \Carbon\Carbon::parse($invoice->zra_submitted_at)->format('d M Y H:i') }}</td>
            </tr>
        </table>
        <div class="sig"><span style="color:#6b7280;">Signature: </span>{{ $invoice->zra_rcpt_sign }}</div>
    </div>
    @endif

    <!-- Notes -->
    @if ($invoice->notes || $invoice->footer)
    <div class="notes">
        @if ($invoice->notes)<strong>Notes</strong>{{ $invoice->notes }}<br>@endif
        @if ($invoice->footer)<br>{{ $invoice->footer }}@endif
    </div>
    @endif

    <!-- Footer -->
    <div class="doc-footer">
        <span>{{ $company->name }} · {{ $company->city ?? 'Zambia' }}</span>
        <span>Generated {{ now()->format('d M Y') }}</span>
    </div>

</div>
</body>
</html>
