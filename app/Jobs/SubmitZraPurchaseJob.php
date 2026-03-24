<?php

namespace App\Jobs;

use App\Models\Bill;
use App\Services\ZraVsdcService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitZraPurchaseJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 5;
    public array $backoff = [60, 120, 300, 600];

    public function __construct(public readonly Bill $bill) {}

    public function handle(ZraVsdcService $service): void
    {
        $this->bill->load(['company', 'contact', 'items.taxRate', 'items.goodsCode', 'items.serviceCode']);
        $service->submitPurchase($this->bill);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('ZRA purchase submission failed', [
            'bill_id' => $this->bill->id,
            'error'   => $e->getMessage(),
        ]);
    }
}
