<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, CheckCircle2, FileText, BarChart3, Wallet, Users, BookMarked, Zap, ArrowRight, TrendingUp } from 'lucide-vue-next';
import { dashboard, login, register } from '@/routes';

withDefaults(
    defineProps<{ canRegister: boolean }>(),
    { canRegister: true },
);

const dashboardUrl = dashboard.url();
const loginUrl     = login.url();
const registerUrl  = register.url();

const features = [
    { icon: FileText,    title: 'Invoicing',        desc: 'Professional ZRA-compliant invoices with VAT calculated automatically on every line.' },
    { icon: BookOpen,    title: 'Bills & Expenses',  desc: 'Track supplier bills, input VAT and accounts payable — all in one place.' },
    { icon: Wallet,      title: 'Payments',          desc: 'Record receipts and payments, auto-allocate to open invoices with one click.' },
    { icon: Users,       title: 'Contacts',          desc: 'Manage customers and suppliers with full transaction history and outstanding balances.' },
    { icon: BarChart3,   title: 'Reports',           desc: 'P&L, Balance Sheet, VAT Summary and aged debtors — ready in seconds.' },
    { icon: BookMarked,  title: 'Double-Entry',      desc: 'Every transaction posts clean journal entries automatically. Always balanced.' },
];

const plans = [
    {
        name: 'Starter',
        price: '199',
        features: ['Up to 5 users', 'Invoices & Bills', 'Payments & Receipts', 'Basic Reports', 'Email support'],
    },
    {
        name: 'Growth',
        price: '399',
        popular: true,
        features: ['Up to 15 users', 'Everything in Starter', 'Advanced Reports', 'VAT Summary', 'Priority support'],
    },
    {
        name: 'Business',
        price: '799',
        features: ['Unlimited users', 'Everything in Growth', 'Multi-company', 'Dedicated account manager', '24/7 support'],
    },
];
</script>

