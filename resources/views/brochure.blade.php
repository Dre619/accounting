<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CloudOne Accounting — Platform Overview</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 15px;
            color: #111827;
            background: #f9fafb;
        }

        /* ── Layout ── */
        .page { max-width: 900px; margin: 0 auto; background: #fff; }

        /* ── Cover ── */
        .cover {
            background: linear-gradient(135deg, #0f2044 0%, #1a3a6e 100%);
            padding: 64px 48px;
            text-align: center;
            color: #fff;
        }
        .cover .logo-mark {
            display: inline-flex; align-items: center; justify-content: center;
            width: 72px; height: 72px; border-radius: 16px;
            background: rgba(249,115,22,.15); margin-bottom: 24px;
        }
        .cover .logo-mark svg { width: 40px; height: 40px; }
        .cover h1 { font-size: 42px; font-weight: 800; letter-spacing: -1px; }
        .cover h1 span { color: #f97316; }
        .cover .tagline { font-size: 18px; color: rgba(255,255,255,.75); margin-top: 12px; }
        .cover .pill {
            display: inline-block; margin-top: 28px; padding: 10px 28px;
            border: 2px solid #f97316; border-radius: 100px;
            color: #f97316; font-weight: 600; font-size: 14px; letter-spacing: .5px;
        }

        /* ── Print button ── */
        .print-bar {
            background: #f0f4fa; padding: 10px 48px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
        }
        .print-bar span { font-size: 13px; color: #6b7280; }
        .print-bar button {
            background: #0f2044; color: #fff; border: none; cursor: pointer;
            padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600;
        }
        .print-bar button:hover { background: #1a3a6e; }
        @media print { .print-bar { display: none; } body { background: #fff; } }

        /* ── Content wrapper ── */
        .content { padding: 48px 48px 64px; }

        /* ── Section headings ── */
        h2 {
            font-size: 22px; font-weight: 800; color: #0f2044;
            margin: 40px 0 14px;
            padding-bottom: 8px;
            border-bottom: 3px solid #f97316;
        }
        h3 {
            font-size: 16px; font-weight: 700; color: #0f2044;
            margin: 24px 0 8px;
        }
        h3 .icon { margin-right: 6px; }

        p { line-height: 1.7; color: #374151; margin-bottom: 10px; }

        /* ── Intro box ── */
        .intro-box {
            background: #f0f4fa; border-left: 4px solid #0f2044;
            padding: 20px 24px; border-radius: 0 8px 8px 0; margin-bottom: 20px;
        }
        .intro-box p { margin: 0; font-size: 15px; line-height: 1.8; }

        /* ── Who is it for ── */
        .who-grid {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 16px; margin-top: 16px;
        }
        .who-card {
            background: #f9fafb; border: 1px solid #e5e7eb;
            border-radius: 10px; padding: 18px 20px;
        }
        .who-card strong { color: #0f2044; display: block; margin-bottom: 4px; font-size: 15px; }
        .who-card span { color: #6b7280; font-size: 13.5px; line-height: 1.6; }

        /* ── Feature modules ── */
        .modules { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px; }
        .module {
            border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px;
            border-top: 3px solid #0f2044;
        }
        .module-title {
            font-weight: 700; font-size: 15px; color: #0f2044;
            margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
        }
        .module ul { padding-left: 18px; }
        .module li { font-size: 13.5px; color: #374151; line-height: 1.7; margin-bottom: 3px; }

        /* ── ZRA compliance highlight ── */
        .zra-box {
            background: linear-gradient(135deg, #0f2044, #1a3a6e);
            color: #fff; border-radius: 12px; padding: 28px 32px; margin-top: 16px;
        }
        .zra-box h3 { color: #f97316; margin-top: 0; font-size: 18px; }
        .zra-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
        .zra-item {
            background: rgba(255,255,255,.08); border-radius: 8px;
            padding: 12px 16px; font-size: 13.5px; color: rgba(255,255,255,.9); line-height: 1.6;
        }
        .zra-item strong { color: #f97316; display: block; margin-bottom: 3px; }

        /* ── Benefits table ── */
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th {
            background: #0f2044; color: #fff; text-align: left;
            padding: 12px 16px; font-size: 13px; font-weight: 700;
        }
        td { padding: 12px 16px; font-size: 13.5px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        tr:nth-child(even) td { background: #f9fafb; }
        td:first-child { font-weight: 700; color: #0f2044; width: 180px; }
        td.green { color: #166534; font-weight: 600; }
        td.red { color: #dc2626; }

        /* ── Pricing ── */
        .pricing-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 20px; }
        .plan {
            border: 2px solid #e5e7eb; border-radius: 12px; padding: 24px; text-align: center;
        }
        .plan.featured { border-color: #f97316; background: #fff7ed; }
        .plan .plan-name { font-size: 18px; font-weight: 800; color: #0f2044; }
        .plan .plan-price { font-size: 28px; font-weight: 800; color: #f97316; margin: 10px 0 4px; }
        .plan .plan-cycle { font-size: 12px; color: #6b7280; margin-bottom: 14px; }
        .plan .plan-tag {
            background: #f97316; color: #fff; font-size: 11px; font-weight: 700;
            padding: 3px 10px; border-radius: 100px; display: inline-block; margin-bottom: 12px;
        }
        .plan ul { list-style: none; text-align: left; }
        .plan li { font-size: 13px; color: #374151; padding: 4px 0; padding-left: 20px; position: relative; }
        .plan li::before { content: "✔"; color: #16a34a; position: absolute; left: 0; font-size: 12px; }

        /* ── Steps ── */
        .steps { display: flex; gap: 20px; margin-top: 16px; }
        .step { flex: 1; text-align: center; padding: 24px 16px; background: #f9fafb; border-radius: 12px; }
        .step .step-num {
            width: 40px; height: 40px; border-radius: 50%;
            background: #0f2044; color: #fff; font-weight: 800; font-size: 18px;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;
        }
        .step strong { display: block; color: #0f2044; margin-bottom: 6px; font-size: 14px; }
        .step span { font-size: 13px; color: #6b7280; line-height: 1.6; }

        /* ── CTA footer ── */
        .cta {
            background: linear-gradient(135deg, #0f2044, #1a3a6e);
            color: #fff; text-align: center; padding: 48px;
            border-radius: 12px; margin-top: 40px;
        }
        .cta h2 { color: #fff; border: none; font-size: 26px; margin: 0 0 10px; }
        .cta p { color: rgba(255,255,255,.75); font-size: 15px; max-width: 500px; margin: 0 auto 24px; }
        .cta .cta-btn {
            display: inline-block; background: #f97316; color: #fff;
            padding: 14px 36px; border-radius: 100px;
            font-weight: 700; font-size: 15px; text-decoration: none; margin: 4px 8px;
        }
        .cta .cta-btn.outline {
            background: transparent; border: 2px solid rgba(255,255,255,.4);
        }

        /* ── Trial banner ── */
        .trial-banner {
            background: #fff7ed; border: 2px solid #f97316;
            border-radius: 10px; padding: 16px 24px;
            text-align: center; margin: 24px 0;
        }
        .trial-banner strong { color: #f97316; font-size: 16px; }
        .trial-banner span { color: #374151; font-size: 14px; }
    </style>
</head>
<body>
<div class="page">

    <!-- Print bar -->
    <div class="print-bar">
        <span>CloudOne Accounting — Platform Overview</span>
        <button onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>

    <!-- Cover -->
    <div class="cover">
        <div class="logo-mark">
            <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M22 10.5C20.3 9.0 18.25 8.25 16 8.25C10.89 8.25 6.75 12.39 6.75 17.5C6.75 22.61 10.89 26.75 16 26.75C18.25 26.75 20.3 26.0 22 24.5" stroke="#F97316" stroke-width="2.8" stroke-linecap="round" fill="none"/>
                <line x1="25.5" y1="10" x2="25.5" y2="25" stroke="#F97316" stroke-width="2.8" stroke-linecap="round"/>
                <line x1="22.5" y1="13" x2="25.5" y2="10" stroke="#F97316" stroke-width="2.8" stroke-linecap="round"/>
            </svg>
        </div>
        <h1>CloudOne <span>Accounting</span></h1>
        <p class="tagline">Your Complete Business Finance Platform</p>
        <div class="pill">Built for Zambia &nbsp;·&nbsp; ZRA-Compliant &nbsp;·&nbsp; Always in the Cloud</div>
    </div>

    <div class="content">

        <!-- What is it -->
        <h2>What is CloudOne Accounting?</h2>
        <div class="intro-box">
            <p>CloudOne Accounting is an easy-to-use, cloud-based accounting and business management platform designed specifically for Zambian businesses. Whether you run a small shop, a growing company, or manage accounts for multiple clients — CloudOne gives you everything you need to run your finances professionally, without needing to be an accountant.</p>
        </div>
        <p>It works on any device with a browser — desktop, laptop, tablet, or phone. No installation required. Your data is always backed up and secure in the cloud.</p>

        <!-- Trial banner -->
        <div class="trial-banner">
            <strong>14-Day Free Trial — No Credit Card Required.</strong>
            <span> Start today and be fully set up within an hour.</span>
        </div>

        <!-- Who is it for -->
        <h2>Who Is It For?</h2>
        <div class="who-grid">
            <div class="who-card">
                <strong>🏪 Small & Medium Businesses</strong>
                <span>Retail shops, service providers, contractors, consultants, wholesalers.</span>
            </div>
            <div class="who-card">
                <strong>📒 Accountants & Bookkeepers</strong>
                <span>Manage multiple company accounts from one login. Invite clients and team members. Bill your clients directly.</span>
            </div>
            <div class="who-card">
                <strong>🌱 NGOs & Non-Profits</strong>
                <span>Track income and expenses. Generate audit-ready reports for donors and oversight bodies.</span>
            </div>
            <div class="who-card">
                <strong>🚀 Startups & Growing Companies</strong>
                <span>Start on the free trial, grow your plan as your team and transactions increase.</span>
            </div>
            <div class="who-card">
                <strong>👷 Payroll-Heavy Employers</strong>
                <span>Restaurants, schools, factories — automated PAYE, NAPSA, and NHIMA every month.</span>
            </div>
            <div class="who-card">
                <strong>🏢 Multi-Location Businesses</strong>
                <span>Manage multiple companies or branches from a single account with separate books.</span>
            </div>
        </div>

        <!-- What it can do -->
        <h2>What CloudOne Can Do For Your Business</h2>
        <div class="modules">
            <div class="module">
                <div class="module-title"><span>🧾</span> Invoicing</div>
                <ul>
                    <li>Create and send professional invoices in seconds</li>
                    <li>Track paid, overdue, and outstanding invoices</li>
                    <li>Send invoices directly by email</li>
                    <li>Set up recurring invoices (weekly / monthly / quarterly)</li>
                    <li>Your logo and bank details on every invoice</li>
                    <li>ZRA-compliant invoice formatting</li>
                </ul>
            </div>
            <div class="module">
                <div class="module-title"><span>📥</span> Bills & Supplier Payments</div>
                <ul>
                    <li>Record all bills from suppliers</li>
                    <li>Track what you owe and to whom</li>
                    <li>Mark bills as paid and keep full history</li>
                    <li>See total outstanding supplier balances</li>
                    <li>Attach notes and references to every bill</li>
                </ul>
            </div>
            <div class="module">
                <div class="module-title"><span>💰</span> Payments & Cash</div>
                <ul>
                    <li>Record cash, bank transfer, and cheque payments</li>
                    <li>Airtel Money, MTN Money, Zamtel Money supported</li>
                    <li>Accept advance payments, allocate to invoices later</li>
                    <li>Withholding tax (WHT) tracking built in</li>
                    <li>Full payment history with method and reference</li>
                </ul>
            </div>
            <div class="module">
                <div class="module-title"><span>👥</span> Contacts</div>
                <ul>
                    <li>Complete directory of customers and suppliers</li>
                    <li>Full transaction history per contact</li>
                    <li>Store TPIN, email, phone, and address</li>
                    <li>Segment as customer, supplier, or both</li>
                </ul>
            </div>
            <div class="module">
                <div class="module-title"><span>💼</span> Payroll</div>
                <ul>
                    <li>Add employees with salary and bank details</li>
                    <li>Run monthly payroll with one click</li>
                    <li>Automatic PAYE using current ZRA tax bands</li>
                    <li>Automatic NAPSA (5%+5%) and NHIMA (1%+1%)</li>
                    <li>Generate payslips automatically</li>
                    <li>Full payroll history for ZRA filing</li>
                </ul>
            </div>
            <div class="module">
                <div class="module-title"><span>📊</span> Accounting & Reports</div>
                <ul>
                    <li>Full double-entry bookkeeping — automatic</li>
                    <li>Chart of accounts pre-built for Zambian businesses</li>
                    <li>Profit & Loss, Balance Sheet, AR/AP reports</li>
                    <li>VAT tracking on all sales and purchases</li>
                    <li>Journal entries created automatically</li>
                </ul>
            </div>
            <div class="module">
                <div class="module-title"><span>🏢</span> Multi-Company & Teams</div>
                <ul>
                    <li>Manage multiple companies from one login</li>
                    <li>Invite accountants, managers, and staff</li>
                    <li>Role-based access: Admin, Member, Viewer</li>
                    <li>Every action is logged and auditable</li>
                </ul>
            </div>
            <div class="module">
                <div class="module-title"><span>💳</span> Subscriptions & Billing</div>
                <ul>
                    <li>Pay monthly or annually (save 2 months)</li>
                    <li>Pay by mobile money, bank transfer, or card</li>
                    <li>Upload proof of payment for bank transfers</li>
                    <li>Upgrade or downgrade anytime</li>
                </ul>
            </div>
        </div>

        <!-- ZRA Compliance -->
        <h2>ZRA Compliance — Built In</h2>
        <div class="zra-box">
            <h3>Always audit-ready. Always compliant.</h3>
            <p style="color:rgba(255,255,255,.75);font-size:14px;margin-top:4px;">CloudOne handles every Zambian tax requirement automatically so you never have to worry about getting it wrong.</p>
            <div class="zra-grid">
                <div class="zra-item"><strong>PAYE (Income Tax)</strong>Calculated automatically using the current ZRA progressive tax bands. Always up to date.</div>
                <div class="zra-item"><strong>NAPSA Contributions</strong>5% employee + 5% employer deducted every payroll run. Capped at the statutory maximum.</div>
                <div class="zra-item"><strong>NHIMA Contributions</strong>1% employee + 1% employer calculated and recorded on every payslip.</div>
                <div class="zra-item"><strong>VAT Tracking</strong>Output VAT on sales and input VAT on purchases tracked separately on every transaction.</div>
                <div class="zra-item"><strong>Withholding Tax (WHT)</strong>Applied and recorded on supplier payments where required.</div>
                <div class="zra-item"><strong>VSDC / Smart Invoice Ready</strong>Fields for ZRA's electronic invoicing system already built in for VAT-registered businesses.</div>
            </div>
        </div>

        <!-- Benefits -->
        <h2>Key Benefits at a Glance</h2>
        <table>
            <thead>
                <tr><th>Benefit</th><th>What It Means For You</th></tr>
            </thead>
            <tbody>
                <tr><td>Save Time</td><td>Invoices, payroll, and journals are automated. What used to take hours takes minutes.</td></tr>
                <tr><td>Save Money</td><td>No need to hire a full-time bookkeeper. One subscription covers everything.</td></tr>
                <tr><td>Avoid ZRA Penalties</td><td>Tax calculations are always correct. No more manual errors on PAYE, NAPSA, or VAT.</td></tr>
                <tr><td>Get Paid Faster</td><td>Professional invoices with your bank and mobile money details encourage on-time payment.</td></tr>
                <tr><td>Know Your Numbers</td><td>See profit, cash position, and outstanding invoices in real time — from anywhere.</td></tr>
                <tr><td>Work From Anywhere</td><td>Browser-based — use it on your phone, tablet, or laptop. No installation needed.</td></tr>
                <tr><td>Your Data is Safe</td><td>Cloud storage with automatic backups. No risk of losing records if your laptop is stolen.</td></tr>
                <tr><td>Grow With Confidence</td><td>Add users, companies, and upgrade your plan as your business grows.</td></tr>
            </tbody>
        </table>

        <!-- Comparison -->
        <h2>How We Compare</h2>
        <table>
            <thead>
                <tr><th>Feature</th><th>CloudOne</th><th>QuickBooks / Sage</th><th>Excel / Manual</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>ZRA PAYE compliance</td>
                    <td class="green">✔ Built in</td>
                    <td class="red">✘ Not localised</td>
                    <td class="red">✘ Manual calculation</td>
                </tr>
                <tr>
                    <td>NAPSA & NHIMA payroll</td>
                    <td class="green">✔ Automatic</td>
                    <td class="red">✘ Not included</td>
                    <td class="red">✘ Manual</td>
                </tr>
                <tr>
                    <td>Mobile money payments</td>
                    <td class="green">✔ Airtel/MTN/Zamtel</td>
                    <td class="red">✘ No</td>
                    <td class="red">✘ No</td>
                </tr>
                <tr>
                    <td>ZMW currency & local tax</td>
                    <td class="green">✔ Native</td>
                    <td style="color:#92400e;">⚠ Partial</td>
                    <td class="red">✘ No</td>
                </tr>
                <tr>
                    <td>Monthly price</td>
                    <td class="green">From K 250</td>
                    <td class="red">From K 2,000+</td>
                    <td>Free (but risky)</td>
                </tr>
                <tr>
                    <td>Setup required</td>
                    <td class="green">None — ready in minutes</td>
                    <td>Complex configuration</td>
                    <td>Build it yourself</td>
                </tr>
                <tr>
                    <td>Audit-ready records</td>
                    <td class="green">✔ Automatic</td>
                    <td class="green">✔ With setup</td>
                    <td class="red">✘ Spreadsheets only</td>
                </tr>
            </tbody>
        </table>

        <!-- Pricing -->
        <h2>Pricing Plans</h2>
        <div class="pricing-grid">
            <div class="plan">
                <div class="plan-name">Starter</div>
                <div class="plan-price">K 250</div>
                <div class="plan-cycle">per month</div>
                <ul>
                    <li>1 user</li>
                    <li>Invoicing & billing</li>
                    <li>Expense tracking</li>
                    <li>Basic reports</li>
                    <li>Email support</li>
                </ul>
            </div>
            <div class="plan featured">
                <div class="plan-tag">MOST POPULAR</div>
                <div class="plan-name">Business</div>
                <div class="plan-price">K 550</div>
                <div class="plan-cycle">per month</div>
                <ul>
                    <li>Up to 5 users</li>
                    <li>Everything in Starter</li>
                    <li>Full payroll (PAYE/NAPSA/NHIMA)</li>
                    <li>Team management</li>
                    <li>Advanced reports</li>
                    <li>Priority support</li>
                </ul>
            </div>
            <div class="plan">
                <div class="plan-name">Pro</div>
                <div class="plan-price">K 1,200</div>
                <div class="plan-cycle">per month</div>
                <ul>
                    <li>Unlimited users</li>
                    <li>Everything in Business</li>
                    <li>Multiple companies</li>
                    <li>VSDC / Smart Invoice</li>
                    <li>Accountant reseller access</li>
                    <li>Dedicated support</li>
                </ul>
            </div>
        </div>
        <p style="text-align:center;margin-top:14px;color:#6b7280;font-size:13px;">
            💡 Save 2 months by paying annually &nbsp;·&nbsp; 14-day free trial on all plans &nbsp;·&nbsp; No credit card required
        </p>

        <!-- Getting started -->
        <h2>Getting Started in 3 Steps</h2>
        <div class="steps">
            <div class="step">
                <div class="step-num">1</div>
                <strong>Sign Up Free</strong>
                <span>Create your account in under 2 minutes. No credit card needed. Your 14-day trial starts immediately.</span>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <strong>Set Up Your Company</strong>
                <span>Enter your company name, TPIN, and logo. The full chart of accounts is created automatically.</span>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <strong>Start Working</strong>
                <span>Create your first invoice, add employees, or record a payment. Most businesses are fully set up within one hour.</span>
            </div>
        </div>

        <!-- CTA -->
        <div class="cta">
            <h2>Ready to Get Started?</h2>
            <p>Join hundreds of Zambian businesses already managing their finances with CloudOne Accounting.</p>
            <a href="{{ route('register') }}" class="cta-btn">Start Free Trial</a>
            <a href="mailto:sales@accounting.cloudone.co.zm" class="cta-btn outline">Talk to Sales</a>
        </div>

    </div>
</div>
</body>
</html>
