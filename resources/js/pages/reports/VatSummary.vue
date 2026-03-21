<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileDown, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as reports from '@/routes/reports';

interface VatLine {
    id: number;
    invoice_number?: string;
    bill_number?: string;
    issue_date: string;
    subtotal: number;
    tax_amount: number;
    total: number;
}

const props = defineProps<{
    invoices: VatLine[];
    bills: VatLine[];
    outputVat: number;
    inputVat: number;
    vatPayable: number;
    from: string;
    to: string;
    company: { name: string; currency: string };
}>();

const from = ref(props.from);
const to   = ref(props.to);

function applyFilter() {
    router.get(reports.vatSummary.url({ query: { from: from.value, to: to.value } }));
}

const printUrl  = computed(() => `/reports/vat-summary/print?from=${from.value}&to=${to.value}`);
const exportUrl = computed(() => `/reports/vat-summary/csv?from=${from.value}&to=${to.value}`);

function fmt(v: number | string) {
    return (props.company.currency ?? 'ZMW') + ' ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="VAT Summary" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold">VAT Summary</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">{{ company.name }} — ZRA Filing Period</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="date" v-model="from"
                        class="border rounded-md px-3 py-1.5 text-sm bg-background" />
                    <span class="text-muted-foreground text-sm">to</span>
                    <input type="date" v-model="to"
                        class="border rounded-md px-3 py-1.5 text-sm bg-background" />
                    <Button size="sm" @click="applyFilter">Apply</Button>
                    <a :href="printUrl" target="_blank">
                        <Button size="sm" variant="outline"><Printer class="h-4 w-4 mr-1" />Print</Button>
                    </a>
                    <a :href="exportUrl">
                        <Button size="sm" variant="outline"><FileDown class="h-4 w-4 mr-1" />CSV</Button>
                    </a>
                </div>
            </div>

            <!-- Summary cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <Card>
                    <CardHeader class="pb-1">
                        <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground">Output VAT (Collected)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-green-600">{{ fmt(outputVat) }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">From {{ invoices.length }} invoice{{ invoices.length !== 1 ? 's' : '' }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-1">
                        <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground">Input VAT (Paid)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold text-amber-600">{{ fmt(inputVat) }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">From {{ bills.length }} bill{{ bills.length !== 1 ? 's' : '' }}</p>
                    </CardContent>
                </Card>
                <Card :class="vatPayable >= 0 ? 'border-red-200' : 'border-green-200'">
                    <CardHeader class="pb-1">
                        <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground">
                            {{ vatPayable >= 0 ? 'VAT Payable to ZRA' : 'VAT Refundable' }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-2xl font-bold" :class="vatPayable >= 0 ? 'text-red-600' : 'text-green-600'">
                            {{ fmt(Math.abs(vatPayable)) }}
                        </p>
                        <p class="text-xs text-muted-foreground mt-0.5">Output − Input VAT</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Output VAT table -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Output VAT — Sales</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Invoice #</TableHead>
                                <TableHead class="text-right">Net (excl. VAT)</TableHead>
                                <TableHead class="text-right">VAT 16%</TableHead>
                                <TableHead class="text-right">Gross</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="inv in invoices" :key="inv.id">
                                <TableCell>{{ new Date(inv.issue_date).toLocaleDateString() }}</TableCell>
                                <TableCell class="font-mono font-semibold">{{ inv.invoice_number }}</TableCell>
                                <TableCell class="text-right">{{ fmt(inv.subtotal) }}</TableCell>
                                <TableCell class="text-right text-green-600">{{ fmt(inv.tax_amount) }}</TableCell>
                                <TableCell class="text-right font-medium">{{ fmt(inv.total) }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!invoices.length">
                                <TableCell colspan="5" class="py-6 text-center text-muted-foreground">No sales invoices for this period.</TableCell>
                            </TableRow>
                            <TableRow class="bg-muted/40">
                                <TableCell colspan="3" class="text-right font-bold">Total Output VAT</TableCell>
                                <TableCell class="text-right font-bold text-green-600">{{ fmt(outputVat) }}</TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Input VAT table -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Input VAT — Purchases</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Bill #</TableHead>
                                <TableHead class="text-right">Net (excl. VAT)</TableHead>
                                <TableHead class="text-right">VAT 16%</TableHead>
                                <TableHead class="text-right">Gross</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="bill in bills" :key="bill.id">
                                <TableCell>{{ new Date(bill.issue_date).toLocaleDateString() }}</TableCell>
                                <TableCell class="font-mono font-semibold">{{ bill.bill_number ?? '—' }}</TableCell>
                                <TableCell class="text-right">{{ fmt(bill.subtotal) }}</TableCell>
                                <TableCell class="text-right text-amber-600">{{ fmt(bill.tax_amount) }}</TableCell>
                                <TableCell class="text-right font-medium">{{ fmt(bill.total) }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!bills.length">
                                <TableCell colspan="5" class="py-6 text-center text-muted-foreground">No supplier bills for this period.</TableCell>
                            </TableRow>
                            <TableRow class="bg-muted/40">
                                <TableCell colspan="3" class="text-right font-bold">Total Input VAT</TableCell>
                                <TableCell class="text-right font-bold text-amber-600">{{ fmt(inputVat) }}</TableCell>
                                <TableCell></TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
