<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, ArrowRight, FileText, TrendingUp, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as invoices from '@/routes/invoices';

interface Stats {
    receivables_total: number;
    receivables_count: number;
    overdue_total: number;
    overdue_count: number;
    revenue_this_month: number;
}
interface MonthRevenue { month: string; total: string }
interface RecentInvoice {
    id: number; invoice_number: string; status: string;
    total: string; amount_due: string; due_date: string;
    contact: { name: string };
}

const props = defineProps<{
    stats: Stats;
    invoiceCounts: Record<string, number>;
    revenueMonthly: MonthRevenue[];
    recentInvoices: RecentInvoice[];
    company: { name: string; currency: string };
}>();

const maxRevenue = computed(() => Math.max(...props.revenueMonthly.map(m => Number(m.total)), 1));

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    paid: 'default', sent: 'secondary', partial: 'secondary',
    overdue: 'destructive', draft: 'outline', void: 'outline',
};

function fmt(v: string | number) {
    return 'ZMW ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

function monthLabel(ym: string) {
    const [y, m] = ym.split('-');
    return new Date(Number(y), Number(m) - 1).toLocaleString('en', { month: 'short' });
}

function isOverdue(inv: RecentInvoice) {
    return ['sent', 'partial'].includes(inv.status) && new Date(inv.due_date) < new Date();
}
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Dashboard</h1>
                <Button as-child>
                    <Link :href="invoices.create.url()">
                        <FileText class="mr-2 h-4 w-4" /> New Invoice
                    </Link>
                </Button>
            </div>

            <!-- KPI cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">Revenue This Month</CardTitle>
                        <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ fmt(stats.revenue_this_month) }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Invoiced (excl. VAT)</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">Outstanding Receivables</CardTitle>
                        <Wallet class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold">{{ fmt(stats.receivables_total) }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ stats.receivables_count }} invoice{{ stats.receivables_count !== 1 ? 's' : '' }} unpaid
                        </p>
                    </CardContent>
                </Card>

                <Card :class="stats.overdue_count > 0 ? 'border-destructive/50' : ''">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-sm font-medium">Overdue</CardTitle>
                        <AlertCircle class="h-4 w-4" :class="stats.overdue_count > 0 ? 'text-destructive' : 'text-muted-foreground'" />
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold" :class="stats.overdue_count > 0 ? 'text-destructive' : ''">
                            {{ fmt(stats.overdue_total) }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ stats.overdue_count }} overdue invoice{{ stats.overdue_count !== 1 ? 's' : '' }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Revenue chart + invoice status -->
            <div class="grid gap-4 lg:grid-cols-3">

                <!-- Revenue bar chart -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-sm font-medium">Revenue — Last 6 Months</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="revenueMonthly.length" class="flex items-end gap-3 h-32">
                            <div v-for="m in revenueMonthly" :key="m.month"
                                class="flex flex-1 flex-col items-center gap-1">
                                <span class="text-[10px] text-muted-foreground">{{ fmt(m.total).replace('ZMW ','') }}</span>
                                <div class="w-full bg-primary/80 rounded-t-sm transition-all"
                                    :style="{ height: (Number(m.total) / maxRevenue * 90) + 'px' }" />
                                <span class="text-[10px] text-muted-foreground">{{ monthLabel(m.month) }}</span>
                            </div>
                        </div>
                        <p v-else class="py-10 text-center text-sm text-muted-foreground">No revenue data yet.</p>
                    </CardContent>
                </Card>

                <!-- Invoice status breakdown -->
                <Card>
                    <CardHeader><CardTitle class="text-sm font-medium">Invoice Status</CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <div v-for="(count, status) in invoiceCounts" :key="status"
                            class="flex items-center justify-between text-sm">
                            <Badge :variant="statusVariant[status as string] ?? 'outline'" class="capitalize">{{ status }}</Badge>
                            <span class="font-semibold">{{ count }}</span>
                        </div>
                        <div v-if="!Object.keys(invoiceCounts).length" class="text-center text-sm text-muted-foreground py-4">
                            No invoices yet.
                        </div>
                        <Button variant="outline" size="sm" class="w-full mt-2" as-child>
                            <Link :href="invoices.index.url()">View All <ArrowRight class="ml-2 h-4 w-4" /></Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <!-- Recent invoices -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Recent Invoices</CardTitle>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="invoices.index.url()">View all <ArrowRight class="ml-1 h-4 w-4" /></Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Invoice #</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                                <TableHead class="text-right">Due</TableHead>
                                <TableHead>Due Date</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="inv in recentInvoices" :key="inv.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="$inertia.visit(invoices.show.url(inv.id))">
                                <TableCell class="font-mono font-semibold">{{ inv.invoice_number }}</TableCell>
                                <TableCell>{{ inv.contact.name }}</TableCell>
                                <TableCell>
                                    <Badge :variant="isOverdue(inv) ? 'destructive' : (statusVariant[inv.status] ?? 'outline')" class="capitalize">
                                        {{ isOverdue(inv) ? 'Overdue' : inv.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">{{ fmt(inv.total) }}</TableCell>
                                <TableCell class="text-right" :class="Number(inv.amount_due) > 0 ? 'text-amber-600 font-medium' : 'text-muted-foreground'">
                                    {{ fmt(inv.amount_due) }}
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">{{ new Date(inv.due_date).toLocaleDateString() }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!recentInvoices.length">
                                <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                                    No invoices yet.
                                    <Link :href="invoices.create.url()" class="ml-1 underline">Create your first invoice</Link>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
