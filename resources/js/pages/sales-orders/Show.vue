<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Send, Ban, FileText, Check, Printer } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface Item {
    id: number; description: string; quantity: string; unit_price: string; discount_percent: string;
    subtotal: string; tax_amount: string; total: string; quantity_invoiced: string;
    product: { id: number; name: string; type: string } | null;
    account: { id: number; code: string; name: string } | null;
}
interface LinkedInvoice { id: number; invoice_number: string | null; status: string; total: string }
interface Order {
    id: number; order_number: string; status: string; reference: string | null;
    order_date: string; valid_until: string | null; notes: string | null;
    subtotal: string; tax_amount: string; discount_amount: string; total: string;
    contact: { id: number; name: string; email: string | null } | null;
    items: Item[]; invoices: LinkedInvoice[]; created_by: { name: string } | null;
}

const props = defineProps<{ order: Order; company: Record<string, string | null> }>();

const canEdit    = ['draft', 'sent', 'accepted'].includes(props.order.status);
const canSend    = props.order.status === 'draft';
const canAccept  = ['draft', 'sent'].includes(props.order.status);
const canConvert = !['invoiced', 'cancelled'].includes(props.order.status);
const canCancel  = !['invoiced', 'cancelled'].includes(props.order.status);

const statusVariant: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    draft: 'outline', sent: 'secondary', accepted: 'secondary', partial: 'secondary',
    invoiced: 'default', cancelled: 'destructive',
};

