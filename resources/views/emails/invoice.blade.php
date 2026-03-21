<x-mail::message>
# Invoice {{ $invoice->invoice_number }}

Dear {{ $invoice->contact->name }},

@if($message)
{{ $message }}

@endif
Please find attached invoice **{{ $invoice->invoice_number }}** from **{{ $company->name }}** for **ZMW {{ number_format($invoice->total, 2) }}**.

**Due Date:** {{ \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') }}

<x-mail::table>
| Description | Qty | Unit Price | Total |
|:------------|----:|----------:|------:|
@foreach($invoice->items as $item)
| {{ $item->description }} | {{ $item->quantity }} | ZMW {{ number_format($item->unit_price, 2) }} | ZMW {{ number_format($item->total, 2) }} |
@endforeach
</x-mail::table>

**Total: ZMW {{ number_format($invoice->total, 2) }}**

@if(Number($invoice->amount_due) > 0)
**Amount Due: ZMW {{ number_format($invoice->amount_due, 2) }}**
@endif

<x-mail::button :url="url('/invoices/' . $invoice->id . '/print')">
View Invoice
</x-mail::button>

@if($invoice->notes)
*{{ $invoice->notes }}*
@endif

Thanks,<br>
{{ $company->name }}
</x-mail::message>
