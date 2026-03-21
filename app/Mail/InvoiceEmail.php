<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Company $company,
        public readonly string  $message = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} from {$this->company->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.invoice',
        );
    }
}
