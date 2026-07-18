<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface Row {
    id: number; sku: string | null; name: string;
    quantity: number; average_cost: number; value: number;
}

const props = defineProps<{
    rows: Row[];
    totalValue: number;
    glBalance: number | null;
    variance: number | null;
    generatedAt: string;
    company: Record<string, string | null>;
}>();

function fmt(v: number, dp = 2) {
    return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: dp, maximumFractionDigits: dp });
}

const reconciled = props.variance !== null && Math.abs(props.variance) < 0.005;
</script>

<template>
    <Head title="Inventory Valuation" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto w-full">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Inventory Valuation</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ company.name }} · Stock on hand at weighted-average cost, as of {{ generatedAt }}
                    </p>
                </div>
                <Button variant="outline" @click="router.get('/reports/inventory-valuation/csv')">
                    <Download class="mr-2 h-4 w-4" /> Export CSV
                </Button>
            </div>

            <Card>
                <CardContent class="pt-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>SKU</TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead class="text-right">Quantity</TableHead>
                                <TableHead class="text-right">Avg cost</TableHead>
                                <TableHead class="text-right">Value</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="r in rows" :key="r.id">
                                <TableCell class="font-mono text-xs text-muted-foreground">{{ r.sku ?? '—' }}</TableCell>
                                <TableCell class="font-medium">{{ r.name }}</TableCell>
                                <TableCell class="text-right">{{ Number(r.quantity) }}</TableCell>
                                <TableCell class="text-right text-muted-foreground">{{ fmt(r.average_cost, 4) }}</TableCell>
                                <TableCell class="text-right font-medium">{{ fmt(r.value) }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!rows.length">
                                <TableCell colspan="5" class="py-10 text-center text-muted-foreground">
                                    No inventory products to value.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div class="flex justify-end pt-4">
                        <dl class="w-72 space-y-1 text-sm">
                            <div class="flex justify-between border-t pt-2 font-bold text-base">
                                <dt>Total stock value</dt>
                                <dd>ZMW {{ fmt(totalValue) }}</dd>
                            </div>
                            <template v-if="glBalance !== null">
                                <div class="flex justify-between text-muted-foreground">
                                    <dt>GL Inventory (1300)</dt>
                                    <dd>ZMW {{ fmt(glBalance) }}</dd>
                                </div>
                                <div class="flex justify-between" :class="reconciled ? 'text-emerald-600' : 'text-destructive'">
                                    <dt>{{ reconciled ? 'Reconciled' : 'Variance' }}</dt>
                                    <dd>ZMW {{ fmt(variance ?? 0) }}</dd>
                                </div>
                            </template>
                        </dl>
                    </div>

                    <p v-if="glBalance !== null && !reconciled" class="text-xs text-muted-foreground pt-2">
                        A non-zero variance usually means stock was received or issued without a posted document
                        (e.g. opening stock entered without a cost), or a manual journal touched account 1300.
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
