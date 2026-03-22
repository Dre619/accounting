<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\LencoPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(private readonly LencoPaymentService $lenco) {}

    // ── Plans listing ────────────────────────────────────────────────────────

    public function plans(Request $request): Response
    {
        $company      = $request->user()->currentCompany;
        $plans        = SubscriptionPlan::active()->get();
        $subscription = $company->subscriptions()
            ->with('plan')
            ->latest()
            ->first();

        return Inertia::render('billing/Plans', [
            'plans'        => $plans,
            'subscription' => $subscription,
            'trialEndsAt'  => $company->trial_ends_at,
            'lencoPubKey'  => config('lenco.public_key'),
        ]);
    }

    // ── Checkout: show payment page for a chosen plan ────────────────────────

    public function checkout(Request $request, SubscriptionPlan $plan): Response
    {
        $cycle = $request->query('cycle', 'monthly');

        return Inertia::render('billing/Checkout', [
            'plan'        => $plan,
            'cycle'       => $cycle,
            'amount'      => $cycle === 'annual' ? $plan->price_annual : $plan->price_monthly,
            'lencoPubKey' => config('lenco.public_key'),
            'banking'     => [
                'bank_name'      => config('banking.bank_name'),
                'account_name'   => config('banking.account_name'),
                'account_number' => config('banking.account_number'),
                'branch'         => config('banking.branch'),
                'swift_code'     => config('banking.swift_code'),
                'sort_code'      => config('banking.sort_code'),
                'mobile_money'   => config('banking.mobile_money'),
                'instructions'   => config('banking.instructions'),
            ],
        ]);
    }

    // ── Online payment: verify Lenco reference & activate subscription ────────

    public function verifyOnline(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id'   => ['required', 'exists:subscription_plans,id'],
            'cycle'     => ['required', 'in:monthly,annual'],
            'months'    => ['required', 'integer', 'min:1', 'max:12'],
            'reference' => ['required', 'string', 'max:100'],
            'amount'    => ['required', 'numeric'],
        ]);

        $company = $request->user()->currentCompany;
        $plan    = SubscriptionPlan::findOrFail($validated['plan_id']);

        // Verify with Lenco
        $transaction = $this->lenco->verify($validated['reference']);

        if (! $transaction) {
            return back()->withErrors(['reference' => 'Payment could not be verified with Lenco. Please try again or contact support.']);
        }

        // Activate subscription
        $months       = $validated['cycle'] === 'annual' ? 12 : (int) $validated['months'];
        $subscription = $this->activateSubscription($company->id, $plan, $validated['cycle'], $months);

        SubscriptionPayment::create([
            'company_id'      => $company->id,
            'subscription_id' => $subscription->id,
            'plan_id'         => $plan->id,
            'amount'          => $validated['amount'],
            'billing_cycle'   => $validated['cycle'],
            'months'          => $months,
            'method'          => 'online',
            'status'          => 'completed',
            'reference'       => $validated['reference'],
            'paid_at'         => now(),
            'verified_at'     => now(),
        ]);

        return redirect()->route('billing.status')
            ->with('success', "Subscription activated! You're now on the {$plan->name} plan.");
    }

    // ── Offline payment: upload proof ────────────────────────────────────────

    public function uploadProof(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id'  => ['required', 'exists:subscription_plans,id'],
            'cycle'    => ['required', 'in:monthly,annual'],
            'months'   => ['required', 'integer', 'min:1', 'max:12'],
            'proof'    => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $company = $request->user()->currentCompany;
        $plan    = SubscriptionPlan::findOrFail($validated['plan_id']);
        $months  = $validated['cycle'] === 'annual' ? 12 : (int) $validated['months'];
        $amount  = $validated['cycle'] === 'annual'
            ? $plan->price_annual
            : round($plan->price_monthly * $months, 2);

        $path = $request->file('proof')->store("proofs/{$company->id}", 'local');

        SubscriptionPayment::create([
            'company_id'    => $company->id,
            'plan_id'       => $plan->id,
            'amount'        => $amount,
            'billing_cycle' => $validated['cycle'],
            'months'        => $months,
            'method'        => 'offline',
            'status'        => 'pending',
            'reference'     => 'OFF-' . strtoupper(Str::random(10)),
            'proof_path'    => $path,
            'notes'         => $validated['notes'],
        ]);

        return redirect()->route('billing.status')
            ->with('success', 'Proof of payment uploaded. Your subscription will be activated within 24 hours after verification.');
    }

    // ── Current subscription status ──────────────────────────────────────────

    public function status(Request $request): Response
    {
        $company = $request->user()->currentCompany;

        $subscription = $company->subscriptions()
            ->with('plan')
            ->latest()
            ->first();

        $pendingPayment = $company->subscriptionPayments()
            ->where('status', 'pending')
            ->with('plan')
            ->latest()
            ->first();

        return Inertia::render('billing/Status', [
            'subscription'   => $subscription,
            'pendingPayment' => $pendingPayment,
            'trialEndsAt'    => $company->trial_ends_at,
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function activateSubscription(int $companyId, SubscriptionPlan $plan, string $cycle, int $months): Subscription
    {
        // Expire any previous active subscriptions
        Subscription::where('company_id', $companyId)
            ->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'expired']);

        $startsAt = now()->toDateString();
        $endsAt   = now()->addMonths($months)->toDateString();

        return Subscription::create([
            'company_id'    => $companyId,
            'plan_id'       => $plan->id,
            'status'        => 'active',
            'billing_cycle' => $cycle,
            'starts_at'     => $startsAt,
            'ends_at'       => $endsAt,
        ]);
    }
}
