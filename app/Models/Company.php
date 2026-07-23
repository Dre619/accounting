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
        'tax_regime',
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function defaultWarehouse(): HasOne
    {
        return $this->hasOne(Warehouse::class)->where('is_default', true);
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

    /** True when the company files Turnover Tax instead of VAT + income tax. */
    public function isOnTurnoverTax(): bool
    {
        return $this->tax_regime === 'turnover';
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
     * Next journal entry number for this company, e.g. "JNL-0007".
     *
     * Derived from the highest number ever issued INCLUDING soft-deleted entries:
     * journal_entries is soft-deleting but unique(company_id, entry_number) still
     * counts trashed rows, so numbering from a live count() reuses a taken slot
     * and raises a duplicate key error. The loop also steps over any gaps or
     * duplicates left by the previous count-based scheme.
     */
    public function nextJournalEntryNumber(): string
    {
        return $this->nextDocumentNumber(
            fn () => $this->journalEntries()->withTrashed(), 'entry_number', 'JNL-'
        );
    }

    /** Next purchase order number, e.g. "PO-0007". */
    public function nextPurchaseOrderNumber(): string
    {
        return $this->nextDocumentNumber(
            fn () => $this->purchaseOrders()->withTrashed(), 'po_number', 'PO-'
        );
    }

    /** Next sales order / quotation number, e.g. "SO-0007". */
    public function nextSalesOrderNumber(): string
    {
        return $this->nextDocumentNumber(
            fn () => $this->salesOrders()->withTrashed(), 'order_number', 'SO-'
        );
    }

    /**
     * Sequential document numbering that survives deletion.
     *
     * Counting rows is unsafe: a soft delete lowers the count while the number
     * stays taken, and a force delete leaves a gap the count then reissues. So
     * take the highest number ever issued and step past anything already used.
     * Ordering by length first keeps it correct beyond four digits.
     *
     * @param  \Closure():\Illuminate\Database\Eloquent\Builder  $query
     */
    private function nextDocumentNumber(\Closure $query, string $column, string $prefix): string
    {
        $this->lockForNumbering();

        $last = $query()
            ->orderByRaw("LENGTH({$column}) DESC")
            ->orderBy($column, 'DESC')
            ->value($column);

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        while ($query()->where($column, $this->formatDocumentNumber($prefix, $next))->exists()) {
            $next++;
        }

        return $this->formatDocumentNumber($prefix, $next);
    }

    /**
     * Serialize document-number generation for this company.
     *
     * Two posts running at once could otherwise read the same "highest number"
     * and generate the same value, colliding on the unique index and failing the
     * whole posting. A row lock on the company makes concurrent generators queue:
     * the lock is held until the caller's transaction commits (all posting flows
     * run inside a transaction), by which point the first number is committed and
     * visible to the next waiter. Outside a transaction it is a harmless no-op —
     * and on sqlite (tests) lockForUpdate is a no-op regardless.
     */
    private function lockForNumbering(): void
    {
        static::whereKey($this->getKey())->lockForUpdate()->first();
    }

    private function formatDocumentNumber(string $prefix, int $sequence): string
    {
        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate the next invoice number and increment the sequence.
     */
    public function nextInvoiceNumber(): string
    {
        $this->lockForNumbering();

        /** @var string $prefix */
        $prefix = $this->invoice_prefix;
        // Re-read the sequence from the locked row rather than trusting a possibly
        // stale in-memory value, so concurrent callers cannot mint the same number.
        $sequence = (int) static::whereKey($this->getKey())->value('invoice_sequence');
        $number = "{$prefix}-" . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        $this->increment('invoice_sequence');
        return $number;
    }
}
