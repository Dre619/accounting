<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface Movement {
    id: number; date: string; product: string; sku: string | null; type: string;
    quantity: number; unit_cost: number; value: number; balance: number; note: string | null;
}
interface ProductRef { id: number; name: string }

const props = defineProps<{
    movements: Movement[];
    filters: { from: string; to: string; product_id: number | null; type: string };
    products: ProductRef[];
    totals: { qty_in: number; qty_out: number; value_in: number; value_out: number };
}>();

const filters = reactive({ ...props.filters });

const typeOptions = [
    { key: 'all', label: 'All types' },
    { key: 'purchase', label: 'Purchase' },
    { key: 'sale', label: 'Sale' },
    { key: 'adjustment', label: 'Adjustment' },
    { key: 'opening', label: 'Opening' },
    { key: 'return', label: 'Return' },
];

const typeLabel: Record<string, string> = {
    purchase: 'Purchase', sale: 'Sale', adjustment: 'Adjustment',
    opening: 'Opening', transfer: 'Transfer', return: 'Return',
};

function apply() {
    router.get('/reports/stock-movements', {
        from: filters.from, to: filters.to,
        product_id: filters.product_id || undefined,
        type: filters.type,
    }, { preserveState: true, replace: true });
}

function exportCsv() {
    const q = new URLSearchParams({
        from: filters.from, to: filters.to, type: filters.type,
        ...(filters.product_id ? { product_id: String(filters.product_id) } : {}),
    });
    window.open(`/reports/stock-movements/csv?${q.toString()}`, '_blank');
}

function fmt(v: number, dp = 2) {
    return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: dp, maximumFractionDigits: dp });
}
</script>

<template>
    <Head title="Stock Movements" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto w-full">

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Stock Movements</h1>
                <Button variant="outline" @click="exportCsv"><Download class="mr-2 h-4 w-4" /> Export CSV</Button>
            </div>

            <!-- Filters -->
            <Card>
                <CardContent class="pt-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
                    <div class="space-y-1">
                        <Label for="from">From</Label>
                        <Input id="from" v-model="filters.from" type="date" />
                    </div>
                    <div class="space-y-1">
                        <Label for="to">To</Label>
                        <Input id="to" v-model="filters.to" type="date" />
                    </div>
                    <div class="space-y-1">
                        <Label>Product</Label>
                        <select v-model="filters.product_id"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option :value="null">All products</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label>Type</Label>
                        <select v-model="filters.type"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                            <option v-for="t in typeOptions" :key="t.key" :value="t.key">{{ t.label }}</option>
                        </select>
                    </div>
                    <Button @click="apply">Apply</Button>
                </CardContent>
            </Card>

            <!-- Totals -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <Card><CardContent class="pt-5"><p class="text-xs text-muted-foreground">Qty in</p><p class="text-xl font-bold text-emerald-600">+{{ totals.qty_in }}</p></CardContent></Card>
                <Card><CardContent class="pt-5"><p class="text-xs text-muted-foreground">Qty out</p><p class="text-xl font-bold text-destructive">{{ totals.qty_out }}</p></CardContent></Card>
                <Card><CardContent class="pt-5"><p class="text-xs text-muted-foreground">Value in</p><p class="text-xl font-bold">{{ fmt(totals.value_in) }}</p></CardContent></Card>
                <Card><CardContent class="pt-5"><p class="text-xs text-muted-foreground">Value out</p><p class="text-xl font-bold">{{ fmt(totals.value_out) }}</p></CardContent></Card>
            </div>

            <Card>
                <CardContent class="pt-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Product</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Note</TableHead>
                                <TableHead class="text-right">Qty</TableHead>
                                <TableHead class="text-right">Unit cost</TableHead>
                                <TableHead class="text-right">Balance</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="m in movements" :key="m.id">
                                <TableCell class="text-muted-foreground whitespace-nowrap">{{ m.date }}</TableCell>
                                <TableCell class="font-medium">{{ m.product }}</TableCell>
                                <TableCell><Badge variant="outline">{{ typeLabel[m.type] ?? m.type }}</Badge></TableCell>
                                <TableCell class="text-muted-foreground text-sm">{{ m.note ?? '—' }}</TableCell>
                                <TableCell class="text-right" :class="m.quantity < 0 ? 'text-destructive' : 'text-emerald-600'">
                                    {{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}
                                </TableCell>
                                <TableCell class="text-right text-muted-foreground">{{ fmt(m.unit_cost, 4) }}</TableCell>
                                <TableCell class="text-right font-medium">{{ m.balance }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!movements.length">
                                <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                                    No stock movements in this period.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
