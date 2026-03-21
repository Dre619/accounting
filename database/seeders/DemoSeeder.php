<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Demo User ──────────────────────────────────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'demo@cloudone.zm'],
            [
                'name'              => 'Demo User',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // ── 2. Company ────────────────────────────────────────────────────────
        $company = Company::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Lusaka Tech Solutions Ltd'],
            [
                'tpin'               => '1000123456',
                'vat_number'         => 'V00012345Z',
                'email'              => 'accounts@luskatechsolutions.zm',
                'phone'              => '+260 211 123 456',
                'address'            => 'Plot 15, Cairo Road',
                'city'               => 'Lusaka',
                'country'            => 'Zambia',
                'currency'           => 'ZMW',
                'financial_year_end' => '12-31',
                'invoice_prefix'     => 'INV',
                'invoice_sequence'   => 1,
            ]
        );

        // Provision chart of accounts + tax rates if fresh
        if ($company->accounts()->count() === 0) {
            app(CompanyProvisioningService::class)->provision($company);
        }

        // Extend trial so login works
        $company->update(['trial_ends_at' => now()->addDays(30)]);

        // Lookup key accounts once
        $ar   = Account::where('company_id', $company->id)->where('code', '1200')->first(); // AR
        $ap   = Account::where('company_id', $company->id)->where('code', '2000')->first(); // AP
        $bank = Account::where('company_id', $company->id)->where('code', '1100')->first(); // Bank

        $vat16 = TaxRate::where('company_id', $company->id)->where('code', 'VAT16')->first();
        $vat0  = TaxRate::where('company_id', $company->id)->where('code', 'VAT0')->first();

        // ── 3. Contacts ───────────────────────────────────────────────────────
        $customers = $this->seedCustomers($company, $ar, $vat16);
        $suppliers = $this->seedSuppliers($company, $ap);

        // ── 4. Invoices ───────────────────────────────────────────────────────
        $invoices = $this->seedInvoices($company, $user, $customers, $ar, $vat16, $vat0);

        // ── 5. Payments (receipts) ────────────────────────────────────────────
        $this->seedReceipts($company, $user, $invoices, $bank);

        // ── 6. Bills ──────────────────────────────────────────────────────────
        $bills = $this->seedBills($company, $user, $suppliers, $ap, $vat16);

        // ── 7. Bill payments ──────────────────────────────────────────────────
        $this->seedBillPayments($company, $user, $bills, $bank);

        // ── 8. Employees ──────────────────────────────────────────────────────
        $employees = $this->seedEmployees($company, $user);

        // ── 9. Payroll run ────────────────────────────────────────────────────
        $this->seedPayroll($company, $user, $employees);

        $this->command->info('Demo data seeded — login: demo@cloudone.zm / password');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Customers
    // ─────────────────────────────────────────────────────────────────────────
    private function seedCustomers(Company $company, ?Account $ar, ?TaxRate $vat): array
    {
        $data = [
            ['name' => 'Zambeef Products Plc',       'type' => 'customer', 'email' => 'ap@zambeef.co.zm',         'phone' => '+260 211 370 600', 'tpin' => '1000111001', 'city' => 'Lusaka'],
            ['name' => 'Airtel Zambia Ltd',           'type' => 'customer', 'email' => 'procurement@airtel.zm',    'phone' => '+260 211 366 000', 'tpin' => '1000222002', 'city' => 'Lusaka'],
            ['name' => 'Shoprite Checkers Zambia',   'type' => 'customer', 'email' => 'accounts@shoprite.co.zm',  'phone' => '+260 211 234 567', 'tpin' => '1000333003', 'city' => 'Lusaka'],
            ['name' => 'Choma Solar Ltd',            'type' => 'customer', 'email' => 'info@chomasolar.zm',       'phone' => '+260 213 220 100', 'tpin' => '1000444004', 'city' => 'Choma'],
            ['name' => 'Ndola Copper Traders',       'type' => 'customer', 'email' => 'finance@nctraders.zm',     'phone' => '+260 212 611 200', 'tpin' => '1000555005', 'city' => 'Ndola'],
        ];

        $contacts = [];
        foreach ($data as $row) {
            $contacts[] = Contact::firstOrCreate(
                ['company_id' => $company->id, 'name' => $row['name']],
                array_merge($row, [
                    'company_id'                    => $company->id,
                    'country'                       => 'Zambia',
                    'is_active'                     => true,
                    'default_receivable_account_id' => $ar?->id,
                    'default_tax_rate_id'           => $vat?->id,
                ])
            );
        }
        return $contacts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Suppliers
    // ─────────────────────────────────────────────────────────────────────────
    private function seedSuppliers(Company $company, ?Account $ap): array
    {
        $data = [
            ['name' => 'Puma Energy Zambia',         'type' => 'supplier', 'email' => 'accounts@puma.zm',         'phone' => '+260 211 260 000', 'tpin' => '1000666006', 'city' => 'Lusaka'],
            ['name' => 'Stanbic Bank Zambia',        'type' => 'supplier', 'email' => 'corporate@stanbic.co.zm',  'phone' => '+260 211 370 000', 'tpin' => '1000777007', 'city' => 'Lusaka'],
            ['name' => 'Mukuba Office Supplies',     'type' => 'supplier', 'email' => 'sales@mukubaoffice.zm',    'phone' => '+260 212 222 333', 'tpin' => '1000888008', 'city' => 'Kitwe'],
        ];

        $contacts = [];
        foreach ($data as $row) {
            $contacts[] = Contact::firstOrCreate(
                ['company_id' => $company->id, 'name' => $row['name']],
                array_merge($row, [
                    'company_id'                 => $company->id,
                    'country'                    => 'Zambia',
                    'is_active'                  => true,
                    'default_payable_account_id' => $ap?->id,
                ])
            );
        }
        return $contacts;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Invoices
    // ─────────────────────────────────────────────────────────────────────────
    private function seedInvoices(
        Company $company, User $user, array $customers,
        ?Account $ar, ?TaxRate $vat16, ?TaxRate $vat0
    ): array {
        if (Invoice::where('company_id', $company->id)->exists()) {
            return Invoice::where('company_id', $company->id)->get()->all();
        }

        $invoices = [];

        // Invoice 1 — Paid, last month
        $inv = Invoice::create([
            'company_id'           => $company->id,
            'contact_id'           => $customers[0]->id,
            'invoice_number'       => 'INV-0001',
            'status'               => 'paid',
            'issue_date'           => now()->subMonth()->startOfMonth(),
            'due_date'             => now()->subMonth()->startOfMonth()->addDays(30),
            'receivable_account_id'=> $ar?->id,
            'created_by'           => $user->id,
            'sent_at'              => now()->subMonth()->startOfMonth()->addDay(),
            'subtotal'             => 0,
            'tax_amount'           => 0,
            'discount_amount'      => 0,
            'withholding_tax_amount'=> 0,
            'total'                => 0,
            'amount_paid'          => 0,
            'amount_due'           => 0,
        ]);
        $this->addInvoiceItems($inv, [
            ['desc' => 'IT Infrastructure Consulting', 'qty' => 10, 'price' => 1500.00, 'tax' => $vat16],
            ['desc' => 'Network Setup & Configuration', 'qty' => 1,  'price' => 5800.00, 'tax' => $vat16],
        ]);
        $invoices[] = $inv;

        // Invoice 2 — Sent (outstanding), current month
        $inv = Invoice::create([
            'company_id'           => $company->id,
            'contact_id'           => $customers[1]->id,
            'invoice_number'       => 'INV-0002',
            'status'               => 'sent',
            'issue_date'           => now()->subDays(15),
            'due_date'             => now()->addDays(15),
            'receivable_account_id'=> $ar?->id,
            'created_by'           => $user->id,
            'sent_at'              => now()->subDays(14),
            'subtotal'             => 0,
            'tax_amount'           => 0,
            'discount_amount'      => 0,
            'withholding_tax_amount'=> 0,
            'total'                => 0,
            'amount_paid'          => 0,
            'amount_due'           => 0,
        ]);
        $this->addInvoiceItems($inv, [
            ['desc' => 'Software Development — Phase 1', 'qty' => 40, 'price' => 450.00, 'tax' => $vat16],
            ['desc' => 'Project Management',             'qty' => 8,  'price' => 300.00, 'tax' => $vat16],
        ]);
        $invoices[] = $inv;

        // Invoice 3 — Partial payment received
        $inv = Invoice::create([
            'company_id'           => $company->id,
            'contact_id'           => $customers[2]->id,
            'invoice_number'       => 'INV-0003',
            'status'               => 'partial',
            'issue_date'           => now()->subDays(25),
            'due_date'             => now()->addDays(5),
            'receivable_account_id'=> $ar?->id,
            'created_by'           => $user->id,
            'sent_at'              => now()->subDays(24),
            'subtotal'             => 0,
            'tax_amount'           => 0,
            'discount_amount'      => 0,
            'withholding_tax_amount'=> 0,
            'total'                => 0,
            'amount_paid'          => 0,
            'amount_due'           => 0,
        ]);
        $this->addInvoiceItems($inv, [
            ['desc' => 'ERP System License (Annual)',    'qty' => 5,  'price' => 2200.00, 'tax' => $vat16],
            ['desc' => 'Implementation Support',        'qty' => 20, 'price' => 350.00,  'tax' => $vat16],
        ]);
        $invoices[] = $inv;

        // Invoice 4 — Overdue (sent, past due)
        $inv = Invoice::create([
            'company_id'           => $company->id,
            'contact_id'           => $customers[3]->id,
            'invoice_number'       => 'INV-0004',
            'status'               => 'sent',
            'issue_date'           => now()->subDays(45),
            'due_date'             => now()->subDays(15),
            'receivable_account_id'=> $ar?->id,
            'created_by'           => $user->id,
            'sent_at'              => now()->subDays(44),
            'subtotal'             => 0,
            'tax_amount'           => 0,
            'discount_amount'      => 0,
            'withholding_tax_amount'=> 0,
            'total'                => 0,
            'amount_paid'          => 0,
            'amount_due'           => 0,
        ]);
        $this->addInvoiceItems($inv, [
            ['desc' => 'Solar Panel Installation', 'qty' => 1,  'price' => 18500.00, 'tax' => $vat0],
            ['desc' => 'Battery Storage System',   'qty' => 2,  'price' => 4200.00,  'tax' => $vat0],
        ]);
        $invoices[] = $inv;

        // Invoice 5 — Draft
        $inv = Invoice::create([
            'company_id'           => $company->id,
            'contact_id'           => $customers[4]->id,
            'invoice_number'       => 'INV-0005',
            'status'               => 'draft',
            'issue_date'           => now(),
            'due_date'             => now()->addDays(30),
            'receivable_account_id'=> $ar?->id,
            'created_by'           => $user->id,
            'subtotal'             => 0,
            'tax_amount'           => 0,
            'discount_amount'      => 0,
            'withholding_tax_amount'=> 0,
            'total'                => 0,
            'amount_paid'          => 0,
            'amount_due'           => 0,
        ]);
        $this->addInvoiceItems($inv, [
            ['desc' => 'Copper Wire Supply — 500m rolls', 'qty' => 10, 'price' => 1200.00, 'tax' => $vat16],
        ]);
        $invoices[] = $inv;

        // Update invoice_sequence
        $company->update(['invoice_sequence' => 6]);

        return $invoices;
    }

    private function addInvoiceItems(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $i => $line) {
            $subtotal  = round($line['qty'] * $line['price'], 2);
            $taxRate   = $line['tax'] ? $line['tax']->rate : 0;
            $taxAmount = round($subtotal * $taxRate / 100, 2);

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $line['desc'],
                'quantity'    => $line['qty'],
                'unit_price'  => $line['price'],
                'subtotal'    => $subtotal,
                'tax_rate_id' => $line['tax']?->id,
                'tax_amount'  => $taxAmount,
                'total'       => $subtotal + $taxAmount,
                'sort_order'  => $i + 1,
            ]);
        }

        $invoice->load('items');
        $invoice->recalculate();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Receipts (customer payments)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedReceipts(Company $company, User $user, array $invoices, ?Account $bank): void
    {
        if (Payment::where('company_id', $company->id)->where('type', 'receipt')->exists()) {
            return;
        }

        // Full payment on invoice 1
        $inv1  = $invoices[0];
        $rcpt1 = Payment::create([
            'company_id'        => $company->id,
            'contact_id'        => $inv1->contact_id,
            'type'              => 'receipt',
            'payment_number'    => 'RCP-0001',
            'payment_date'      => now()->subMonth()->startOfMonth()->addDays(28),
            'amount'            => $inv1->total,
            'withholding_tax_amount' => 0,
            'method'            => 'bank_transfer',
            'reference'         => 'ZBL-' . rand(100000, 999999),
            'deposit_account_id'=> $bank?->id,
            'created_by'        => $user->id,
        ]);
        PaymentAllocation::create([
            'payment_id'     => $rcpt1->id,
            'allocatable_id' => $inv1->id,
            'allocatable_type'=> Invoice::class,
            'amount'         => $inv1->total,
        ]);
        $inv1->update([
            'amount_paid' => $inv1->total,
            'amount_due'  => 0,
            'status'      => 'paid',
        ]);

        // Partial payment on invoice 3
        $inv3       = $invoices[2];
        $partialAmt = round($inv3->total * 0.4, 2);
        $rcpt2      = Payment::create([
            'company_id'        => $company->id,
            'contact_id'        => $inv3->contact_id,
            'type'              => 'receipt',
            'payment_number'    => 'RCP-0002',
            'payment_date'      => now()->subDays(10),
            'amount'            => $partialAmt,
            'withholding_tax_amount' => 0,
            'method'            => 'mtn_money',
            'reference'         => 'MTN-' . rand(100000, 999999),
            'deposit_account_id'=> $bank?->id,
            'created_by'        => $user->id,
        ]);
        PaymentAllocation::create([
            'payment_id'      => $rcpt2->id,
            'allocatable_id'  => $inv3->id,
            'allocatable_type'=> Invoice::class,
            'amount'          => $partialAmt,
        ]);
        $inv3->update([
            'amount_paid' => $partialAmt,
            'amount_due'  => round($inv3->total - $partialAmt, 2),
            'status'      => 'partial',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bills
    // ─────────────────────────────────────────────────────────────────────────
    private function seedBills(
        Company $company, User $user, array $suppliers,
        ?Account $ap, ?TaxRate $vat16
    ): array {
        if (Bill::where('company_id', $company->id)->exists()) {
            return Bill::where('company_id', $company->id)->get()->all();
        }

        $bills = [];

        // Bill 1 — Paid (fuel)
        $bill = Bill::create([
            'company_id'        => $company->id,
            'contact_id'        => $suppliers[0]->id,
            'bill_number'       => 'BILL-0001',
            'reference'         => 'PUMA-INV-7712',
            'status'            => 'paid',
            'issue_date'        => now()->subMonth()->startOfMonth()->addDays(5),
            'due_date'          => now()->subMonth()->startOfMonth()->addDays(35),
            'payable_account_id'=> $ap?->id,
            'created_by'        => $user->id,
            'approved_at'       => now()->subMonth()->startOfMonth()->addDays(6),
            'subtotal'          => 0,
            'tax_amount'        => 0,
            'discount_amount'   => 0,
            'withholding_tax_amount' => 0,
            'total'             => 0,
            'amount_paid'       => 0,
            'amount_due'        => 0,
        ]);
        $this->addBillItems($bill, [
            ['desc' => 'Diesel Fuel — 500 litres', 'qty' => 500, 'price' => 28.50, 'tax' => $vat16],
        ]);
        $bills[] = $bill;

        // Bill 2 — Outstanding (bank charges)
        $bill = Bill::create([
            'company_id'        => $company->id,
            'contact_id'        => $suppliers[1]->id,
            'bill_number'       => 'BILL-0002',
            'reference'         => 'STANBIC-FEE-2026',
            'status'            => 'received',
            'issue_date'        => now()->subDays(10),
            'due_date'          => now()->addDays(20),
            'payable_account_id'=> $ap?->id,
            'created_by'        => $user->id,
            'approved_at'       => now()->subDays(9),
            'subtotal'          => 0,
            'tax_amount'        => 0,
            'discount_amount'   => 0,
            'withholding_tax_amount' => 0,
            'total'             => 0,
            'amount_paid'       => 0,
            'amount_due'        => 0,
        ]);
        $this->addBillItems($bill, [
            ['desc' => 'Monthly Account Maintenance Fee', 'qty' => 3, 'price' => 450.00, 'tax' => null],
            ['desc' => 'SWIFT Transfer Fees',             'qty' => 2, 'price' => 285.00, 'tax' => null],
        ]);
        $bills[] = $bill;

        // Bill 3 — Overdue (office supplies)
        $bill = Bill::create([
            'company_id'        => $company->id,
            'contact_id'        => $suppliers[2]->id,
            'bill_number'       => 'BILL-0003',
            'reference'         => 'MOS-2024-881',
            'status'            => 'overdue',
            'issue_date'        => now()->subDays(40),
            'due_date'          => now()->subDays(10),
            'payable_account_id'=> $ap?->id,
            'created_by'        => $user->id,
            'approved_at'       => now()->subDays(39),
            'subtotal'          => 0,
            'tax_amount'        => 0,
            'discount_amount'   => 0,
            'withholding_tax_amount' => 0,
            'total'             => 0,
            'amount_paid'       => 0,
            'amount_due'        => 0,
        ]);
        $this->addBillItems($bill, [
            ['desc' => 'A4 Paper Reams (80gsm)',  'qty' => 20,  'price' => 95.00,  'tax' => $vat16],
            ['desc' => 'Printer Toner Cartridges','qty' => 4,   'price' => 620.00, 'tax' => $vat16],
            ['desc' => 'Office Stationery Bundle','qty' => 1,   'price' => 880.00, 'tax' => $vat16],
        ]);
        $bills[] = $bill;

        return $bills;
    }

    private function addBillItems(Bill $bill, array $lines): void
    {
        foreach ($lines as $i => $line) {
            $subtotal  = round($line['qty'] * $line['price'], 2);
            $taxRate   = $line['tax'] ? $line['tax']->rate : 0;
            $taxAmount = round($subtotal * $taxRate / 100, 2);

            BillItem::create([
                'bill_id'     => $bill->id,
                'description' => $line['desc'],
                'quantity'    => $line['qty'],
                'unit_price'  => $line['price'],
                'subtotal'    => $subtotal,
                'tax_rate_id' => $line['tax']?->id,
                'tax_amount'  => $taxAmount,
                'total'       => $subtotal + $taxAmount,
                'sort_order'  => $i + 1,
            ]);
        }

        $bill->load('items');
        $bill->recalculate();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bill Payments
    // ─────────────────────────────────────────────────────────────────────────
    private function seedBillPayments(Company $company, User $user, array $bills, ?Account $bank): void
    {
        if (Payment::where('company_id', $company->id)->where('type', 'payment')->exists()) {
            return;
        }

        $bill1 = $bills[0]; // paid
        $pmt   = Payment::create([
            'company_id'        => $company->id,
            'contact_id'        => $bill1->contact_id,
            'type'              => 'payment',
            'payment_number'    => 'PMT-0001',
            'payment_date'      => now()->subMonth()->startOfMonth()->addDays(30),
            'amount'            => $bill1->total,
            'withholding_tax_amount' => 0,
            'method'            => 'bank_transfer',
            'reference'         => 'PAY-' . rand(100000, 999999),
            'deposit_account_id'=> $bank?->id,
            'created_by'        => $user->id,
        ]);
        PaymentAllocation::create([
            'payment_id'      => $pmt->id,
            'allocatable_id'  => $bill1->id,
            'allocatable_type'=> Bill::class,
            'amount'          => $bill1->total,
        ]);
        $bill1->update([
            'amount_paid' => $bill1->total,
            'amount_due'  => 0,
            'status'      => 'paid',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Employees
    // ─────────────────────────────────────────────────────────────────────────
    private function seedEmployees(Company $company, User $user): array
    {
        if (Employee::where('company_id', $company->id)->exists()) {
            return Employee::where('company_id', $company->id)->get()->all();
        }

        $records = [
            [
                'employee_number' => 'EMP-001',
                'first_name'      => 'Chanda',
                'last_name'       => 'Mwale',
                'job_title'       => 'Software Engineer',
                'department'      => 'Technology',
                'employment_type' => 'full_time',
                'basic_salary'    => 12500.00,
                'hire_date'       => '2022-03-01',
                'tpin'            => '1001001001',
                'napsa_number'    => 'NAP-1001001',
                'nhima_number'    => 'NHI-1001001',
                'email'           => 'c.mwale@luskatechsolutions.zm',
                'phone'           => '+260 977 100 001',
                'bank_name'       => 'Zanaco',
                'bank_account'    => '2001001001',
                'bank_branch'     => 'Cairo Road',
            ],
            [
                'employee_number' => 'EMP-002',
                'first_name'      => 'Mutale',
                'last_name'       => 'Banda',
                'job_title'       => 'Accountant',
                'department'      => 'Finance',
                'employment_type' => 'full_time',
                'basic_salary'    => 9800.00,
                'hire_date'       => '2021-07-15',
                'tpin'            => '1002002002',
                'napsa_number'    => 'NAP-1002002',
                'nhima_number'    => 'NHI-1002002',
                'email'           => 'm.banda@luskatechsolutions.zm',
                'phone'           => '+260 977 200 002',
                'bank_name'       => 'Stanbic',
                'bank_account'    => '9002002002',
                'bank_branch'     => 'Manda Hill',
            ],
            [
                'employee_number' => 'EMP-003',
                'first_name'      => 'Nkandu',
                'last_name'       => 'Phiri',
                'job_title'       => 'IT Support Technician',
                'department'      => 'Technology',
                'employment_type' => 'full_time',
                'basic_salary'    => 7200.00,
                'hire_date'       => '2023-01-10',
                'tpin'            => '1003003003',
                'napsa_number'    => 'NAP-1003003',
                'nhima_number'    => 'NHI-1003003',
                'email'           => 'n.phiri@luskatechsolutions.zm',
                'phone'           => '+260 977 300 003',
                'bank_name'       => 'First National Bank',
                'bank_account'    => '6200300003',
                'bank_branch'     => 'Levy',
            ],
            [
                'employee_number' => 'EMP-004',
                'first_name'      => 'Lubuto',
                'last_name'       => 'Tembo',
                'job_title'       => 'Sales Manager',
                'department'      => 'Sales',
                'employment_type' => 'full_time',
                'basic_salary'    => 11000.00,
                'hire_date'       => '2020-09-01',
                'tpin'            => '1004004004',
                'napsa_number'    => 'NAP-1004004',
                'nhima_number'    => 'NHI-1004004',
                'email'           => 'l.tembo@luskatechsolutions.zm',
                'phone'           => '+260 977 400 004',
                'bank_name'       => 'Atlas Mara',
                'bank_account'    => '3004004004',
                'bank_branch'     => 'Crossroads',
            ],
            [
                'employee_number' => 'EMP-005',
                'first_name'      => 'Wanga',
                'last_name'       => 'Mulenga',
                'job_title'       => 'Office Administrator',
                'department'      => 'Administration',
                'employment_type' => 'contract',  // valid enum value
                'basic_salary'    => 5500.00,
                'hire_date'       => '2023-06-01',
                'tpin'            => '1005005005',
                'napsa_number'    => 'NAP-1005005',
                'nhima_number'    => 'NHI-1005005',
                'email'           => 'w.mulenga@luskatechsolutions.zm',
                'phone'           => '+260 977 500 005',
                'bank_name'       => 'Zanaco',
                'bank_account'    => '2005005005',
                'bank_branch'     => 'Kafue Road',
            ],
        ];

        $employees = [];
        foreach ($records as $rec) {
            $employees[] = Employee::create(array_merge($rec, [
                'company_id' => $company->id,
                'is_active'  => true,
                'created_by' => $user->id,
            ]));
        }
        return $employees;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Payroll
    // ─────────────────────────────────────────────────────────────────────────
    private function seedPayroll(Company $company, User $user, array $employees): void
    {
        $period = now()->subMonth()->format('Y-m');

        if (PayrollRun::where('company_id', $company->id)->where('period', $period)->exists()) {
            return;
        }

        // Zambia PAYE / NAPSA / NHIMA constants
        $napsaCap = 1_221.80;

        $totals = [
            'total_gross'           => 0.0,
            'total_paye'            => 0.0,
            'total_napsa_employee'  => 0.0,
            'total_napsa_employer'  => 0.0,
            'total_nhima_employee'  => 0.0,
            'total_nhima_employer'  => 0.0,
            'total_net'             => 0.0,
        ];

        $run = PayrollRun::create([
            'company_id'   => $company->id,
            'period'       => $period,
            'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'period_end'   => now()->subMonth()->endOfMonth()->toDateString(),
            'status'       => 'approved',
            'notes'        => 'Demo payroll — auto-seeded',
            'processed_by' => $user->id,
            'approved_by'  => $user->id,
            'approved_at'  => now()->subMonth()->endOfMonth(),
            ...$totals,
        ]);

        foreach ($employees as $emp) {
            $gross = (float) $emp->basic_salary;

            // PAYE (progressive)
            $paye = $this->calcPaye($gross);

            // NAPSA — 5% employee + 5% employer, cap K1,221.80
            $napsaEmp = min(round($gross * 0.05, 2), $napsaCap);
            $napsaEr  = min(round($gross * 0.05, 2), $napsaCap);

            // NHIMA — 1% each, no cap
            $nhimaEmp = round($gross * 0.01, 2);
            $nhimaEr  = round($gross * 0.01, 2);

            $net = round($gross - $paye - $napsaEmp - $nhimaEmp, 2);

            Payslip::create([
                'payroll_run_id'        => $run->id,
                'employee_id'           => $emp->id,
                'basic_salary'          => $gross,
                'gross_salary'          => $gross,
                'paye'                  => $paye,
                'napsa_employee'        => $napsaEmp,
                'napsa_employer'        => $napsaEr,
                'nhima_employee'        => $nhimaEmp,
                'nhima_employer'        => $nhimaEr,
                'total_deductions'      => round($paye + $napsaEmp + $nhimaEmp, 2),
                'net_salary'            => $net,
            ]);

            $totals['total_gross']          += $gross;
            $totals['total_paye']           += $paye;
            $totals['total_napsa_employee'] += $napsaEmp;
            $totals['total_napsa_employer'] += $napsaEr;
            $totals['total_nhima_employee'] += $nhimaEmp;
            $totals['total_nhima_employer'] += $nhimaEr;
            $totals['total_net']            += $net;
        }

        $run->update(array_map(fn ($v) => round($v, 2), $totals));
    }

    private function calcPaye(float $gross): float
    {
        $bands = [
            [4800,        0.000],
            [6900,        0.200],
            [9200,        0.300],
            [PHP_INT_MAX, 0.375],
        ];

        $tax      = 0.0;
        $prev     = 0.0;
        foreach ($bands as [$ceiling, $rate]) {
            if ($gross <= $prev) break;
            $taxable = min($gross, $ceiling) - $prev;
            $tax    += $taxable * $rate;
            $prev    = $ceiling;
        }
        return round($tax, 2);
    }
}
