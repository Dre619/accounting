<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentsController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'pending');

        $payments = SubscriptionPayment::with(['company', 'plan', 'verifiedBy'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/payments/Index', [
            'payments'       => $payments,
            'currentStatus'  => $status,
            'counts'         => [
                'pending'   => SubscriptionPayment::where('status', 'pending')->count(),
                'completed' => SubscriptionPayment::where('status', 'completed')->count(),
                'rejected'  => SubscriptionPayment::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function approve(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Only pending payments can be approved.');

        // Expire any current active subscription for this company
        Subscription::where('company_id', $payment->company_id)
            ->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'expired']);

        $months = $payment->months ?: ($payment->billing_cycle === 'annual' ? 12 : 1);

        $subscription = Subscription::create([
            'company_id'    => $payment->company_id,
            'plan_id'       => $payment->plan_id,
            'status'        => 'active',
            'billing_cycle' => $payment->billing_cycle,
            'starts_at'     => now()->toDateString(),
            'ends_at'       => now()->addMonths($months)->toDateString(),
        ]);

        $payment->update([
            'status'          => 'completed',
            'subscription_id' => $subscription->id,
            'verified_at'     => now(),
            'verified_by'     => $request->user()->id,
        ]);

        return back()->with('success', "Payment approved. {$payment->company->name} is now on the {$payment->plan->name} plan.");
    }

    public function reject(Request $request, SubscriptionPayment $payment): RedirectResponse
    {
        abort_unless($payment->status === 'pending', 422, 'Only pending payments can be rejected.');

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $payment->update([
            'status'      => 'rejected',
            'notes'       => $validated['notes'],
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Payment rejected and company notified.');
    }

    public function proof(SubscriptionPayment $payment): StreamedResponse
    {
        abort_unless($payment->proof_path && Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->download($payment->proof_path);
    }
}
