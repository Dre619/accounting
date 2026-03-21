<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileDown, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as reports from '@/routes/reports';

interface AccountLine {
    code: string;
    name: string;
    subtype: string | null;
    balance: number;
}

const props = defineProps<{
    income: AccountLine[];
    expenses: AccountLine[];
    totalIncome: number;
    totalExpenses: number;
    netProfit: number;
    from: string;
    to: string;
    company: { name: string; currency: string };
}>();

const from = ref(props.from);
const to   = ref(props.to);

function applyFilter() {
    router.get(reports.profitLoss.url({ query: { from: from.value, to: to.value } }));
}

const printUrl  = computed(() => `/reports/profit-loss/print?from=${from.value}&to=${to.value}`);
const exportUrl = computed(() => `/reports/profit-loss/csv?from=${from.value}&to=${to.value}`);

function fmt(v: number) {
    return (props.company.currency ?? 'ZMW') + ' ' + v.toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Profit & Loss" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Profit & Loss</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">{{ company.name }}</p>
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

            <!-- Income -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Income</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Account</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in income" :key="row.code">
                                <TableCell class="font-mono text-xs text-muted-foreground">{{ row.code }}</TableCell>
                                <TableCell>{{ row.name }}</TableCell>
                                <TableCell class="text-right font-medium">{{ fmt(row.balance) }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!income.length">
                                <TableCell colspan="3" class="py-6 text-center text-muted-foreground">No income recorded for this period.</TableCell>
                            </TableRow>
                            <TableRow class="bg-muted/40 font-bold">
                                <TableCell colspan="2" class="text-right font-bold">Total Income</TableCell>
                                <TableCell class="text-right font-bold text-green-600">{{ fmt(totalIncome) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Expenses -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Expenses</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Account</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in expenses" :key="row.code">
                                <TableCell class="font-mono text-xs text-muted-foreground">{{ row.code }}</TableCell>
                                <TableCell>{{ row.name }}</TableCell>
                                <TableCell class="text-right font-medium">{{ fmt(row.balance) }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!expenses.length">
                                <TableCell colspan="3" class="py-6 text-center text-muted-foreground">No expenses recorded for this period.</TableCell>
                            </TableRow>
                            <TableRow class="bg-muted/40">
                                <TableCell colspan="2" class="text-right font-bold">Total Expenses</TableCell>
                                <TableCell class="text-right font-bold text-red-600">{{ fmt(totalExpenses) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Net Profit summary -->
            <Card :class="netProfit >= 0 ? 'border-green-200 bg-green-50 dark:bg-green-950/20' : 'border-red-200 bg-red-50 dark:bg-red-950/20'">
                <CardContent class="py-4 flex items-center justify-between">
                    <span class="font-bold text-lg">Net Profit</span>
                    <span class="font-black text-2xl" :class="netProfit >= 0 ? 'text-green-600' : 'text-red-600'">
                        {{ fmt(netProfit) }}
                    </span>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
