<?php

namespace Database\Seeders;

use App\Models\ManualSection;
use Illuminate\Database\Seeder;

class ManualSectionSeeder extends Seeder
{
    /**
     * Starter content for the public user manual.
     *
     * Sections are matched on slug and only created when missing, so re-running
     * this seeder never overwrites wording an admin has since edited.
     */
    public function run(): void
    {
        foreach ($this->sections() as $i => $section) {
            ManualSection::firstOrCreate(
                ['slug' => $section['slug']],
                [...$section, 'sort_order' => $i + 1, 'is_published' => true],
            );
        }
    }

    private function sections(): array
    {
        return [
            [
                'slug'    => 'getting-started',
                'title'   => 'Getting started',
                'summary' => 'Create your account and find your way around.',
                'body'    => <<<'MD'
                CloudOne Accounting is double-entry accounting software built for Zambian businesses. Every invoice, bill and payment you record posts the correct journal entries behind the scenes, so your reports are always in balance.

                ## Create your account

                1. Go to the home page and choose **Start free trial**.
                2. Enter your name, email address and a password.
                3. Verify your email address using the link we send you.
                4. Set up your company (see the next section).

                ## Your free trial

                Every new company starts on a **14-day free trial** with all features unlocked — including the ones normally reserved for the Business plan. No card is needed to start.

                Your remaining trial days are shown at the bottom of the sidebar. When the trial ends you'll be asked to choose a plan before you can carry on using the app. Nothing you entered during the trial is lost.

                ## Finding your way around

                The sidebar on the left is your main menu. It only lists the features included in your current plan, so it may be shorter or longer depending on what you're subscribed to.
                MD,
            ],
            [
                'slug'    => 'setting-up-your-company',
                'title'   => 'Setting up your company',
                'summary' => 'Company details, TPIN and what gets created for you automatically.',
                'body'    => <<<'MD'
                After registering you'll be asked to set up your company. This only takes a minute.

                ## What to enter

                | Field | Notes |
                | --- | --- |
                | Company name | Appears on your invoices and reports |
                | TPIN | Your ZRA Taxpayer Identification Number |
                | VAT number | Only if you are VAT-registered |
                | Address, city, country | Printed on invoices |
                | Currency | Defaults to ZMW (Zambian Kwacha) |
                | Financial year end | Used to date your reports |
                | Invoice prefix | The letters before each invoice number |

                ## What we create for you

                As soon as your company is saved, we set up:

                - A **Zambian chart of accounts** — assets, liabilities, equity, income and expenses, ready to use.
                - **Standard tax rates** — Standard VAT (16%), Zero-Rated VAT (0%), and withholding tax rates for dividends (15%), services (15%), non-residents (20%) and rent (10%).
                - Your **14-day free trial**.

                You can add to or adjust the chart of accounts at any time under **Accounts**.

                ## Changing your details later

                Go to **Settings → Company** to update anything, including uploading your logo so it appears on printed invoices.
                MD,
            ],
            [
                'slug'    => 'contacts',
                'title'   => 'Contacts',
                'summary' => 'Customers and suppliers in one place.',
                'body'    => <<<'MD'
                Contacts are the customers you invoice and the suppliers you buy from. A single contact can be both.

                ## Adding a contact

                1. Go to **Contacts → New contact**.
                2. Enter the name and, if you have them, the TPIN, email address and phone number.
                3. Save.

                Adding an email address is worth doing — it lets you email invoices straight from the app.

                ## Viewing history

                Open any contact to see their full transaction history and what they currently owe you (or you owe them).
                MD,
            ],
            [
                'slug'    => 'invoices',
                'title'   => 'Invoices',
                'summary' => 'Bill your customers and track what you are owed.',
                'body'    => <<<'MD'
                ## Creating an invoice

                1. Go to **Invoices → New invoice**.
                2. Choose the customer.
                3. Set the issue date and due date.
                4. Add a line for each item: description, quantity, unit price and tax rate.
                5. Save.

                VAT is worked out per line from the tax rate you pick, so you can mix standard-rated and zero-rated items on the same invoice. The totals update as you type.

                ## Sending and printing

                From an open invoice you can:

                - **Send** — mark it as sent, which moves it out of draft.
                - **Email** — send it to the customer's email address directly.
                - **Print** — open a clean printable version to save as PDF or print.

                ## Correcting a mistake

                Draft invoices can be edited freely. Once an invoice has been sent you should **void** it rather than delete it, which keeps your audit trail intact and reverses the journal entries. Then raise a fresh invoice.

                ## Getting paid

                Don't mark an invoice as paid by editing it. Record the money you received under **Payments** and allocate it to the invoice — see [Payments and receipts](#payments-and-receipts).
                MD,
            ],
            [
                'slug'    => 'payments-and-receipts',
                'title'   => 'Payments and receipts',
                'summary' => 'Record money in and out, and match it to invoices and bills.',
                'body'    => <<<'MD'
                ## Recording a payment

                1. Go to **Payments → New payment**.
                2. Choose whether you're recording money **received** from a customer or **paid** to a supplier.
                3. Pick the contact, the date, the amount and the account it went through (for example your bank account).
                4. Save.

                ## Allocating to invoices

                A payment on its own doesn't settle anything until it's allocated. After saving, use **Allocate** to apply the payment against one or more open invoices or bills.

                You can:

                - Settle an invoice in full.
                - Part-pay an invoice — the remainder stays outstanding.
                - Spread one payment across several invoices, which is handy when a customer pays a lump sum for a few invoices at once.

                Anything you don't allocate stays as a credit on the contact and can be allocated later.
                MD,
            ],
            [
                'slug'    => 'bills-and-suppliers',
                'title'   => 'Bills and suppliers',
                'summary' => 'Track what you owe. Available on Growth and Business.',
                'body'    => <<<'MD'
                > Bills are included in the **Growth** and **Business** plans.

                Bills record what your suppliers charge you, so you can see your accounts payable and reclaim input VAT.

                ## Entering a bill

                1. Go to **Bills → New bill**.
                2. Choose the supplier and enter their invoice number as the reference.
                3. Set the bill date and the due date.
                4. Add a line per item with the tax rate, so input VAT is captured.
                5. Save.

                ## Approving

                New bills start as drafts. **Approve** a bill once you've checked it — this posts it to your accounts and makes it payable. Approved bills appear in Aged Payables and in your VAT return.

                ## Paying a bill

                Record the payment under **Payments**, choosing money paid out, then allocate it to the bill.

                ## Voiding

                If a bill was entered in error, **void** it. As with invoices, this reverses the accounting rather than hiding the history.
                MD,
            ],
            [
                'slug'    => 'recurring-invoices',
                'title'   => 'Recurring invoices',
                'summary' => 'Invoice regular customers automatically. Available on Growth and Business.',
                'body'    => <<<'MD'
                > Recurring invoices are included in the **Growth** and **Business** plans.

                If you bill the same customer the same amount every month, set up a recurring invoice once and let it run.

                ## Setting one up

                1. Go to **Recurring → New**.
                2. Choose the customer and add the lines exactly as you would on a normal invoice.
                3. Set how often it repeats and when it should start.
                4. Set the payment terms — how many days after issue the invoice falls due.
                5. Save.

                ## How it runs

                Invoices are generated automatically on schedule and issued as sent. You can also press **Run now** to generate the next invoice immediately — useful for testing your setup or billing early.

                Each generated invoice is a normal invoice: edit, email, print or void it like any other.
                MD,
            ],
            [
                'slug'    => 'chart-of-accounts',
                'title'   => 'Chart of accounts',
                'summary' => 'The list of accounts everything is recorded against.',
                'body'    => <<<'MD'
                Your chart of accounts is the backbone of your books. A Zambian default set is created for you when you set up your company, so you can start straight away.

                ## Account types

                - **Assets** — what you own: bank accounts, cash, money owed to you.
                - **Liabilities** — what you owe: suppliers, VAT payable, loans.
                - **Equity** — the owners' stake.
                - **Income** — what you earn.
                - **Expenses** — what you spend.

                ## Adding an account

                Go to **Accounts → New account**, give it a code and a name, and choose its type and category. Codes keep your reports in a sensible order, so leave gaps between them.

                ## A note on deleting

                You can't delete an account once it has transactions posted to it — that would break your history. Mark it inactive instead.
                MD,
            ],
            [
                'slug'    => 'journal-entries',
                'title'   => 'Journal entries',
                'summary' => 'Manual double-entry adjustments. Available on Business.',
                'body'    => <<<'MD'
                > Manual journals are included in the **Business** plan.

                Most of your bookkeeping happens automatically from invoices, bills and payments. Journals are for the rest: depreciation, accruals, opening balances and corrections from your accountant.

                ## Posting a journal

                1. Go to **Journal → New entry**.
                2. Set the date and a narration explaining what it's for.
                3. Add lines, entering a debit or a credit against each account.
                4. Check that total debits equal total credits — an unbalanced journal can't be posted.
                5. Save it as a draft, then **Post** it when you're happy.

                Drafts don't affect your reports. Posting is what commits the entry to your ledger.
                MD,
            ],
            [
                'slug'    => 'reports',
                'title'   => 'Reports',
                'summary' => 'See how the business is doing.',
                'body'    => <<<'MD'
                All reports are under **Reports**. Each can be printed, and most can be exported to CSV for Excel.

                ## Profit & Loss

                Your income less expenses over a period. Available on **every plan**, including Starter.

                ## Balance Sheet

                What you own and owe at a point in time. Included in **Growth** and **Business**.

                ## VAT Summary

                Output VAT charged on sales, less input VAT on purchases, for a chosen period — the figures you need for your ZRA VAT return. Included in **Growth** and **Business**.

                ## Aged Receivables and Aged Payables

                Who owes you and who you owe, bucketed by how overdue they are. Use Aged Receivables to prioritise your chasing. Included in **Growth** and **Business**.

                ## Choosing a period

                Set the date range at the top of each report. Reports read from posted transactions only, so a bill still sitting in draft won't appear until it's approved.
                MD,
            ],
            [
                'slug'    => 'vat-and-zra-smart-invoice',
                'title'   => 'VAT and ZRA Smart Invoice',
                'summary' => 'Zambian VAT, and sending invoices to ZRA via VSDC.',
                'body'    => <<<'MD'
                ## VAT

                Standard VAT of **16%** and a **0%** zero-rated option are set up for your company automatically. Choose the rate per invoice line, so a single invoice can carry a mix.

                When you're ready to file, the **VAT Summary** report (Growth and Business) gives you output VAT, input VAT and the net figure for the period.

                Withholding tax rates for dividends (15%), services (15%), non-residents (20%) and rent (10%) are also created for you.

                ## ZRA Smart Invoice (VSDC)

                > ZRA Smart Invoice is included in the **Business** plan.

                Smart Invoice sends your invoices electronically to ZRA through the Virtual Sales Data Controller (VSDC).

                ### Before you start

                You'll need your VSDC details from ZRA: the device URL, branch ID and device serial number. Enter these under **Settings**, then **Initialize** the device. You only do this once.

                ### Sending an invoice

                Open a saved invoice and choose **Submit to ZRA**. Once accepted, the ZRA details are stored against the invoice and appear on the printed copy.

                If a submission fails, the error from ZRA is shown so you can correct the invoice and try again. Bills from suppliers can be submitted the same way.

                If you get stuck with device setup, contact support — an administrator can check your VSDC configuration for you.
                MD,
            ],
            [
                'slug'    => 'employees-and-payroll',
                'title'   => 'Employees and payroll',
                'summary' => 'Pay your staff and produce payslips. Available on Business.',
                'body'    => <<<'MD'
                > Payroll is included in the **Business** plan.

                ## Adding employees

                Go to **Employees → New employee** and enter their details, including their NRC, their basic pay and any recurring allowances or deductions.

                ## Running a payroll

                1. Go to **Payroll → New payroll run**.
                2. Choose the pay period.
                3. Check the calculated figures for each employee.
                4. **Approve** the run to post it to your accounts.

                Approving is what creates the journal entries for wages, so review before you approve.

                ## Payslips

                Once a run is approved, print a payslip for any employee from within the run.
                MD,
            ],
            [
                'slug'    => 'team-and-users',
                'title'   => 'Your team',
                'summary' => 'Invite colleagues to your company.',
                'body'    => <<<'MD'
                ## Inviting someone

                Go to **Settings → Team** and send an invitation to your colleague's email address. They'll get a link to join your company. If they don't have an account yet, the link walks them through creating one.

                ## How many people can join

                Your plan sets the limit:

                | Plan | Users |
                | --- | --- |
                | Starter | 1 |
                | Growth | 3 |
                | Business | 10 |

                If you've hit your limit, upgrade your plan and the extra seats are available immediately.

                ## Removing someone

                Remove a team member from **Settings → Team**. Their work stays in your books — only their access is withdrawn.
                MD,
            ],
            [
                'slug'    => 'billing-and-subscription',
                'title'   => 'Billing and subscription',
                'summary' => 'Plans, paying, and renewing when your subscription ends.',
                'body'    => <<<'MD'
                ## The plans

                All prices are in Zambian Kwacha. Paying annually saves you roughly 17%.

                | Plan | Per month | Users | Includes |
                | --- | --- | --- | --- |
                | Starter | ZMW 199 | 1 | Invoices, contacts, payments, accounts, Profit & Loss |
                | Growth | ZMW 399 | 3 | Everything in Starter, plus bills, recurring invoices and the advanced reports |
                | Business | ZMW 799 | 10 | Everything in Growth, plus journals, payroll and ZRA Smart Invoice |

                ## Subscribing

                Go to **Billing** and choose a plan. You can pay in one of two ways.

                ### Card or mobile money

                Pay online and your subscription activates as soon as the payment is confirmed.

                ### Bank transfer

                Prefer to pay by transfer? Choose the offline option, make the payment using the bank details shown, then upload your proof of payment. We verify it and activate your subscription, normally **within 24 hours**. You can watch its progress under **Billing** — it shows as *Payment under review* until it's approved.

                ## Renewing

                Your renewal date is shown under **Billing**. When a subscription ends, the app locks until it's renewed, but **none of your data is deleted**.

                To renew, go to **Billing** and press **Renew** on your plan — or pick a different plan if your needs have changed. Renewing works exactly like subscribing the first time.

                ## Changing plan

                Upgrade or downgrade at any time from **Billing**. Upgrades take effect immediately, so extra features and seats are there straight away.
                MD,
            ],
            [
                'slug'    => 'getting-help',
                'title'   => 'Getting help',
                'summary' => 'When you are stuck.',
                'body'    => <<<'MD'
                If something in this manual doesn't match what you see, or you're stuck on something it doesn't cover, email us at **support@cloudone.co.zm**.

                It helps us if you include:

                - What you were trying to do.
                - What happened instead.
                - The invoice, bill or payment number involved, if there is one.
                - A screenshot, if you can take one.
                MD,
            ],
        ];
    }
}
