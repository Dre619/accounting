<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, PlusCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import * as bills from '@/routes/bills';
import * as invoices from '@/routes/invoices';
import * as payments from '@/routes/payments';

interface AllocatableInvoice { id: number; invoice_number: string }
interface AllocatableBill    { id: number; bill_number: string | null }

interface Allocation {
    id: number;
    amount: string;
    allocatable_type: string;
    allocatable: AllocatableInvoice | AllocatableBill | null;
}

interface OpenDoc {
    id: number;
    number: string;
    total: string;
    amount_due: string;
    due_date: string;
    doc_type: 'invoice' | 'bill';
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

const props = defineProps<{
    payment: Payment;
    openDocs: OpenDoc[];
    unallocatedAmount: number;
}>();

const showAllocatePanel = ref(false);

const allocForm = useForm({
    allocations: props.openDocs.map(d => ({
        type:   d.doc_type,
        id:     d.id,
        amount: 0,
        max:    Number(d.amount_due),
        number: d.number,
        due:    d.due_date,
    })),
});

const totalEntered = computed(() =>
    allocForm.allocations.reduce((s, a) => s + (Number(a.amount) || 0), 0)
);
const remaining = computed(() => Math.max(0, props.unallocatedAmount - totalEntered.value));

function autoFill() {
    let left = props.unallocatedAmount;
    allocForm.allocations = allocForm.allocations.map(a => {
        const amt = Math.min(left, a.max);
        left = Math.max(0, left - amt);
        return { ...a, amount: Math.round(amt * 100) / 100 };
    });
}

function submitAllocations() {
    const filtered = allocForm.allocations.filter(a => Number(a.amount) > 0);
    allocForm
        .transform(() => ({ allocations: filtered.map(({ type, id, amount }) => ({ type, id, amount })) }))
        .post(`/payments/${props.payment.id}/allocate`, {
            onSuccess: () => { showAllocatePanel.value = false; },
        });
}

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
                    <Badge v-if="unallocatedAmount > 0" variant="outline" class="text-amber-600 border-amber-400">
                        Unallocated
                    </Badge>
                </div>
                <div class="flex gap-2">
                    <Button v-if="unallocatedAmount > 0 && openDocs.length" variant="outline" size="sm"
                        @click="showAllocatePanel = !showAllocatePanel">
                        <PlusCircle class="h-4 w-4 mr-1.5" />
                        Allocate
                    </Button>
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

                    <!-- Amount + unallocated -->
                    <div class="rounded-lg bg-muted/50 px-4 py-3 space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-muted-foreground">Total Amount</span>
                            <span class="text-2xl font-bold"
                                :class="payment.type === 'receipt' ? 'text-green-600' : 'text-red-600'">
                                {{ payment.type === 'receipt' ? '+' : '−' }} {{ fmt(payment.amount) }}
                            </span>
                        </div>
                        <div v-if="unallocatedAmount > 0" class="flex items-center justify-between text-sm">
                            <span class="text-amber-600">Unallocated</span>
                            <span class="font-medium text-amber-600">{{ fmt(unallocatedAmount) }}</span>
                        </div>
                    </div>

                    <!-- Existing allocations -->
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

            <!-- Allocate panel -->
            <Card v-if="showAllocatePanel && openDocs.length">
                <CardContent class="p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold text-sm">
                            Allocate {{ fmt(unallocatedAmount) }} to open {{ payment.type === 'receipt' ? 'invoices' : 'bills' }}
                        </p>
                        <Button variant="ghost" size="sm" @click="autoFill">Auto-fill</Button>
                    </div>

                    <div class="space-y-2">
                        <div v-for="(row, i) in allocForm.allocations" :key="row.id"
                            class="grid grid-cols-[1fr_auto_auto] items-center gap-3 text-sm">
                            <div>
                                <span class="font-mono text-primary">{{ row.number }}</span>
                                <span class="text-muted-foreground ml-2">due {{ row.due }}</span>
                                <span class="ml-2 text-xs text-muted-foreground">({{ fmt(row.max) }} outstanding)</span>
                            </div>
                            <Input
                                type="number"
                                min="0"
                                :max="row.max"
                                step="0.01"
                                v-model.number="allocForm.allocations[i].amount"
                                class="w-36 text-right"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t text-sm">
                        <span class="text-muted-foreground">
                            Remaining after allocation: <strong>{{ fmt(remaining) }}</strong>
                        </span>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" @click="showAllocatePanel = false">Cancel</Button>
                            <Button size="sm" :disabled="allocForm.processing || totalEntered <= 0" @click="submitAllocations">
                                Save Allocations
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
