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
    assets: { current: AccountLine[]; fixed: AccountLine[]; other: AccountLine[] };
    liabilities: { current: AccountLine[]; long_term: AccountLine[] };
    equity: AccountLine[];
    retainedEarnings: number;
    totalAssets: number;
    totalLiabilities: number;
    totalEquity: number;
    asOf: string;
    company: { name: string; currency: string };
}>();

const asOf = ref(props.asOf);

function applyFilter() {
    router.get(reports.balanceSheet.url({ query: { as_of: asOf.value } }));
}

const printUrl  = computed(() => `/reports/balance-sheet/print?as_of=${asOf.value}`);
const exportUrl = computed(() => `/reports/balance-sheet/csv?as_of=${asOf.value}`);

function fmt(v: number) {
    return (props.company.currency ?? 'ZMW') + ' ' + v.toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

function sectionTotal(rows: AccountLine[]) {
    return rows.reduce((s, r) => s + r.balance, 0);
}
</script>

<template>
    <Head title="Balance Sheet" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Balance Sheet</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">{{ company.name }} &mdash; as at {{ new Date(asOf).toLocaleDateString('en-ZM', { dateStyle: 'long' }) }}</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="date" v-model="asOf"
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

            <!-- ASSETS -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Assets</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Account</TableHead>
                                <TableHead class="text-right">Balance</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <!-- Current Assets -->
                            <template v-if="assets.current.length">
                                <TableRow class="bg-muted/20">
                                    <TableCell colspan="3" class="py-1.5 text-xs font-semibold text-muted-foreground pl-4">Current Assets</TableCell>
                                </TableRow>
                                <TableRow v-for="row in assets.current" :key="row.code">
                                    <TableCell class="font-mono text-xs text-muted-foreground pl-8">{{ row.code }}</TableCell>
                                    <TableCell class="pl-8">{{ row.name }}</TableCell>
                                    <TableCell class="text-right">{{ fmt(row.balance) }}</TableCell>
                                </TableRow>
                                <TableRow class="border-t">
                                    <TableCell colspan="2" class="text-right text-sm font-medium text-muted-foreground">Subtotal</TableCell>
                                    <TableCell class="text-right font-semibold">{{ fmt(sectionTotal(assets.current)) }}</TableCell>
                                </TableRow>
                            </template>
                            <!-- Fixed Assets -->
                            <template v-if="assets.fixed.length">
                                <TableRow class="bg-muted/20">
                                    <TableCell colspan="3" class="py-1.5 text-xs font-semibold text-muted-foreground pl-4">Fixed Assets</TableCell>
                                </TableRow>
                                <TableRow v-for="row in assets.fixed" :key="row.code">
                                    <TableCell class="font-mono text-xs text-muted-foreground pl-8">{{ row.code }}</TableCell>
                                    <TableCell class="pl-8">{{ row.name }}</TableCell>
                                    <TableCell class="text-right">{{ fmt(row.balance) }}</TableCell>
                                </TableRow>
                                <TableRow class="border-t">
                                    <TableCell colspan="2" class="text-right text-sm font-medium text-muted-foreground">Subtotal</TableCell>
                                    <TableCell class="text-right font-semibold">{{ fmt(sectionTotal(assets.fixed)) }}</TableCell>
                                </TableRow>
                            </template>
                            <!-- Other Assets -->
                            <template v-if="assets.other.length">
                                <TableRow class="bg-muted/20">
                                    <TableCell colspan="3" class="py-1.5 text-xs font-semibold text-muted-foreground pl-4">Other Assets</TableCell>
                                </TableRow>
                                <TableRow v-for="row in assets.other" :key="row.code">
                                    <TableCell class="font-mono text-xs text-muted-foreground pl-8">{{ row.code }}</TableCell>
                                    <TableCell class="pl-8">{{ row.name }}</TableCell>
                                    <TableCell class="text-right">{{ fmt(row.balance) }}</TableCell>
                                </TableRow>
                                <TableRow class="border-t">
                                    <TableCell colspan="2" class="text-right text-sm font-medium text-muted-foreground">Subtotal</TableCell>
                                    <TableCell class="text-right font-semibold">{{ fmt(sectionTotal(assets.other)) }}</TableCell>
                                </TableRow>
                            </template>
                            <TableRow class="bg-muted/40">
                                <TableCell colspan="2" class="text-right font-bold">Total Assets</TableCell>
                                <TableCell class="text-right font-bold">{{ fmt(totalAssets) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- LIABILITIES -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Liabilities</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Account</TableHead>
                                <TableHead class="text-right">Balance</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="liabilities.current.length">
                                <TableRow class="bg-muted/20">
                                    <TableCell colspan="3" class="py-1.5 text-xs font-semibold text-muted-foreground pl-4">Current Liabilities</TableCell>
                                </TableRow>
                                <TableRow v-for="row in liabilities.current" :key="row.code">
                                    <TableCell class="font-mono text-xs text-muted-foreground pl-8">{{ row.code }}</TableCell>
                                    <TableCell class="pl-8">{{ row.name }}</TableCell>
                                    <TableCell class="text-right">{{ fmt(row.balance) }}</TableCell>
                                </TableRow>
                                <TableRow class="border-t">
                                    <TableCell colspan="2" class="text-right text-sm font-medium text-muted-foreground">Subtotal</TableCell>
                                    <TableCell class="text-right font-semibold">{{ fmt(sectionTotal(liabilities.current)) }}</TableCell>
                                </TableRow>
                            </template>
                            <template v-if="liabilities.long_term.length">
                                <TableRow class="bg-muted/20">
                                    <TableCell colspan="3" class="py-1.5 text-xs font-semibold text-muted-foreground pl-4">Long-term Liabilities</TableCell>
                                </TableRow>
                                <TableRow v-for="row in liabilities.long_term" :key="row.code">
                                    <TableCell class="font-mono text-xs text-muted-foreground pl-8">{{ row.code }}</TableCell>
                                    <TableCell class="pl-8">{{ row.name }}</TableCell>
                                    <TableCell class="text-right">{{ fmt(row.balance) }}</TableCell>
                                </TableRow>
                                <TableRow class="border-t">
                                    <TableCell colspan="2" class="text-right text-sm font-medium text-muted-foreground">Subtotal</TableCell>
                                    <TableCell class="text-right font-semibold">{{ fmt(sectionTotal(liabilities.long_term)) }}</TableCell>
                                </TableRow>
                            </template>
                            <TableRow class="bg-muted/40">
                                <TableCell colspan="2" class="text-right font-bold">Total Liabilities</TableCell>
                                <TableCell class="text-right font-bold">{{ fmt(totalLiabilities) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- EQUITY -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Equity</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Account</TableHead>
                                <TableHead class="text-right">Balance</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in equity" :key="row.code">
                                <TableCell class="font-mono text-xs text-muted-foreground">{{ row.code }}</TableCell>
                                <TableCell>{{ row.name }}</TableCell>
                                <TableCell class="text-right">{{ fmt(row.balance) }}</TableCell>
                            </TableRow>
                            <TableRow>
                                <TableCell class="text-muted-foreground font-mono text-xs"></TableCell>
                                <TableCell class="italic text-muted-foreground">Retained Earnings</TableCell>
                                <TableCell class="text-right" :class="retainedEarnings >= 0 ? 'text-green-600' : 'text-red-600'">{{ fmt(retainedEarnings) }}</TableCell>
                            </TableRow>
                            <TableRow class="bg-muted/40">
                                <TableCell colspan="2" class="text-right font-bold">Total Equity</TableCell>
                                <TableCell class="text-right font-bold">{{ fmt(totalEquity) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Check -->
            <Card class="border-primary/30 bg-primary/5">
                <CardContent class="py-4 flex items-center justify-between">
                    <span class="font-semibold text-sm text-muted-foreground">Liabilities + Equity</span>
                    <span class="font-black text-xl">{{ fmt(totalLiabilities + totalEquity) }}</span>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
