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
import * as billRoutes from '@/routes/bills';

interface Bill {
    id: number; bill_number: string | null; status: string;
    total: string; amount_due: string; due_date: string; issue_date: string;
    contact: { id: number; name: string };
}

const props = defineProps<{
    bills: { data: Bill[]; total: number; last_page: number; links: { url: string|null; label: string; active: boolean }[] };
    currentStatus: string;
    search: string;
    counts: Record<string, number>;
}>();

const searchVal = ref(props.search);
let debounce: ReturnType<typeof setTimeout>;
function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(billRoutes.index.url(), { status: props.currentStatus, search: searchVal.value }, { preserveState: true, replace: true });
    }, 350);
}

const tabs = [
    { key: 'all',      label: 'All'      },
    { key: 'draft',    label: 'Draft'    },
    { key: 'approved', label: 'Approved' },
    { key: 'partial',  label: 'Partial'  },
    { key: 'paid',     label: 'Paid'     },
    { key: 'overdue',  label: 'Overdue'  },
];

const statusVariant: Record<string, 'default'|'secondary'|'destructive'|'outline'> = {
    paid: 'default', approved: 'secondary', partial: 'secondary',
    overdue: 'destructive', draft: 'outline', void: 'outline',
};

function fmt(v: string | number) {
    return 'ZMW ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

function isOverdue(b: Bill) {
    return ['approved','partial'].includes(b.status) && new Date(b.due_date) < new Date();
}
</script>

<template>
    <Head title="Bills" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Bills</h1>
                <Button as-child><Link :href="billRoutes.create.url()"><Plus class="mr-2 h-4 w-4" /> New Bill</Link></Button>
            </div>

            <div class="flex gap-2 flex-wrap">
                <Button v-for="tab in tabs" :key="tab.key"
                    :variant="currentStatus === tab.key ? 'default' : 'outline'" size="sm"
                    @click="router.get(billRoutes.index.url(), { status: tab.key, search: searchVal })">
                    {{ tab.label }}
                    <span v-if="counts[tab.key]" class="ml-1 text-xs opacity-70">({{ counts[tab.key] }})</span>
                </Button>
            </div>

            <Card>
                <CardContent class="pt-4">
                    <div class="relative mb-4 max-w-sm">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input v-model="searchVal" placeholder="Search bill # or supplier…" class="pl-9" @input="onSearch" />
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Bill #</TableHead>
                                <TableHead>Supplier</TableHead>
                                <TableHead>Issue Date</TableHead>
                                <TableHead>Due Date</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                                <TableHead class="text-right">Due</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="b in props.bills.data" :key="b.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="router.get(billRoutes.show.url(b.id))">
                                <TableCell class="font-mono font-semibold">{{ b.bill_number ?? '—' }}</TableCell>
                                <TableCell>{{ b.contact?.name ?? '—' }}</TableCell>
                                <TableCell class="text-muted-foreground text-sm">{{ new Date(b.issue_date).toLocaleDateString() }}</TableCell>
                                <TableCell class="text-sm" :class="isOverdue(b) ? 'text-destructive font-medium' : 'text-muted-foreground'">
                                    {{ new Date(b.due_date).toLocaleDateString() }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="isOverdue(b) ? 'destructive' : (statusVariant[b.status] ?? 'outline')" class="capitalize">
                                        {{ isOverdue(b) ? 'Overdue' : b.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right font-medium">{{ fmt(b.total) }}</TableCell>
                                <TableCell class="text-right" :class="Number(b.amount_due) > 0 ? 'text-amber-600 font-medium' : 'text-muted-foreground'">
                                    {{ fmt(b.amount_due) }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!props.bills.data?.length">
                                <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                                    No bills found. <Link :href="billRoutes.create.url()" class="ml-1 underline">Add one</Link>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div v-if="props.bills.last_page > 1" class="flex justify-center gap-1 mt-4">
                        <Button v-for="link in props.bills.links" :key="link.label"
                            :variant="link.active ? 'default' : 'outline'" size="sm"
                            :disabled="!link.url" @click="link.url && router.get(link.url)" v-html="link.label" />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
