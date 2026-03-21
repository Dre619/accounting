<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyInvitation extends Model
{
    protected $fillable = [
        'company_id', 'email', 'role', 'token', 'invited_by', 'expires_at', 'accepted_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at->isFuture();
    }
}
