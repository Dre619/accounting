<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'entry_number',
        'entry_date',
        'description',
        'status',
        'source',
        'sourceable_type',
        'sourceable_id',
        'created_by',
        'posted_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at'  => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('sort_order');
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function isBalanced(): bool
    {
        return $this->lines->sum('debit') === $this->lines->sum('credit');
    }

    public function post(): void
    {
        $this->update(['status' => 'posted', 'posted_at' => now()]);
    }
}
