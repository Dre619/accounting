<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import * as bills from '@/routes/bills';
import * as invoices from '@/routes/invoices';
import * as payments from '@/routes/payments';

interface AllocatableInvoice { id: number; invoice_number: string }
interface AllocatableBill   { id: number; bill_number: string | null }

interface Allocation {
    id: number;
    amount: string;
    allocatable_type: string;
    allocatable: AllocatableInvoice | AllocatableBill | null;
}

interface Payment {
    id: number;
    payment_number: string;
    type: 'receipt' | 'payment';
    payment_date: string;
    amount: string;
    withholding_tax_amount: string;
    method: string;
    reference: string | null;
    notes: string | null;
    contact: { id: number; name: string } | null;
    deposit_account: { code: string; name: string };
    allocations: Allocation[];
}

const props = defineProps<{ payment: Payment }>();

const methodLabel: Record<string, string> = {
    cash: 'Cash', bank_transfer: 'Bank Transfer', cheque: 'Cheque',
    airtel_money: 'Airtel Money', mtn_money: 'MTN Money',
    zamtel_money: 'Zamtel Money', other: 'Other',
};

function fmt(v: string | number) {
    return 'ZMW ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

function destroy() {
    if (confirm(`Delete ${props.payment.payment_number}? This will reverse all journal entries.`)) {
        router.delete(payments.destroy.url(props.payment.id));
    }
}
</script>

<template>
    <Head :title="`${payment.type === 'receipt' ? 'Receipt' : 'Payment'} ${payment.payment_number}`" />
    <AppLayout>
        <div class="max-w-3xl mx-auto p-6 space-y-4">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <component :is="payment.type === 'receipt' ? ArrowDownCircle : ArrowUpCircle"
                        :class="payment.type === 'receipt' ? 'h-6 w-6 text-green-500' : 'h-6 w-6 text-red-500'" />
                    <h1 class="text-xl font-bold font-mono">{{ payment.payment_number }}</h1>
                    <Badge :variant="payment.type === 'receipt' ? 'default' : 'destructive'" class="capitalize">
                        {{ payment.type }}
                    </Badge>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="payments.index.url()">← Back</Link>
                    </Button>
                    <Button variant="destructive" size="icon" @click="destroy">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <!-- Details card -->
            <Card>
                <CardContent class="p-6 space-y-6">

                    <!-- Meta grid -->
                    <div class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
                        <div>
                            <p class="text-muted-foreground text-xs uppercase tracking-wide mb-0.5">Date</p>
                            <p class="font-medium">{{ new Date(payment.payment_date).toLocaleDateString('en-ZM', { day: 'numeric', month: 'long', year: 'numeric' }) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase tracking-wide mb-0.5">Method</p>
                            <p class="font-medium">{{ methodLabel[payment.method] ?? payment.method }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase tracking-wide mb-0.5">{{ payment.type === 'receipt' ? 'Customer' : 'Supplier' }}</p>
                            <p class="font-medium">{{ payment.contact?.name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase tracking-wide mb-0.5">{{ payment.type === 'receipt' ? 'Deposit Account' : 'Payment Account' }}</p>
                            <p class="font-medium">{{ payment.deposit_account.code }} — {{ payment.deposit_account.name }}</p>
                        </div>
                        <div v-if="payment.reference">
                            <p class="text-muted-foreground text-xs uppercase tracking-wide mb-0.5">Reference</p>
                            <p class="font-medium font-mono">{{ payment.reference }}</p>
                        </div>
                        <div v-if="Number(payment.withholding_tax_amount) > 0">
                            <p class="text-muted-foreground text-xs uppercase tracking-wide mb-0.5">Withholding Tax</p>
                            <p class="font-medium">{{ fmt(payment.withholding_tax_amount) }}</p>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div class="rounded-lg bg-muted/50 px-4 py-3 flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Total Amount</span>
                        <span class="text-2xl font-bold"
                            :class="payment.type === 'receipt' ? 'text-green-600' : 'text-red-600'">
                            {{ payment.type === 'receipt' ? '+' : '−' }} {{ fmt(payment.amount) }}
                        </span>
                    </div>

                    <!-- Allocations -->
                    <div v-if="payment.allocations.length">
                        <Separator class="mb-4" />
                        <p class="text-sm font-semibold mb-3">
                            Allocated to {{ payment.type === 'receipt' ? 'Invoices' : 'Bills' }}
                        </p>
                        <div class="space-y-2">
                            <div v-for="alloc in payment.allocations" :key="alloc.id"
                                class="flex items-center justify-between text-sm">
                                <template v-if="alloc.allocatable">
                                    <Link
                                        v-if="alloc.allocatable_type.includes('Invoice')"
                                        :href="invoices.show.url(alloc.allocatable.id)"
                                        class="font-mono text-primary hover:underline">
                                        {{ (alloc.allocatable as AllocatableInvoice).invoice_number }}
                                    </Link>
                                    <Link
                                        v-else
                                        :href="bills.show.url(alloc.allocatable.id)"
                                        class="font-mono text-primary hover:underline">
                                        {{ (alloc.allocatable as AllocatableBill).bill_number ?? `BILL-${alloc.allocatable.id}` }}
                                    </Link>
                                </template>
                                <span v-else class="text-muted-foreground">—</span>
                                <span class="font-medium">{{ fmt(alloc.amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <p v-if="payment.notes" class="text-sm text-muted-foreground border-t pt-4">
                        {{ payment.notes }}
                    </p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
