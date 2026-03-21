<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, BookOpen, Clock, FileText, TrendingUp } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import * as reports from '@/routes/reports';

const reportCards = [
    {
        title: 'Profit & Loss',
        description: 'Revenue, expenses and net profit for a date range.',
        icon: TrendingUp,
        href: reports.profitLoss.url(),
    },
    {
        title: 'Balance Sheet',
        description: 'Assets, liabilities and equity at a point in time.',
        icon: BookOpen,
        href: reports.balanceSheet.url(),
    },
    {
        title: 'VAT Summary',
        description: 'Output vs input VAT for ZRA quarterly filing.',
        icon: FileText,
        href: reports.vatSummary.url(),
    },
    {
        title: 'Aged Receivables',
        description: 'Outstanding customer invoices grouped by age.',
        icon: Clock,
        href: reports.agedReceivables.url(),
    },
    {
        title: 'Aged Payables',
        description: 'Outstanding supplier bills grouped by age.',
        icon: BarChart3,
        href: reports.agedPayables.url(),
    },
];
</script>

<template>
    <Head title="Reports" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-2xl font-bold">Reports</h1>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="r in reportCards" :key="r.href" :href="r.href" class="block group">
                    <Card class="h-full transition-shadow group-hover:shadow-md">
                        <CardHeader class="flex flex-row items-start gap-4 pb-3">
                            <div class="rounded-md bg-primary/10 p-2.5">
                                <component :is="r.icon" class="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <CardTitle class="text-base">{{ r.title }}</CardTitle>
                                <CardDescription class="mt-1 text-sm">{{ r.description }}</CardDescription>
                            </div>
                        </CardHeader>
                    </Card>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
