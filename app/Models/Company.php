<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'tpin',
        'vat_number',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'currency',
        'financial_year_end',
        'invoice_prefix',
        'invoice_sequence',
        'logo_path',
        'trial_ends_at',
        'vsdc_url',
        'vsdc_bhf_id',
        'vsdc_dvc_srl_no',
        'vsdc_initialized',
        'vsdc_sdc_id',
        'vsdc_mrc_no',
        'vsdc_status',
        'vsdc_last_seen_at',
    ];

    protected $casts = [
        'trial_ends_at'    => 'datetime',
        'vsdc_initialized' => 'boolean',
        'vsdc_last_seen_at'=> 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function taxRates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function accountCategories(): HasMany
    {
        return $this->hasMany(AccountCategory::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', ['active', 'trialing'])
            ->where('ends_at', '>', now())
            ->latestOfMany();
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
            ->withPivot('role', 'invited_by', 'joined_at', 'is_active')
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function payrollRuns(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function allUsers(): \Illuminate\Support\Collection
    {
        // Owner + active company_users members
        $members = $this->members()->wherePivot('is_active', true)->get();
        $owner   = $this->owner;

        return $members->prepend($owner)->unique('id');
    }

    public function userCount(): int
    {
        return $this->members()->wherePivot('is_active', true)->count() + 1; // +1 for owner
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null && now()->lt($this->trial_ends_at);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function canAccess(): bool
    {
        return $this->isOnTrial() || $this->hasActiveSubscription();
    }

    public function isVsdcReady(): bool
    {
        return (bool) $this->vsdc_initialized
            && (bool) $this->vsdc_url
            && (bool) $this->vsdc_bhf_id
            && (bool) $this->vsdc_sdc_id
            && (bool) $this->vsdc_mrc_no;
    }

    /**
     * Generate the next invoice number and increment the sequence.
     */
    public function nextInvoiceNumber(): string
    {
        /** @var string $prefix */
        $prefix = $this->invoice_prefix;
        /** @var int $sequence */
        $sequence = $this->invoice_sequence;
        $number = "{$prefix}-" . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        $this->increment('invoice_sequence');
        return $number;
    }
}
