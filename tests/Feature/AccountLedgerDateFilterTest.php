<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the account-ledger "to" date filter against the date-cast trap: a
 * `date`-cast column stores 'Y-m-d 00:00:00', so a string `<=` on the end date
 * silently drops transactions dated ON that day. Must use whereDate().
 */
class AccountLedgerDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\AccountingSeeder::class);

        $this->user = User::factory()->create();
        $this->company = Company::create([
            'user_id' => $this->user->id, 'name' => 'Ledger Co', 'currency' => 'ZMW',
            'invoice_prefix' => 'INV', 'invoice_sequence' => 1,
        ]);
        $this->user->forceFill(['current_company_id' => $this->company->id])->save();

        app(CompanyProvisioningService::class)->provision($this->company);
        $this->actingAs($this->user);
    }

    private function postEntry(string $date, float $amount): void
    {
        $cash = Account::where('company_id', $this->company->id)->where('code', '1000')->value('id');
        $rev  = Account::where('company_id', $this->company->id)->where('code', '4000')->value('id');

        $entry = JournalEntry::create([
            'company_id'   => $this->company->id,
            'entry_number' => $this->company->nextJournalEntryNumber(),
            'entry_date'   => $date,
            'description'  => "Sale {$date}",
            'status'       => 'posted',
            'source'       => 'manual',
            'posted_at'    => now(),
        ]);

        JournalLine::insert([
            ['journal_entry_id' => $entry->id, 'account_id' => $cash, 'description' => 'x', 'debit' => $amount, 'credit' => 0, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $entry->id, 'account_id' => $rev,  'description' => 'x', 'debit' => 0, 'credit' => $amount, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_transaction_on_the_end_date_is_included(): void
    {
        $this->postEntry('2026-07-10', 1000);
        $this->postEntry('2026-07-31', 500); // exactly on the "to" boundary

        $cash = Account::where('company_id', $this->company->id)->where('code', '1000')->first();

        $this->get("/accounts/{$cash->id}?from=2026-07-01&to=2026-07-31")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('accounts/Show')
                ->has('lines', 2) // both rows, including the 31 Jul boundary entry
                ->where('closingBalance', 1500)
                ->etc());
    }
}
