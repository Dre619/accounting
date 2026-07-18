<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

/**
 * Appends immutable timeline entries (notes, calls, emails, meetings) against
 * any subject that exposes an activities() morph relation — a Contact today,
 * an Opportunity later.
 */
class ActivityService
{
    public function log(Model $subject, array $data): Activity
    {
        return $subject->activities()->create([
            'company_id'  => $subject->company_id,
            'user_id'     => auth()->id(),
            'type'        => $data['type'] ?? 'note',
            'body'        => $data['body'],
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }
}
