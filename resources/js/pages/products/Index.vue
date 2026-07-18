<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Trash2, Pencil, Eye, AlertTriangle } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface Product {
    id: number;
    sku: string | null;
    name: string;
    type: 'inventory' | 'service' | 'non_inventory';
    sales_price: string;
    quantity_on_hand: string;
    average_cost: string;
    reorder_point: string | null;
    is_active: boolean;
}

const props = defineProps<{
    products: { data: Product[]; total: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
    currentType: string;
    search: string;
    lowStock: boolean;
    counts: Record<string, number>;
}>();

const searchVal = ref(props.search);

let debounce: ReturnType<typeof setTimeout>;
function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/products', { type: props.currentType, search: searchVal.value, low_stock: props.lowStock ? 1 : undefined }, { preserveState: true, replace: true });
    }, 350);
}

const tabs = [
    { key: 'all',       label: 'All' },
    { key: 'inventory', label: 'Inventory' },
    { key: 'service',   label: 'Services' },
];

const typeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    inventory: 'default',
    service:   'secondary',
    non_inventory: 'outline',
};

function fmt(v: string | number) {
    return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

function isLow(p: Product) {
    return p.type === 'inventory' && p.reorder_point !== null && Number(p.quantity_on_hand) <= Number(p.reorder_point);
}

function destroy(id: number, name: string) {
    if (confirm(`Delete product "${name}"? Items with stock history will be deactivated instead.`)) {
        router.delete(`/products/${id}`);
    }
}
</script>

<template>
    <Head title="Products" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Products &amp; Services</h1>
                <Button as-child>
                    <Link href="/products/create"><Plus class="mr-2 h-4 w-4" /> New Product</Link>
                </Button>
            </div>

            <!-- Tabs + low-stock filter -->
            <div class="flex gap-2 flex-wrap">
                <Button
                    v-for="tab in tabs" :key="tab.key"
                    :variant="currentType === tab.key && !lowStock ? 'default' : 'outline'"
                    size="sm"
                    @click="router.get('/products', { type: tab.key, search: searchVal })"
                >
                    {{ tab.label }}
                    <span class="ml-1 text-xs opacity-70">({{ counts[tab.key] ?? 0 }})</span>
                </Button>
                <Button
                    :variant="lowStock ? 'default' : 'outline'" size="sm"
                    class="ml-auto"
                    @click="router.get('/products', { low_stock: 1 })"
                >
                    <AlertTriangle class="mr-1 h-3.5 w-3.5" /> Low stock
                    <span class="ml-1 text-xs opacity-70">({{ counts.low_stock ?? 0 }})</span>
                </Button>
            </div>

            <Card>
                <CardContent class="pt-4">
                    <div class="relative mb-4 max-w-sm">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input v-model="searchVal" placeholder="Search by name or SKU…" class="pl-9" @input="onSearch" />
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>SKU</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead class="text-right">On hand</TableHead>
                                <TableHead class="text-right">Avg cost</TableHead>
                                <TableHead class="text-right">Sales price</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="p in props.products.data" :key="p.id">
                                <TableCell class="font-medium">
                                    <Link :href="`/products/${p.id}`" class="hover:underline">{{ p.name }}</Link>
                                    <Badge v-if="isLow(p)" variant="destructive" class="ml-2 text-[10px] py-0">Low</Badge>
                                    <Badge v-if="!p.is_active" variant="outline" class="ml-2 text-[10px] py-0">Inactive</Badge>
                                </TableCell>
                                <TableCell class="font-mono text-xs text-muted-foreground">{{ p.sku ?? '—' }}</TableCell>
                                <TableCell><Badge :variant="typeVariant[p.type]" class="capitalize">{{ p.type.replace('_', ' ') }}</Badge></TableCell>
                                <TableCell class="text-right">{{ p.type === 'inventory' ? Number(p.quantity_on_hand) : '—' }}</TableCell>
                                <TableCell class="text-right text-muted-foreground">{{ p.type === 'inventory' ? fmt(p.average_cost) : '—' }}</TableCell>
                                <TableCell class="text-right">{{ fmt(p.sales_price) }}</TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button variant="ghost" size="icon" as-child>
                                            <Link :href="`/products/${p.id}`" title="View"><Eye class="h-4 w-4" /></Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" as-child>
                                            <Link :href="`/products/${p.id}/edit`" title="Edit"><Pencil class="h-4 w-4" /></Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" class="text-destructive hover:text-destructive" title="Delete" @click="destroy(p.id, p.name)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!props.products.data?.length">
                                <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                                    No products found.
                                    <Link href="/products/create" class="ml-1 underline">Add one</Link>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div v-if="props.products.last_page > 1" class="flex justify-center gap-1 mt-4">
                        <Button v-for="link in props.products.links" :key="link.label"
                            :variant="link.active ? 'default' : 'outline'" size="sm"
                            :disabled="!link.url"
                            @click="link.url && router.get(link.url)"
                            v-html="link.label" />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
