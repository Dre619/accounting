<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\SalesOrder;
use Illuminate\Support\Facades\DB;

class OpportunityService
{
    public function __construct(private readonly SalesOrderService $salesOrders) {}

    public function markWon(Opportunity $opportunity): Opportunity
    {
        $opportunity->update(['stage' => 'won', 'won_at' => now(), 'lost_at' => null, 'lost_reason' => null]);

        return $opportunity;
    }

    public function markLost(Opportunity $opportunity, ?string $reason = null): Opportunity
    {
        $opportunity->update(['stage' => 'lost', 'lost_at' => now(), 'lost_reason' => $reason, 'won_at' => null]);

        return $opportunity;
    }

    /**
     * The CRM→ERP bridge: turn an opportunity into a draft sales order (quote).
     * Creates a single line from the opportunity's title and estimated value —
     * the user then refines the quote's lines. Moves an early-stage opportunity
     * to 'proposal' and links the quote back.
     */
    public function convertToQuote(Opportunity $opportunity): SalesOrder
    {
        abort_if($opportunity->sales_order_id !== null, 422, 'This opportunity already has a quote.');
        abort_if($opportunity->stage === 'lost', 422, 'A lost opportunity cannot be quoted.');

        return DB::transaction(function () use ($opportunity) {
            $incomeAccountId = $opportunity->company->accounts()
                ->where('type', 'income')->value('id');

            $order = $this->salesOrders->store($opportunity->company, [
                'contact_id' => $opportunity->contact_id,
                'order_date' => now()->toDateString(),
                'reference'  => $opportunity->title,
                'items'      => [[
                    'description' => $opportunity->title,
                    'account_id'  => $incomeAccountId,
                    'quantity'    => 1,
                    'unit_price'  => $opportunity->estimated_value,
                ]],
            ]);

            $opportunity->sales_order_id = $order->id;
            if (in_array($opportunity->stage, ['new', 'qualified'], true)) {
                $opportunity->stage = 'proposal';
            }
            $opportunity->save();

            return $order;
        });
    }
}
