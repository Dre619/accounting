<?php

use App\Http\Controllers\Admin\CompaniesController as AdminCompaniesController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PaymentsController as AdminPaymentsController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\ZraVsdcController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\TeamInvitationController;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

$plans = [];

$subscriptionPlans = SubscriptionPlan::where('is_active',true)->get();

foreach($subscriptionPlans as $plan)
    {
        $plans[] = [
            'name' => $plan->name,
            'popular' => $plan->sort_order == 2,
            'price' => $plan->price_monthly,
            'features' => $plan->features,
            'description' => $plan->description
        ];
    }

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
    'plans' => $plans
])->name('home');

Route::get('/brochure', fn () => view('brochure'))->name('brochure');

Route::middleware(['auth', 'verified'])->group(function () {
    // Company setup — shown when user has no company yet
    Route::get('setup', [CompanyController::class, 'create'])->name('company.create');
    Route::post('setup', [CompanyController::class, 'store'])->name('company.store');
    Route::post('companies/{company}/switch', [CompanyController::class, 'switch'])->name('company.switch');

    // All routes below require a company to be selected
    Route::middleware(\App\Http\Middleware\EnsureCompanySelected::class)->group(function () {

        // Billing (accessible even without active subscription)
        Route::get('billing', [BillingController::class, 'plans'])->name('billing.plans');
        Route::get('billing/{plan}/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
        Route::post('billing/verify-online', [BillingController::class, 'verifyOnline'])->name('billing.verify-online');
        Route::post('billing/upload-proof', [BillingController::class, 'uploadProof'])->name('billing.upload-proof');
        Route::get('billing/status', [BillingController::class, 'status'])->name('billing.status');

        // App routes — also require an active subscription or trial
        Route::middleware(\App\Http\Middleware\EnsureActiveSubscription::class)->group(function () {
            Route::get('dashboard', DashboardController::class)->name('dashboard');

            Route::resource('contacts', ContactController::class);

            Route::resource('invoices', InvoiceController::class);
            Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
            Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
            Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');

            Route::middleware('plan.feature:bills')->group(function () {
                Route::resource('bills', BillController::class);
                Route::post('bills/{bill}/approve', [BillController::class, 'approve'])->name('bills.approve');
                Route::post('bills/{bill}/void', [BillController::class, 'void'])->name('bills.void');
                Route::get('bills/{bill}/print', [BillController::class, 'print'])->name('bills.print');
            });

            Route::get('payments/open-documents', [PaymentController::class, 'openDocuments'])->name('payments.open-documents');
            Route::post('payments/{payment}/allocate', [PaymentController::class, 'allocate'])->name('payments.allocate');
            Route::resource('payments', PaymentController::class)->except(['edit', 'update']);

            Route::resource('accounts', AccountController::class);

            // Journal entries (Business only)
            Route::middleware('plan.feature:journals')->prefix('journal')->name('journal.')->group(function () {
                Route::get('/', [JournalEntryController::class, 'index'])->name('index');
                Route::get('create', [JournalEntryController::class, 'create'])->name('create');
                Route::post('/', [JournalEntryController::class, 'store'])->name('store');
                Route::get('{entry}', [JournalEntryController::class, 'show'])->name('show');
                Route::post('{entry}/post', [JournalEntryController::class, 'post'])->name('post');
                Route::delete('{entry}', [JournalEntryController::class, 'destroy'])->name('destroy');
            });

            // Reports
            Route::prefix('reports')->name('reports.')->group(function () {
                Route::get('/', [ReportController::class, 'index'])->name('index');
                // P&L — all plans
                Route::get('profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
                Route::get('profit-loss/print', [ReportController::class, 'profitLossPrint'])->name('profit-loss.print');
                Route::get('profit-loss/csv', [ReportController::class, 'profitLossCsv'])->name('profit-loss.csv');
                // Advanced reports — Growth+
                Route::middleware('plan.feature:reports_advanced')->group(function () {
                    Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
                    Route::get('balance-sheet/print', [ReportController::class, 'balanceSheetPrint'])->name('balance-sheet.print');
                    Route::get('balance-sheet/csv', [ReportController::class, 'balanceSheetCsv'])->name('balance-sheet.csv');
                    Route::get('vat-summary', [ReportController::class, 'vatSummary'])->name('vat-summary');
                    Route::get('vat-summary/print', [ReportController::class, 'vatSummaryPrint'])->name('vat-summary.print');
                    Route::get('vat-summary/csv', [ReportController::class, 'vatSummaryCsv'])->name('vat-summary.csv');
                    Route::get('aged-receivables', [ReportController::class, 'agedReceivables'])->name('aged-receivables');
                    Route::get('aged-payables', [ReportController::class, 'agedPayables'])->name('aged-payables');
                });
            });

            // Recurring invoices (Growth+)
            Route::middleware('plan.feature:recurring')->group(function () {
                Route::resource('recurring', RecurringInvoiceController::class);
                Route::post('recurring/{recurring}/run', [RecurringInvoiceController::class, 'run'])->name('recurring.run');
            });

            // Email invoice
            Route::post('invoices/{invoice}/email', [InvoiceController::class, 'email'])->name('invoices.email');

            // ZRA Smart Invoice / VSDC (Business only)
            Route::middleware('plan.feature:zra_vsdc')->group(function () {
                Route::post('invoices/{invoice}/zra-submit', [ZraVsdcController::class, 'submit'])->name('invoices.zra-submit');
                Route::post('settings/vsdc/initialize', [ZraVsdcController::class, 'initialize'])->name('vsdc.initialize');
            });

            // Employees & Payroll (Business only)
            Route::middleware('plan.feature:payroll')->group(function () {
                Route::resource('employees', EmployeeController::class)->except(['show']);
                Route::resource('payroll', PayrollController::class)->except(['edit', 'update']);
                Route::post('payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
                Route::get('payroll/{payroll}/payslips/{payslip}/print', [PayrollController::class, 'printPayslip'])->name('payroll.payslip.print');
            });

        });
    });
});

// Admin routes
Route::middleware(['auth', \App\Http\Middleware\EnsureAdmin::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::get('payments', [AdminPaymentsController::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/approve', [AdminPaymentsController::class, 'approve'])->name('payments.approve');
        Route::post('payments/{payment}/reject', [AdminPaymentsController::class, 'reject'])->name('payments.reject');
        Route::get('payments/{payment}/proof', [AdminPaymentsController::class, 'proof'])->name('payments.proof');
        Route::get('companies', [AdminCompaniesController::class, 'index'])->name('companies.index');

        // Admin settings — profile, security & appearance (same backend, admin layout)
        Route::get('settings/profile',    [AdminSettingsController::class, 'profile'])->name('settings.profile');
        Route::get('settings/security',   [AdminSettingsController::class, 'security'])->name('settings.security');
        Route::get('settings/appearance', [AdminSettingsController::class, 'appearance'])->name('settings.appearance');

        // Admin settings — platform, plans, users
        Route::get('settings/platform', [AdminSettingsController::class, 'platform'])->name('settings.platform');
        Route::post('settings/platform', [AdminSettingsController::class, 'updatePlatform'])->name('settings.platform.update');
        Route::get('settings/plans', [AdminSettingsController::class, 'plans'])->name('settings.plans');
        Route::patch('settings/plans/{plan}', [AdminSettingsController::class, 'updatePlan'])->name('settings.plans.update');
        Route::get('settings/users', [AdminSettingsController::class, 'users'])->name('settings.users');
        Route::post('settings/users/{user}/toggle-admin', [AdminSettingsController::class, 'toggleAdmin'])->name('settings.users.toggle-admin');
        Route::delete('settings/users/{user}', [AdminSettingsController::class, 'destroyUser'])->name('settings.users.destroy');
    });

require __DIR__.'/settings.php';
