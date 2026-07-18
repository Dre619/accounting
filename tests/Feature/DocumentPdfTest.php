<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payslip;
use App\Models\PayrollRun;
use App\Models\TaxRate;
use App\Services\DocumentPdfService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPdfTest extends TestCase
{
    private function pdf(): DocumentPdfService
    {
        return app(DocumentPdfService::class);
    }

    private function company(): Company
    {
        return (new Company)->forceFill([
            'name' => 'Acme Trading Ltd', 'address' => '12 Cairo Rd', 'city' => 'Lusaka',
            'tpin' => '1000000000', 'vat_number' => 'VAT123', 'email' => 'hi@acme.zm', 'phone' => '+260 97 000 0000',
        ]);
    }

    private function invoiceWithItem(): Invoice
    {
        $invoice = (new Invoice)->forceFill([
            'invoice_number' => 'INV-0042', 'status' => 'sent',
            'issue_date' => '2026-07-01', 'due_date' => '2026-07-31', 'reference' => 'PO-9',
            'subtotal' => 1000, 'tax_amount' => 160, 'discount_amount' => 0,
            'total' => 1160, 'amount_paid' => 160, 'amount_due' => 1000, 'notes' => 'Thank you.',
        ]);

        $item = (new InvoiceItem)->forceFill([
            'description' => 'Consulting — July', 'quantity' => 10, 'unit_price' => 100,
            'discount_percent' => 0, 'total' => 1160,
        ]);
        $item->setRelation('taxRate', (new TaxRate)->forceFill(['name' => 'VAT 16%']));
        $item->setRelation('account', (new Account)->forceFill(['code' => '4000', 'name' => 'Sales']));

        $invoice->setRelation('contact', (new Contact)->forceFill([
            'name' => 'Blue Corp', 'address' => '9 Great East Rd', 'tpin' => '2000000000', 'email' => 'ap@blue.zm',
        ]));
        $invoice->setRelation('items', collect([$item]));

        return $invoice;
    }

    private function billWithItem(): Bill
    {
        $bill = (new Bill)->forceFill([
            'bill_number' => 'BILL-0009', 'status' => 'approved',
            'issue_date' => '2026-07-02', 'due_date' => '2026-08-02', 'reference' => 'SUP-1',
            'subtotal' => 500, 'tax_amount' => 80, 'discount_amount' => 0,
            'total' => 580, 'amount_paid' => 0, 'amount_due' => 580, 'notes' => 'Net 30.',
        ]);

        $item = (new BillItem)->forceFill([
            'description' => 'Office supplies', 'quantity' => 5, 'unit_price' => 100,
            'discount_percent' => 0, 'total' => 580,
        ]);
        $item->setRelation('taxRate', (new TaxRate)->forceFill(['name' => 'VAT 16%']));
        $item->setRelation('account', (new Account)->forceFill(['code' => '6000', 'name' => 'Expenses']));

        $bill->setRelation('contact', (new Contact)->forceFill([
            'name' => 'Supplier Co', 'tpin' => '3000000000', 'email' => 'sales@supplier.zm',
        ]));
        $bill->setRelation('items', collect([$item]));

        return $bill;
    }

    private function payslip(): array
    {
        $payroll = (new PayrollRun)->forceFill([
            'period' => 'July 2026', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
        ]);
        $payroll->id = 1;

        $payslip = (new Payslip)->forceFill([
            'payroll_run_id' => 1, 'basic_salary' => 8000, 'gross_salary' => 8000,
            'paye' => 1200, 'napsa_employee' => 400, 'napsa_employer' => 400,
            'nhima_employee' => 80, 'nhima_employer' => 80, 'other_deductions' => 0,
            'total_deductions' => 1680, 'net_salary' => 6320,
        ]);
        $payslip->setRelation('employee', (new Employee)->forceFill([
            'first_name' => 'Grace', 'last_name' => 'Banda', 'employee_number' => 'EMP-001',
            'job_title' => 'Accountant', 'tpin' => '4000000000', 'napsa_number' => 'N123',
            'bank_name' => 'Zanaco', 'bank_account' => '000111222',
        ]));

        return [$payroll, $payslip];
    }

    private function assertIsPdf(string $bytes): void
    {
        $this->assertStringStartsWith('%PDF-', $bytes, 'Output is not a PDF document.');
        $this->assertGreaterThan(1500, strlen($bytes), 'PDF is suspiciously small — it likely failed to render.');
    }

    public function test_invoice_renders_to_a_pdf(): void
    {
        $this->assertIsPdf($this->pdf()->raw('invoices.print', [
            'invoice' => $this->invoiceWithItem(),
            'company' => $this->company(),
            'logoSrc' => null,
        ]));
    }

    public function test_bill_renders_to_a_pdf(): void
    {
        $this->assertIsPdf($this->pdf()->raw('bills.print', [
            'bill'    => $this->billWithItem(),
            'company' => $this->company(),
            'logoSrc' => null,
        ]));
    }

    public function test_payslip_renders_to_a_pdf(): void
    {
        [$payroll, $payslip] = $this->payslip();

        $this->assertIsPdf($this->pdf()->raw('payroll.payslip', [
            'payslip' => $payslip,
            'payroll' => $payroll,
            'company' => $this->company(),
        ]));
    }

    public function test_invoice_with_an_embedded_logo_renders(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('logo.png', 120, 60)->store('logos', 'public');

        $logoSrc = $this->pdf()->logoDataUri($path);
        $this->assertStringStartsWith('data:image/', $logoSrc);

        $this->assertIsPdf($this->pdf()->raw('invoices.print', [
            'invoice' => $this->invoiceWithItem(),
            'company' => $this->company(),
            'logoSrc' => $logoSrc,
        ]));
    }

    public function test_missing_logo_yields_no_data_uri(): void
    {
        Storage::fake('public');

        $this->assertNull($this->pdf()->logoDataUri(null));
        $this->assertNull($this->pdf()->logoDataUri('logos/does-not-exist.png'));
    }

    public function test_stream_inline_sets_pdf_headers_and_does_not_force_download(): void
    {
        $response = $this->pdf()->streamInline(
            'invoices.print',
            ['invoice' => $this->invoiceWithItem(), 'company' => $this->company(), 'logoSrc' => null],
            'Invoice-INV-0042.pdf',
        );

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        // 'inline' opens in the browser's PDF viewer; 'attachment' would force a download.
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Invoice-INV-0042.pdf', $response->headers->get('Content-Disposition'));
        $this->assertIsPdf($response->getContent());
    }

    // ── Financial reports ─────────────────────────────────────────────────────

    private function companyArray(): array
    {
        return ['name' => 'Acme Trading Ltd', 'currency' => 'ZMW'];
    }

    public function test_profit_loss_renders_to_a_pdf(): void
    {
        $this->assertIsPdf($this->pdf()->raw('reports.profit-loss', [
            'income'        => [['code' => '4000', 'name' => 'Sales', 'subtype' => null, 'balance' => 12000.0]],
            'expenses'      => [['code' => '6000', 'name' => 'Rent', 'subtype' => null, 'balance' => 4500.0]],
            'totalIncome'   => 12000.0,
            'totalExpenses' => 4500.0,
            'netProfit'     => 7500.0,
            'from'          => '2026-01-01',
            'to'            => '2026-07-31',
            'company'       => $this->companyArray(),
        ]));
    }

    public function test_profit_loss_renders_a_loss_and_empty_sections(): void
    {
        $this->assertIsPdf($this->pdf()->raw('reports.profit-loss', [
            'income'        => [],
            'expenses'      => [],
            'totalIncome'   => 0.0,
            'totalExpenses' => 0.0,
            'netProfit'     => -1200.0,
            'from'          => '2026-01-01',
            'to'            => '2026-07-31',
            'company'       => $this->companyArray(),
        ]));
    }

    public function test_balance_sheet_renders_to_a_pdf(): void
    {
        $this->assertIsPdf($this->pdf()->raw('reports.balance-sheet', [
            'assets' => [
                'current' => [['code' => '1000', 'name' => 'Bank', 'balance' => 8000.0]],
                'fixed'   => [['code' => '1500', 'name' => 'Equipment', 'balance' => 5000.0]],
                'other'   => [],
            ],
            'liabilities' => [
                'current'   => [['code' => '2000', 'name' => 'Payables', 'balance' => 3000.0]],
                'long_term' => [],
            ],
            'equity'           => [['code' => '3000', 'name' => 'Owner Capital', 'balance' => 7000.0]],
            'retainedEarnings' => 3000.0,
            'totalAssets'      => 13000.0,
            'totalLiabilities' => 3000.0,
            'totalEquity'      => 10000.0,
            'asOf'             => '2026-07-31',
            'company'          => $this->companyArray(),
        ]));
    }

    public function test_vat_summary_renders_with_a_net_payable(): void
    {
        $this->assertIsPdf($this->pdf()->raw('reports.vat-summary', [
            'invoices' => collect([
                (object) ['invoice_number' => 'INV-1', 'issue_date' => '2026-07-01', 'subtotal' => 1000, 'tax_amount' => 160, 'total' => 1160],
            ]),
            'bills' => collect([
                (object) ['bill_number' => 'BILL-1', 'issue_date' => '2026-07-02', 'subtotal' => 500, 'tax_amount' => 80, 'total' => 580],
            ]),
            'outputVat'  => 160.0,
            'inputVat'   => 80.0,
            'vatPayable' => 80.0,
            'from'       => '2026-07-01',
            'to'         => '2026-07-31',
            'company'    => $this->companyArray(),
        ]));
    }

    public function test_report_renders_with_an_embedded_company_logo(): void
    {
        Storage::fake('public');
        $path    = UploadedFile::fake()->image('logo.png', 120, 60)->store('logos', 'public');
        $logoSrc = $this->pdf()->logoDataUri($path);

        $this->assertStringStartsWith('data:image/', $logoSrc);
        $this->assertIsPdf($this->pdf()->raw('reports.profit-loss', [
            'income'        => [['code' => '4000', 'name' => 'Sales', 'subtype' => null, 'balance' => 12000.0]],
            'expenses'      => [],
            'totalIncome'   => 12000.0,
            'totalExpenses' => 0.0,
            'netProfit'     => 12000.0,
            'from'          => '2026-01-01',
            'to'            => '2026-07-31',
            'company'       => $this->companyArray(),
            'logoSrc'       => $logoSrc,
        ]));
    }

    public function test_vat_summary_renders_a_refund_and_empty_tables(): void
    {
        // Negative vatPayable exercises the refund branch; empty collections
        // exercise the isEmpty() placeholder rows.
        $this->assertIsPdf($this->pdf()->raw('reports.vat-summary', [
            'invoices'   => collect(),
            'bills'      => collect(),
            'outputVat'  => 0.0,
            'inputVat'   => 200.0,
            'vatPayable' => -200.0,
            'from'       => '2026-07-01',
            'to'         => '2026-07-31',
            'company'    => $this->companyArray(),
        ]));
    }
}
