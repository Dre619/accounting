<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface Order {
    id: number;
    po_number: string;
    status: string;
    order_date: string;
    expected_date: string | null;
    total: string;
    contact: { id: number; name: string } | null;
}

const props = defineProps<{
    orders: { data: Order[]; total: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
    currentStatus: string;
    search: string;
    counts: Record<string, number>;
}>();

const searchVal = ref(props.search);

let debounce: ReturnType<typeof setTimeout>;
function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/purchase-orders', { status: props.currentStatus, search: searchVal.value }, { preserveState: true, replace: true });
    }, 350);
}

const tabs = [
    { key: 'all',       label: 'All' },
    { key: 'draft',     label: 'Draft' },
    { key: 'sent',      label: 'Sent' },
    { key: 'partial',   label: 'Partial' },
    { key: 'billed',    label: 'Billed' },
    { key: 'cancelled', label: 'Cancelled' },
];

const statusVariant: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    draft: 'outline', sent: 'secondary', partial: 'secondary',
    received: 'default', billed: 'default', cancelled: 'destructive',
};

function fmt(v: string | number) {
    return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Purchase Orders" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Purchase Orders</h1>
                <Button as-child>
                    <Link href="/purchase-orders/create"><Plus class="mr-2 h-4 w-4" /> New Order</Link>
                </Button>
            </div>

            <div class="flex gap-2 flex-wrap">
                <Button
                    v-for="tab in tabs" :key="tab.key"
                    :variant="currentStatus === tab.key ? 'default' : 'outline'"
                    size="sm"
                    @click="router.get('/purchase-orders', { status: tab.key, search: searchVal })"
                >
                    {{ tab.label }}
                    <span class="ml-1 text-xs opacity-70">({{ tab.key === 'all' ? orders.total : (counts[tab.key] ?? 0) }})</span>
                </Button>
            </div>

            <Card>
                <CardContent class="pt-4">
                    <div class="relative mb-4 max-w-sm">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input v-model="searchVal" placeholder="Search by number or supplier…" class="pl-9" @input="onSearch" />
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Number</TableHead>
                                <TableHead>Supplier</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Order date</TableHead>
                                <TableHead>Expected</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="o in props.orders.data" :key="o.id">
                                <TableCell class="font-medium">
                                    <Link :href="`/purchase-orders/${o.id}`" class="hover:underline">{{ o.po_number }}</Link>
                                </TableCell>
                                <TableCell>{{ o.contact?.name ?? '—' }}</TableCell>
                                <TableCell><Badge :variant="statusVariant[o.status]" class="capitalize">{{ o.status }}</Badge></TableCell>
                                <TableCell class="text-muted-foreground">{{ o.order_date }}</TableCell>
                                <TableCell class="text-muted-foreground">{{ o.expected_date ?? '—' }}</TableCell>
                                <TableCell class="text-right font-medium">ZMW {{ fmt(o.total) }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!props.orders.data?.length">
                                <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                                    No purchase orders found.
                                    <Link href="/purchase-orders/create" class="ml-1 underline">Create one</Link>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div v-if="props.orders.last_page > 1" class="flex justify-center gap-1 mt-4">
                        <Button v-for="link in props.orders.links" :key="link.label"
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
