<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, Plus, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as paymentRoutes from '@/routes/payments';

interface Payment {
    id: number; type: 'receipt'|'payment'; payment_number: string;
    payment_date: string; amount: string; method: string;
    reference: string|null;
    contact: { name: string }|null;
    deposit_account: { code: string; name: string };
}

const props = defineProps<{
    payments: { data: Payment[]; total: number; last_page: number; links: { url: string|null; label: string; active: boolean }[] };
    currentType: string;
    counts: Record<string, number>;
}>();

const methodLabel: Record<string, string> = {
    cash: 'Cash', bank_transfer: 'Bank Transfer', cheque: 'Cheque',
    airtel_money: 'Airtel Money', mtn_money: 'MTN Money',
    zamtel_money: 'Zamtel Money', other: 'Other',
};

function fmt(v: string|number) { return 'ZMW ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 }); }

function destroy(id: number, num: string) {
    if (confirm(`Delete ${num}? This will reverse all journal entries.`)) {
        router.delete(paymentRoutes.destroy.url(id));
    }
}
</script>

<template>
    <Head title="Payments" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Payments</h1>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="paymentRoutes.create.url({ query: { type: 'payment' } })">
                            <ArrowUpCircle class="mr-2 h-4 w-4 text-red-500" /> Record Payment
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="paymentRoutes.create.url({ query: { type: 'receipt' } })">
                            <ArrowDownCircle class="mr-2 h-4 w-4" /> Record Receipt
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="flex gap-2">
                <Button v-for="tab in [{ key:'all', label:'All' }, { key:'receipt', label:'Receipts' }, { key:'payment', label:'Payments' }]" :key="tab.key"
                    :variant="currentType === tab.key ? 'default' : 'outline'" size="sm"
                    @click="router.get(paymentRoutes.index.url(), { type: tab.key })">
                    {{ tab.label }}
                    <span class="ml-1 text-xs opacity-70">({{ counts[tab.key] ?? 0 }})</span>
                </Button>
            </div>

            <Card>
                <CardContent class="pt-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Reference #</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead>Method</TableHead>
                                <TableHead>Account</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="p in props.payments.data" :key="p.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="router.get(paymentRoutes.show.url(p.id))">
                                <TableCell class="font-mono font-semibold">{{ p.payment_number }}</TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1.5">
                                        <ArrowDownCircle v-if="p.type === 'receipt'" class="h-4 w-4 text-green-500" />
                                        <ArrowUpCircle   v-else class="h-4 w-4 text-red-500" />
                                        <span class="capitalize text-sm">{{ p.type }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-muted-foreground text-sm">{{ new Date(p.payment_date).toLocaleDateString() }}</TableCell>
                                <TableCell>{{ p.contact?.name ?? '—' }}</TableCell>
                                <TableCell class="text-sm text-muted-foreground">{{ methodLabel[p.method] ?? p.method }}</TableCell>
                                <TableCell class="text-sm text-muted-foreground">{{ p.deposit_account?.name ?? '—' }}</TableCell>
                                <TableCell class="text-right font-semibold" :class="p.type === 'receipt' ? 'text-green-600' : 'text-red-600'">
                                    {{ p.type === 'receipt' ? '+' : '−' }} {{ fmt(p.amount) }}
                                </TableCell>
                                <TableCell @click.stop>
                                    <Button variant="ghost" size="icon" class="text-destructive hover:text-destructive" @click="destroy(p.id, p.payment_number)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!props.payments.data?.length">
                                <TableCell colspan="8" class="py-10 text-center text-muted-foreground">No payments recorded yet.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div v-if="props.payments.last_page > 1" class="flex justify-center gap-1 mt-4">
                        <Button v-for="link in props.payments.links" :key="link.label"
                            :variant="link.active ? 'default' : 'outline'" size="sm"
                            :disabled="!link.url" @click="link.url && router.get(link.url)" v-html="link.label" />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
