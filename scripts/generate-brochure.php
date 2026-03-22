<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Table;

$word = new PhpWord();

// ── Global styles ────────────────────────────────────────────────────────────
$word->setDefaultFontName('Calibri');
$word->setDefaultFontSize(11);

$navy  = '0F2044';
$orange = 'F97316';
$white  = 'FFFFFF';
$light  = 'F0F4FA';
$grey   = '6B7280';
$dark   = '111827';

// Heading 1
$word->addTitleStyle(1, [
    'name'  => 'Calibri', 'size' => 26, 'bold' => true, 'color' => $white,
], ['shading' => ['fill' => $navy], 'space' => ['before' => 0, 'after' => 200]]);

// Heading 2
$word->addTitleStyle(2, [
    'name' => 'Calibri', 'size' => 14, 'bold' => true, 'color' => $navy,
], ['space' => ['before' => 300, 'after' => 80], 'borderBottom' => ['color' => $orange, 'size' => 8]]);

// Heading 3
$word->addTitleStyle(3, [
    'name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => $orange,
], ['space' => ['before' => 200, 'after' => 60]]);

// ── Section setup ────────────────────────────────────────────────────────────
$secStyle = ['marginLeft' => 1000, 'marginRight' => 1000, 'marginTop' => 800, 'marginBottom' => 800];
$section  = $word->addSection($secStyle);

// Helper: styled paragraph
$bodyStyle = ['name' => 'Calibri', 'size' => 11, 'color' => $dark];
$mutedStyle = ['name' => 'Calibri', 'size' => 10, 'color' => $grey];

