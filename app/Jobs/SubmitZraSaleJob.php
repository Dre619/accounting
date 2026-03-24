<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\ZraVsdcService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitZraSaleJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 5;
    public array $backoff = [60, 120, 300, 600];

    public function __construct(public readonly Invoice $invoice) {}

    public function handle(ZraVsdcService $service): void
    {
        $this->invoice->load(['company', 'contact', 'items.taxRate', 'items.goodsCode', 'items.serviceCode']);
        $service->submitSale($this->invoice);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('ZRA sale submission failed', [
            'invoice_id' => $this->invoice->id,
            'error'      => $e->getMessage(),
        ]);
    }
}