<template>
    <Head title="CloudOne Accounting — Simple accounting for Zambian SMEs" />

    <div class="min-h-screen font-sans" style="background:#fff; color:#0f2044;">

        <!-- NAV -->
        <header style="background:#0f2044; border-bottom:1px solid #1a3060;">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center gap-2.5">
                    <div class="h-8 w-8 rounded-lg flex items-center justify-center" style="background:#f97316;">
                        <BookOpen class="h-4 w-4 text-white" />
                    </div>
                    <span class="font-bold text-lg text-white tracking-tight">CloudOne Accounting</span>
                </div>
                <!-- Links -->
                <nav class="flex items-center gap-3">
                    <Link v-if="$page.props.auth.user" :href="dashboardUrl"
                        class="rounded-lg px-5 py-2 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                        style="background:#f97316;">
                        Go to Dashboard
                    </Link>
                    <template v-else>
                        <Link :href="loginUrl"
                            class="text-sm font-medium text-blue-200 hover:text-white transition-colors px-3 py-2">
                            Log in
                        </Link>
                        <Link v-if="canRegister" :href="registerUrl"
                            class="rounded-lg px-5 py-2 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                            style="background:#f97316;">
                            Start free trial
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- HERO -->
        <section style="background:linear-gradient(135deg,#0f2044 0%,#1a3a6e 60%,#1e4080 100%);" class="text-white">
            <div class="max-w-6xl mx-auto px-6 py-28 text-center">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold mb-8"
                    style="background:rgba(249,115,22,0.15); color:#fb923c; border:1px solid rgba(249,115,22,0.3);">
                    <Zap class="h-3.5 w-3.5" /> 14-day free trial &mdash; no credit card required
                </div>
                <h1 class="text-5xl sm:text-6xl font-extrabold tracking-tight mb-6 leading-tight">
                    Accounting built for<br />
                    <span style="color:#fb923c;">Zambian businesses</span>
                </h1>
                <p class="text-xl text-blue-200 max-w-2xl mx-auto mb-10 leading-relaxed">
                    ZRA-compliant invoicing, double-entry bookkeeping, VAT tracking and financial reports —
                    all in one simple platform. In Kwacha, for Zambia.
                </p>
                <div class="flex items-center justify-center gap-4 flex-wrap">
                    <Link v-if="canRegister" :href="registerUrl"
                        class="inline-flex items-center gap-2 rounded-lg px-7 py-3.5 text-base font-bold text-white shadow-lg transition-opacity hover:opacity-90"
                        style="background:#f97316;">
                        Get started free <ArrowRight class="h-4 w-4" />
                    </Link>
                    <Link :href="loginUrl"
                        class="inline-flex items-center gap-2 rounded-lg px-7 py-3.5 text-base font-semibold text-white transition-colors hover:bg-white/10"
                        style="border:1px solid rgba(255,255,255,0.3);">
                        Sign in
                    </Link>
                </div>
                <p class="mt-5 text-sm text-blue-300">Prices in ZMW · Hosted securely · Made in Zambia</p>

                <!-- Stats row -->
                <div class="mt-16 grid grid-cols-3 gap-6 max-w-lg mx-auto">
                    <div class="text-center">
                        <div class="text-3xl font-extrabold" style="color:#fb923c;">14</div>
                        <div class="text-xs text-blue-300 mt-1">Day free trial</div>
                    </div>
                    <div class="text-center" style="border-left:1px solid rgba(255,255,255,0.15); border-right:1px solid rgba(255,255,255,0.15);">
                        <div class="text-3xl font-extrabold" style="color:#fb923c;">16%</div>
                        <div class="text-xs text-blue-300 mt-1">VAT auto-applied</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-extrabold" style="color:#fb923c;">ZMW</div>
                        <div class="text-xs text-blue-300 mt-1">Zambian Kwacha</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FEATURES -->
        <section class="py-24" style="background:#f8faff;">
            <div class="max-w-6xl mx-auto px-6">
                <div class="text-center mb-14">
                    <h2 class="text-3xl font-extrabold mb-3" style="color:#0f2044;">Everything you need to run your books</h2>
                    <p class="text-gray-500 max-w-xl mx-auto">Designed from the ground up for Zambian tax and accounting requirements.</p>
                </div>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="f in features" :key="f.title"
                        class="rounded-xl p-6 hover:shadow-md transition-all group"
                        style="background:#fff; border:1px solid #e2e8f0;">
                        <div class="inline-flex items-center justify-center rounded-lg p-3 mb-4 transition-colors group-hover:scale-110 duration-200"
                            style="background:#0f2044;">
                            <component :is="f.icon" class="h-5 w-5 text-white" />
                        </div>
                        <h3 class="font-bold text-base mb-1.5" style="color:#0f2044;">{{ f.title }}</h3>
                        <p class="text-sm text-gray-500 leading-relaxed">{{ f.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ZRA COMPLIANCE BANNER -->
        <section class="py-20" style="background:#0f2044;">
            <div class="max-w-6xl mx-auto px-6">
                <div class="rounded-2xl p-10 flex flex-col md:flex-row items-center gap-10"
                    style="background:#1a3a6e; border:1px solid #2a4a80;">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-4">
                            <TrendingUp class="h-5 w-5" style="color:#fb923c;" />
                            <span class="font-bold text-sm uppercase tracking-widest" style="color:#fb923c;">ZRA Compliant</span>
                        </div>
                        <h2 class="text-2xl font-extrabold text-white mb-5">Built for Zambia Revenue Authority compliance</h2>
                        <ul class="space-y-3 text-sm text-blue-200">
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="h-4 w-4 mt-0.5 shrink-0" style="color:#fb923c;" />
                                TPIN printed on invoices and supplier bills
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="h-4 w-4 mt-0.5 shrink-0" style="color:#fb923c;" />
                                16% VAT calculated automatically on every line item
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="h-4 w-4 mt-0.5 shrink-0" style="color:#fb923c;" />
                                Withholding tax (WHT 15%, 20%) support on payments
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="h-4 w-4 mt-0.5 shrink-0" style="color:#fb923c;" />
                                VAT Summary report ready for ZRA quarterly filing
                            </li>
                            <li class="flex items-start gap-2.5">
                                <CheckCircle2 class="h-4 w-4 mt-0.5 shrink-0" style="color:#fb923c;" />
                                All amounts in Zambian Kwacha (ZMW)
                            </li>
                        </ul>
                    </div>
                    <!-- Big accent number -->
                    <div class="shrink-0 text-center rounded-2xl px-12 py-8" style="background:#0f2044; border:1px solid #2a4a80;">
                        <div class="text-7xl font-black mb-1" style="color:#fb923c;">16%</div>
                        <div class="text-sm text-blue-300">Standard VAT rate<br />auto-applied</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- PRICING -->
        <section class="py-24" style="background:#f8faff;">
            <div class="max-w-6xl mx-auto px-6">
                <div class="text-center mb-14">
                    <h2 class="text-3xl font-extrabold mb-3" style="color:#0f2044;">Simple, transparent pricing</h2>
                    <p class="text-gray-500">All plans include a 14-day free trial. No credit card needed to start.</p>
                </div>
                <div class="grid gap-6 md:grid-cols-3">
                    <div v-for="plan in plans" :key="plan.name"
                        class="rounded-xl p-7 flex flex-col relative"
                        :style="plan.popular
                            ? 'background:#0f2044; border:2px solid #f97316; box-shadow:0 8px 32px rgba(249,115,22,0.2);'
                            : 'background:#fff; border:1px solid #e2e8f0;'">
                        <!-- Popular badge -->
                        <div v-if="plan.popular"
                            class="absolute -top-3.5 left-1/2 -translate-x-1/2 text-xs font-bold px-4 py-1 rounded-full text-white"
                            style="background:#f97316;">
                            Most Popular
                        </div>
                        <h3 class="text-xl font-extrabold mb-1" :style="plan.popular ? 'color:#fff' : 'color:#0f2044'">
                            {{ plan.name }}
                        </h3>
                        <div class="flex items-baseline gap-1 mb-5">
                            <span class="text-sm" :style="plan.popular ? 'color:#93c5fd' : 'color:#6b7280'">ZMW</span>
                            <span class="text-4xl font-black" :style="plan.popular ? 'color:#fb923c' : 'color:#0f2044'">{{ plan.price }}</span>
                            <span class="text-sm" :style="plan.popular ? 'color:#93c5fd' : 'color:#6b7280'">/month</span>
                        </div>
                        <ul class="space-y-2.5 text-sm mb-7 flex-1">
                            <li v-for="feat in plan.features" :key="feat" class="flex items-center gap-2"
                                :style="plan.popular ? 'color:#bfdbfe' : 'color:#374151'">
                                <CheckCircle2 class="h-4 w-4 shrink-0" style="color:#fb923c;" />
                                {{ feat }}
                            </li>
                        </ul>
                        <Link v-if="canRegister" :href="registerUrl"
                            class="block w-full text-center rounded-lg py-3 text-sm font-bold transition-opacity hover:opacity-90"
                            :style="plan.popular
                                ? 'background:#f97316; color:#fff;'
                                : 'background:#0f2044; color:#fff;'">
                            Start free trial
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="py-24 text-center text-white" style="background:linear-gradient(135deg,#0f2044,#1a3a6e);">
            <div class="max-w-2xl mx-auto px-6">
                <h2 class="text-3xl font-extrabold mb-4">Ready to take control of your finances?</h2>
                <p class="text-blue-200 mb-8 text-lg">
                    Join Zambian businesses using CloudOne Accounting.<br />Start your free 14-day trial today.
                </p>
                <Link v-if="canRegister" :href="registerUrl"
                    class="inline-flex items-center gap-2 rounded-lg px-8 py-4 text-base font-bold text-white shadow-lg transition-opacity hover:opacity-90"
                    style="background:#f97316;">
                    Create your free account <ArrowRight class="h-5 w-5" />
                </Link>
            </div>
        </section>

        <!-- FOOTER -->
        <footer style="background:#0a1628; border-top:1px solid #1a2a48;">
            <div class="max-w-6xl mx-auto px-6 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm" style="color:#64748b;">
                <div class="flex items-center gap-2">
                    <div class="h-5 w-5 rounded flex items-center justify-center" style="background:#f97316;">
                        <BookOpen class="h-3 w-3 text-white" />
                    </div>
                    <span class="text-blue-300 font-medium">CloudOne Accounting</span>
                </div>
                <p>© {{ new Date().getFullYear() }} CloudOne Technologies Ltd &middot; Lusaka, Zambia</p>
                <a href="mailto:support@cloudone.co.zm" class="text-blue-400 hover:text-blue-200 transition-colors">
                    support@cloudone.co.zm
                </a>
            </div>
        </footer>

    </div>
</template>