function fmt(v: string | number) {
    return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

function outstanding(it: Item) {
    return Math.max(0, Number(it.quantity) - Number(it.quantity_invoiced));
}
const hasOutstanding = props.order.items.some(it => outstanding(it) > 0);

const showConvert = ref(false);
const lines = reactive<Record<number, number>>(
    Object.fromEntries(props.order.items.map(it => [it.id, outstanding(it)])),
);

function send()   { router.post(`/sales-orders/${props.order.id}/send`); }
function accept() { router.post(`/sales-orders/${props.order.id}/accept`); }
function cancel() { if (confirm('Cancel this sales order?')) router.post(`/sales-orders/${props.order.id}/cancel`); }

function submitConvert() {
    router.post(`/sales-orders/${props.order.id}/convert`, { lines }, {
        onSuccess: () => { showConvert.value = false; },
    });
}
</script>

<template>
    <Head :title="order.order_number" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto w-full">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        {{ order.order_number }}
                        <Badge :variant="statusVariant[order.status]" class="capitalize">{{ order.status }}</Badge>
                    </h1>
                    <p class="text-sm text-muted-foreground">{{ order.contact?.name }} · Ordered {{ order.order_date }}</p>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <Button variant="outline" as-child>
                        <a :href="`/sales-orders/${order.id}/print`" target="_blank"><Printer class="mr-2 h-4 w-4" /> PDF</a>
                    </Button>
                    <Button v-if="canEdit" variant="outline" as-child>
                        <Link :href="`/sales-orders/${order.id}/edit`"><Pencil class="mr-2 h-4 w-4" /> Edit</Link>
                    </Button>
                    <Button v-if="canSend" variant="outline" @click="send"><Send class="mr-2 h-4 w-4" /> Mark sent</Button>
                    <Button v-if="canAccept" variant="outline" @click="accept"><Check class="mr-2 h-4 w-4" /> Accept</Button>
                    <Button v-if="canCancel" variant="outline" class="text-destructive hover:text-destructive" @click="cancel"><Ban class="mr-2 h-4 w-4" /> Cancel</Button>
                    <Button v-if="canConvert && hasOutstanding" @click="showConvert = !showConvert"><FileText class="mr-2 h-4 w-4" /> Convert to Invoice</Button>
                </div>
            </div>

            <!-- Convert panel: choose how much of each line to invoice now -->
            <Card v-if="showConvert && canConvert">
                <CardHeader><CardTitle>Invoice quantities</CardTitle></CardHeader>
                <CardContent>
                    <form class="space-y-3" @submit.prevent="submitConvert">
                        <p class="text-sm text-muted-foreground">
                            Enter how much of each line to invoice now. Defaults to the outstanding balance;
                            reduce a quantity to invoice in stages. Sending the resulting invoice recognises revenue and issues stock.
                        </p>
                        <div v-for="it in order.items" :key="it.id"
                            class="grid grid-cols-[1fr_120px] items-center gap-3 border-b pb-2 last:border-0">
                            <div>
                                <div class="font-medium text-sm">{{ it.description }}</div>
                                <div class="text-xs text-muted-foreground">
                                    Ordered {{ Number(it.quantity) }} · Invoiced {{ Number(it.quantity_invoiced) }} ·
                                    Outstanding {{ outstanding(it) }}
                                </div>
                            </div>
                            <Input v-model.number="lines[it.id]" type="number" min="0" :max="outstanding(it)"
                                step="0.001" class="text-right" :disabled="outstanding(it) <= 0" />
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <Button type="button" variant="outline" @click="showConvert = false">Cancel</Button>
                            <Button type="submit">Create Invoice</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card v-if="order.invoices.length">
                <CardContent class="pt-4 flex items-center gap-3 text-sm">
                    <span class="text-muted-foreground">Invoiced on:</span>
                    <Link v-for="inv in order.invoices" :key="inv.id" :href="`/invoices/${inv.id}`"
                        class="inline-flex items-center gap-1 underline hover:no-underline">
                        {{ inv.invoice_number || `Invoice #${inv.id}` }}
                        <Badge variant="outline" class="capitalize text-[10px] py-0">{{ inv.status }}</Badge>
                    </Link>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Items</CardTitle></CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Description</TableHead>
                                <TableHead>Account</TableHead>
                                <TableHead class="text-right">Qty</TableHead>
                                <TableHead class="text-right">Invoiced</TableHead>
                                <TableHead class="text-right">Unit price</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="it in order.items" :key="it.id">
                                <TableCell class="font-medium">
                                    {{ it.description }}
                                    <Badge v-if="it.product?.type === 'inventory'" variant="outline" class="ml-1 text-[10px] py-0">stock</Badge>
                                </TableCell>
                                <TableCell class="text-muted-foreground text-sm">{{ it.account ? `${it.account.code} — ${it.account.name}` : '—' }}</TableCell>
                                <TableCell class="text-right">{{ Number(it.quantity) }}</TableCell>
                                <TableCell class="text-right text-muted-foreground">{{ Number(it.quantity_invoiced) }}</TableCell>
                                <TableCell class="text-right">{{ fmt(it.unit_price) }}</TableCell>
                                <TableCell class="text-right font-medium">{{ fmt(it.total) }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <div class="flex justify-end pt-4">
                        <dl class="w-64 space-y-1 text-sm">
                            <div class="flex justify-between"><dt class="text-muted-foreground">Subtotal</dt><dd class="font-medium">ZMW {{ fmt(order.subtotal) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-muted-foreground">VAT</dt><dd class="font-medium">ZMW {{ fmt(order.tax_amount) }}</dd></div>
                            <div v-if="Number(order.discount_amount) > 0" class="flex justify-between"><dt class="text-muted-foreground">Discount</dt><dd>− ZMW {{ fmt(order.discount_amount) }}</dd></div>
                            <div class="flex justify-between border-t pt-2 font-bold text-base"><dt>Total</dt><dd>ZMW {{ fmt(order.total) }}</dd></div>
                        </dl>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="order.notes">
                <CardContent class="pt-4">
                    <p class="text-sm text-muted-foreground whitespace-pre-line">{{ order.notes }}</p>
                </CardContent>
            </Card>

            <div>
                <Button variant="ghost" @click="router.get('/sales-orders')">← Back to sales orders</Button>
            </div>
        </div>
    </AppLayout>
</template>
