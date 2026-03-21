<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as invoices from '@/routes/invoices';
import * as reports from '@/routes/reports';

interface AgedRow {
    id: number;
    number: string;
    contact: string;
    due_date: string;
    total: number;
    amount_due: number;
    days_overdue: number;
    bucket: string;
}

const props = defineProps<{
    rows: AgedRow[];
    totals: Record<string, number>;
    asOf: string;
    company: { name: string; currency: string };
}>();

const asOf = ref(props.asOf);
const buckets = ['current', '1-30', '31-60', '61-90', '90+'];
const grandTotal = computed(() => Object.values(props.totals).reduce((s, v) => s + v, 0));

function applyFilter() {
    router.get(reports.agedReceivables.url({ query: { as_of: asOf.value } }));
}

function fmt(v: number) {
    return (props.company.currency ?? 'ZMW') + ' ' + v.toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

function bucketVariant(bucket: string): 'default' | 'secondary' | 'outline' | 'destructive' {
    if (bucket === 'current') return 'default';
    if (bucket === '1-30')    return 'secondary';
    return 'destructive';
}
</script>

<template>
    <Head title="Aged Receivables" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Aged Receivables</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">{{ company.name }} — as at {{ new Date(asOf).toLocaleDateString('en-ZM', { dateStyle: 'long' }) }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="date" v-model="asOf"
                        class="border rounded-md px-3 py-1.5 text-sm bg-background" />
                    <Button size="sm" @click="applyFilter">Apply</Button>
                </div>
            </div>

            <!-- Bucket summary -->
            <div class="grid gap-3" :style="`grid-template-columns: repeat(${buckets.length}, 1fr);`">
                <Card v-for="b in buckets" :key="b" :class="['current', '1-30'].includes(b) ? '' : 'border-destructive/40'">
                    <CardHeader class="pb-1">
                        <CardTitle class="text-xs uppercase tracking-wide text-muted-foreground">{{ b === 'current' ? 'Current' : b + ' days' }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-lg font-bold" :class="['current', '1-30'].includes(b) ? '' : 'text-destructive'">
                            {{ fmt(totals[b] ?? 0) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Detail table -->
            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Invoice #</TableHead>
                                <TableHead>Customer</TableHead>
                                <TableHead>Due Date</TableHead>
                                <TableHead>Age</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                                <TableHead class="text-right">Outstanding</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in rows" :key="row.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="$inertia.visit(invoices.show.url(row.id))">
                                <TableCell class="font-mono font-semibold">{{ row.number }}</TableCell>
                                <TableCell>{{ row.contact }}</TableCell>
                                <TableCell class="text-sm text-muted-foreground">{{ new Date(row.due_date).toLocaleDateString() }}</TableCell>
                                <TableCell>
                                    <Badge :variant="bucketVariant(row.bucket)" class="whitespace-nowrap">
                                        {{ row.bucket === 'current' ? 'Current' : row.days_overdue + ' days' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">{{ fmt(row.total) }}</TableCell>
                                <TableCell class="text-right font-semibold" :class="row.bucket !== 'current' ? 'text-destructive' : ''">
                                    {{ fmt(row.amount_due) }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!rows.length">
                                <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                                    No outstanding receivables.
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="rows.length" class="bg-muted/40">
                                <TableCell colspan="5" class="text-right font-bold">Total Outstanding</TableCell>
                                <TableCell class="text-right font-bold">{{ fmt(grandTotal) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
