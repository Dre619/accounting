<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendOverdueReminders extends Command
{
    protected $signature   = 'invoices:overdue-reminders';
    protected $description = 'Log overdue invoices (extend to send emails once mail is configured)';

    public function handle(): int
    {
        $overdue = Invoice::with(['company', 'contact'])
            ->whereIn('status', ['sent', 'partial'])
            ->where('due_date', '<', now())
            ->get();

        if ($overdue->isEmpty()) {
            $this->info('No overdue invoices.');
            return self::SUCCESS;
        }

        foreach ($overdue as $invoice) {
            $daysOverdue = now()->diffInDays($invoice->due_date);
            Log::info("OverdueInvoice: #{$invoice->invoice_number} — {$invoice->company->name} — {$daysOverdue} day(s) overdue — amount due: {$invoice->amount_due}");
            $this->line("  - {$invoice->invoice_number} | {$invoice->company->name} | {$daysOverdue}d overdue | {$invoice->company->currency} {$invoice->amount_due}");
        }

        $this->info("Found {$overdue->count()} overdue invoice(s).");

        return self::SUCCESS;
    }
}
