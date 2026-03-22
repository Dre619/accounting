<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RecurringInvoice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessRecurringInvoices extends Command
{
    protected $signature   = 'invoices:process-recurring';
    protected $description = 'Generate invoices from active recurring invoice schedules that are due today';

    public function handle(): int
    {
        $today = Carbon::today();

        $due = RecurringInvoice::with(['company', 'items.taxRate'])
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', $today);
            })
            ->get();

        if ($due->isEmpty()) {
            $this->info('No recurring invoices due today.');
            return self::SUCCESS;
        }

        $this->info("Processing {$due->count()} recurring invoice(s)...");
        $created = 0;
        $failed  = 0;

        foreach ($due as $recurring) {
            try {
                DB::transaction(function () use ($recurring, $today) {
                    $company = $recurring->company;

                    $invoice = Invoice::create([
                        'company_id'    => $recurring->company_id,
                        'contact_id'    => $recurring->contact_id,
                        'invoice_number'=> $company->nextInvoiceNumber(),
                        'status'        => 'sent',
                        'issue_date'    => $today,
                        'due_date'      => $today->copy()->addDays($recurring->days_due),
                        'reference'     => $recurring->reference,
                        'notes'         => $recurring->notes,
                        'discount_amount' => $recurring->discount_amount,
                        'subtotal'      => 0,
                        'tax_amount'    => 0,
                        'total'         => 0,
                        'amount_paid'   => 0,
                        'amount_due'    => 0,
                        'created_by'    => $recurring->created_by,
                    ]);

                    foreach ($recurring->items as $templateItem) {
                        $item = new InvoiceItem([
                            'invoice_id'       => $invoice->id,
                            'account_id'       => $templateItem->account_id,
                            'tax_rate_id'      => $templateItem->tax_rate_id,
                            'description'      => $templateItem->description,
                            'quantity'         => $templateItem->quantity,
                            'unit_price'       => $templateItem->unit_price,
                            'discount_percent' => $templateItem->discount_percent,
                            'sort_order'       => $templateItem->sort_order,
                        ]);
                        $item->calculate();
                        $item->save();
                    }

                    $invoice->load('items.taxRate');
                    $invoice->recalculate();

                    // Advance the schedule
                    $recurring->last_run_at = $today;
                    $recurring->next_run_at = $this->nextRunDate($recurring, $today);
                    $recurring->save();
                });

                $created++;
                $this->line("  ✓ Created invoice for recurring #{$recurring->id} ({$recurring->company->name})");
            } catch (\Throwable $e) {
                $failed++;
                Log::error("ProcessRecurringInvoices: failed for recurring #{$recurring->id}", [
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  ✗ Failed for recurring #{$recurring->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Created: {$created}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function nextRunDate(RecurringInvoice $recurring, Carbon $from): Carbon
    {
        $next = match ($recurring->frequency) {
            'weekly'    => $from->copy()->addWeek(),
            'quarterly' => $from->copy()->addMonths(3),
            'yearly'    => $from->copy()->addYear(),
            default     => $from->copy()->addMonth(), // monthly
        };

        // For monthly/quarterly/yearly, pin to day_of_month
        if ($recurring->frequency !== 'weekly') {
            $day  = min($recurring->day_of_month, $next->daysInMonth);
            $next->setDay($day);
        }

        return $next;
    }
}