// ── COVER ────────────────────────────────────────────────────────────────────
$coverPara = $section->addTextRun(['shading' => ['fill' => $navy], 'space' => ['before' => 600, 'after' => 600], 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$coverPara->addText("CloudOne Accounting\n", ['name' => 'Calibri', 'size' => 36, 'bold' => true, 'color' => $white]);
$coverPara->addText("Your Complete Business Finance Platform", ['name' => 'Calibri', 'size' => 16, 'color' => $orange]);

$section->addTextBreak(1);

$taglinePara = $section->addTextRun(['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
$taglinePara->addText(
    "Built for Zambian businesses. ZRA-compliant. Always up to date.",
    ['name' => 'Calibri', 'size' => 13, 'italic' => true, 'color' => $grey]
);

$section->addTextBreak(2);

// ── WHAT IS CLOUDONE ────────────────────────────────────────────────────────
$section->addTitle("What is CloudOne Accounting?", 2);
$section->addText(
    "CloudOne Accounting is an easy-to-use, cloud-based accounting and business management platform designed specifically for Zambian businesses. Whether you run a small shop, a growing company, or manage accounts for multiple clients, CloudOne gives you everything you need to run your finances professionally — without needing to be an accountant.",
    $bodyStyle,
    ['space' => ['after' => 160]]
);
$section->addText(
    "It works on any device with a browser — desktop, laptop, tablet, or phone. No installation required. Your data is always backed up and secure in the cloud.",
    $bodyStyle,
    ['space' => ['after' => 200]]
);

// ── WHO IS IT FOR ────────────────────────────────────────────────────────────
$section->addTitle("Who Is It For?", 2);

$bullets = [
    ["Small & Medium Businesses",    "Retail shops, service providers, contractors, consultants, wholesalers."],
    ["Accountants & Bookkeepers",    "Manage multiple company accounts from one login. Bill your clients. Invite team members."],
    ["NGOs & Non-Profits",           "Track income, expenses, and generate reports for donors and auditors."],
    ["Startups & Growing Companies", "Start free, grow your plan as your team and transactions increase."],
    ["Payroll-Heavy Employers",      "Restaurants, factories, schools — automated PAYE, NAPSA, and NHIMA calculations."],
];

foreach ($bullets as [$title, $desc]) {
    $run = $section->addTextRun(['space' => ['after' => 80]]);
    $run->addText("✔  $title: ", ['bold' => true, 'color' => $navy, 'size' => 11]);
    $run->addText($desc, $bodyStyle);
}

$section->addTextBreak(1);

// ── WHAT IT CAN DO ────────────────────────────────────────────────────────────
$section->addTitle("What CloudOne Can Do For Your Business", 2);
$section->addText(
    "CloudOne covers every part of your business finances in one place.",
    ['name' => 'Calibri', 'size' => 11, 'italic' => true, 'color' => $grey],
    ['space' => ['after' => 160]]
);

// Feature blocks
$features = [
    [
        "icon"    => "🧾",
        "title"   => "Invoicing",
        "bullets" => [
            "Create and send professional invoices to your customers in seconds.",
            "Add your company logo and banking details automatically.",
            "Track which invoices are paid, overdue, or still outstanding.",
            "Send invoices by email directly from the platform.",
            "Set up recurring invoices that generate automatically every week, month, or quarter.",
            "ZRA-compliant invoice formatting built in.",
        ],
    ],
    [
        "icon"    => "📥",
        "title"   => "Bills & Supplier Payments",
        "bullets" => [
            "Record bills from your suppliers and track what you owe.",
            "Mark bills as paid and keep a full history.",
            "See your total outstanding supplier balances at a glance.",
            "Attach notes and references to every bill.",
        ],
    ],
    [
        "icon"    => "💰",
        "title"   => "Payments & Cash Management",
        "bullets" => [
            "Record cash, bank transfer, cheque, Airtel Money, MTN Money, and Zamtel Money payments.",
            "Accept advance payments from customers and allocate them to invoices later.",
            "Full payment history with method, date, and reference for every transaction.",
            "Withholding tax (WHT) tracking built in.",
        ],
    ],
    [
        "icon"    => "👥",
        "title"   => "Contacts (Customers & Suppliers)",
        "bullets" => [
            "Keep a complete directory of all your customers and suppliers.",
            "See the full transaction history for any contact in one click.",
            "Store TPIN, email, phone, and address for each contact.",
            "Segment contacts as customer, supplier, or both.",
        ],
    ],
    [
        "icon"    => "💼",
        "title"   => "Payroll",
        "bullets" => [
            "Add all your employees with their salary, bank account, and employment details.",
            "Run monthly payroll with one click.",
            "Automatic PAYE calculation using the latest ZRA tax bands.",
            "Automatic NAPSA (5% employee + 5% employer) and NHIMA (1% + 1%) deductions.",
            "Generate payslips for every employee automatically.",
            "Full payroll run history and totals for filing returns.",
        ],
    ],
    [
        "icon"    => "📊",
        "title"   => "Accounting & Reports",
        "bullets" => [
            "Full double-entry bookkeeping happens automatically in the background.",
            "Chart of accounts pre-configured for Zambian businesses — no setup needed.",
            "Journal entries created automatically for every invoice, payment, and payroll run.",
            "Reports: Profit & Loss, Balance Sheet, Accounts Receivable, Accounts Payable.",
            "VAT tracking — output VAT on sales, input VAT on purchases.",
        ],
    ],
    [
        "icon"    => "🏢",
        "title"   => "Multi-Company & Team Management",
        "bullets" => [
            "Manage multiple companies from a single login.",
            "Invite team members (accountants, bookkeepers, managers) with role-based access.",
            "Set permissions: Admin, Member, or Viewer for each user.",
            "Every action is tracked — know who created, edited, or deleted records.",
        ],
    ],
    [
        "icon"    => "💳",
        "title"   => "Subscriptions & Billing",
        "bullets" => [
            "Pay monthly or annually (save 2 months with annual).",
            "Pay by bank transfer, mobile money, or card online via Lenco.",
            "Upload proof of payment for offline bank transfers.",
            "Upgrade or downgrade your plan anytime.",
        ],
    ],
];

foreach ($features as $f) {
    $section->addTitle("{$f['icon']}  {$f['title']}", 3);
    foreach ($f['bullets'] as $b) {
        $section->addListItem($b, 0, $bodyStyle, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
    }
    $section->addTextBreak(1);
}

// ── ZRA COMPLIANCE ────────────────────────────────────────────────────────────
$section->addTitle("ZRA Compliance — Built In", 2);
$section->addText(
    "CloudOne is built around Zambia Revenue Authority requirements so you are always audit-ready:",
    $bodyStyle,
    ['space' => ['after' => 100]]
);
$zra = [
    "PAYE calculated using the current ZRA progressive tax bands (K0–K4,800 at 0%, K4,801–K6,900 at 20%, etc.).",
    "NAPSA and NHIMA contributions calculated and recorded automatically every payroll run.",
    "WHT (Withholding Tax) tracked on every payment.",
    "Output VAT and input VAT recorded on all sales and purchases.",
    "VSDC / Smart Invoice integration fields ready for businesses required to use ZRA's electronic invoicing system.",
    "All financial records stored and exportable for audit submission.",
];
foreach ($zra as $item) {
    $section->addListItem($item, 0, $bodyStyle, ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED]);
}

$section->addTextBreak(1);

// ── KEY BENEFITS ────────────────────────────────────────────────────────────
$section->addTitle("Key Benefits at a Glance", 2);

$tableStyle = ['borderSize' => 6, 'borderColor' => 'DEE2E6', 'cellMargin' => 100, 'alignment' => JcTable::CENTER];
$word->addTableStyle('BenefitsTable', $tableStyle);
$table = $section->addTable('BenefitsTable');

$headerRow = $table->addRow(400);
foreach (["Benefit", "What It Means For You"] as $h) {
    $cell = $headerRow->addCell(4000, ['bgColor' => $navy]);
    $cell->addText($h, ['bold' => true, 'color' => $white, 'size' => 11]);
}

$benefitRows = [
    ["Save Time",              "Invoices, payroll, and journals are automated. What used to take hours takes minutes."],
    ["Save Money",             "No need to hire a full-time bookkeeper. One subscription covers everything."],
    ["Avoid ZRA Penalties",    "Tax calculations are always correct. No more manual errors on PAYE or VAT."],
    ["Get Paid Faster",        "Professional invoices with payment details encourage customers to pay on time."],
    ["Always Know Your Numbers","See profit, cash position, and outstanding invoices in real time — anywhere."],
    ["Grow With Confidence",   "Add users, companies, and plan tiers as your business grows."],
    ["Work From Anywhere",     "Browser-based — use it on your phone, tablet, or laptop, anywhere in Zambia."],
    ["Your Data is Safe",      "Cloud storage with automatic backups. No risk of losing records if your laptop is stolen."],
];

foreach ($benefitRows as $i => [$b, $d]) {
    $bg = $i % 2 === 0 ? $light : $white;
    $row = $table->addRow();
    $row->addCell(4000, ['bgColor' => str_replace('#', '', $bg)])->addText($b, ['bold' => true, 'size' => 11, 'color' => $navy]);
    $row->addCell(4000, ['bgColor' => str_replace('#', '', $bg)])->addText($d, ['size' => 11, 'color' => $dark]);
}

$section->addTextBreak(1);

// ── PRICING ────────────────────────────────────────────────────────────────
$section->addTitle("Pricing Plans", 2);
$section->addText(
    "All plans include a 14-day free trial. No credit card required to start.",
    ['name' => 'Calibri', 'size' => 11, 'bold' => true, 'color' => $orange],
    ['space' => ['after' => 160]]
);

$word->addTableStyle('PricingTable', $tableStyle);
$pt = $section->addTable('PricingTable');

$pRow = $pt->addRow(400);
foreach (["Plan", "Monthly Price", "Best For", "Key Limits"] as $h) {
    $pRow->addCell(2000, ['bgColor' => $navy])->addText($h, ['bold' => true, 'color' => $white, 'size' => 11]);
}

$plans = [
    ["Starter",  "K 250 / month",   "Freelancers, sole traders",       "1 user, core invoicing & expenses"],
    ["Business", "K 750 / month",   "Small businesses (2–5 staff)",    "5 users, payroll, full reports"],
    ["Pro",      "K 1,500 / month", "Growing companies & accountants", "Unlimited users, multi-company"],
];

foreach ($plans as $i => $row) {
    $bg = $i % 2 === 0 ? $light : $white;
    $r = $pt->addRow();
    foreach ($row as $j => $val) {
        $bold = $j === 0;
        $r->addCell(2000, ['bgColor' => str_replace('#', '', $bg)])
          ->addText($val, ['bold' => $bold, 'size' => 11, 'color' => $j === 1 ? $orange : $dark]);
    }
}

$section->addTextBreak(1);

// ── WHY CLOUDONE ────────────────────────────────────────────────────────────
$section->addTitle("Why Choose CloudOne Over Alternatives?", 2);

$word->addTableStyle('WhyTable', $tableStyle);
$wt = $section->addTable('WhyTable');
$wRow = $wt->addRow(400);
foreach (["Feature", "CloudOne", "QuickBooks / Sage", "Excel / Manual"] as $h) {
    $wRow->addCell(2000, ['bgColor' => $navy])->addText($h, ['bold' => true, 'color' => $white, 'size' => 11]);
}

$comparisons = [
    ["ZRA / PAYE compliance",        "✔ Built in",       "✘ Not localised",    "✘ Manual calculation"],
    ["NAPSA & NHIMA payroll",        "✔ Automatic",      "✘ Not included",     "✘ Manual"],
    ["Mobile money payment tracking","✔ Airtel/MTN/Zamtel","✘ No",             "✘ No"],
    ["ZMW currency & local tax",     "✔ Native",         "⚠ Partial",          "✘ No"],
    ["Cloud & mobile access",        "✔ Yes",            "✔ Yes (costly)",     "✘ Device-only"],
    ["Price (per month)",            "From K 250",       "From K 2,000+",      "Free but risky"],
    ["Setup required",               "None",             "Complex",            "Build yourself"],
    ["Audit-ready records",          "✔ Automatic",      "✔ Manual setup",     "✘ Spreadsheets"],
];

foreach ($comparisons as $i => $row) {
    $bg = $i % 2 === 0 ? $light : $white;
    $r = $wt->addRow();
    foreach ($row as $j => $val) {
        $color = $j === 1 ? '166534' : ($j > 0 && str_starts_with($val, '✘') ? 'DC2626' : $dark);
        $r->addCell(2000, ['bgColor' => str_replace('#', '', $bg)])
          ->addText($val, ['bold' => $j === 0, 'size' => 11, 'color' => $color]);
    }
}

$section->addTextBreak(1);

// ── GETTING STARTED ────────────────────────────────────────────────────────
$section->addTitle("Getting Started in 3 Steps", 2);

$steps = [
    ["Step 1: Sign Up Free",  "Visit the platform and create your account in under 2 minutes. No credit card needed. Your 14-day free trial starts immediately."],
    ["Step 2: Set Up Your Company", "Enter your company name, TPIN, logo, and bank details. The system creates your full chart of accounts automatically — nothing to configure."],
    ["Step 3: Start Working", "Create your first invoice, add your employees, or record a payment. Most businesses are fully set up and running within one hour."],
];

foreach ($steps as [$title, $desc]) {
    $run = $section->addTextRun(['space' => ['before' => 120, 'after' => 80]]);
    $run->addText("$title  —  ", ['bold' => true, 'color' => $navy, 'size' => 12]);
    $run->addText($desc, $bodyStyle);
}

$section->addTextBreak(1);

// ── FOOTER ────────────────────────────────────────────────────────────────
$footerRun = $section->addTextRun([
    'shading'   => ['fill' => $navy],
    'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
    'space'     => ['before' => 200, 'after' => 200],
]);
$footerRun->addText("CloudOne Accounting  |  ", ['color' => $white, 'size' => 10]);
$footerRun->addText("Built for Zambia. Trusted by businesses.", ['color' => $orange, 'size' => 10, 'italic' => true]);

// ── SAVE ─────────────────────────────────────────────────────────────────────
$outputPath = __DIR__ . '/../storage/app/CloudOne-Accounting-Overview.docx';
$writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Word2007');
$writer->save($outputPath);

echo "Document saved to: $outputPath\n";
