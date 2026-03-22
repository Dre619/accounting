<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireTrials extends Command
{
    protected $signature   = 'trials:expire';
    protected $description = 'Log companies whose trial period has ended and have no active subscription';

    public function handle(): int
    {
        $expired = Company::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->whereDoesntHave('subscriptions', function ($q) {
                $q->whereIn('status', ['active', 'trialing'])
                  ->where('ends_at', '>', now());
            })
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired trials found.');
            return self::SUCCESS;
        }

        foreach ($expired as $company) {
            Log::info("ExpireTrials: company #{$company->id} ({$company->name}) trial ended on {$company->trial_ends_at->toDateString()}");
            $this->line("  - {$company->name} (trial ended {$company->trial_ends_at->toDateString()})");
        }

        $this->info("Found {$expired->count()} expired trial(s).");

        return self::SUCCESS;
    }
}
