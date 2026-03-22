<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Mark active subscriptions as expired when their end date has passed';

    public function handle(): int
    {
        $expired = Subscription::whereIn('status', ['active', 'trialing'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No subscriptions to expire.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'expired']);
            $count++;
        }

        Log::info("ExpireSubscriptions: expired {$count} subscription(s).");
        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
