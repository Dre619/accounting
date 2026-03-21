<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import * as payments from '@/routes/payments';

interface Contact     { id: number; name: string; type: string }
interface BankAccount { id: number; code: string; name: string }
interface OpenDoc     { id: number; number: string; total: string; amount_due: string; due_date: string; doc_type: 'invoice'|'bill' }

const props = defineProps<{
    contacts:     Contact[];
    bankAccounts: BankAccount[];
    defaultType:  'receipt' | 'payment';
}>();

const openDocs    = ref<OpenDoc[]>([]);
const loadingDocs = ref(false);

const form = useForm({
    type:                   props.defaultType,
    contact_id:             null as number|null,
    payment_date:           new Date().toISOString().slice(0, 10),
    amount:                 0,
    withholding_tax_amount: 0,
    method:                 'bank_transfer' as string,
    reference:              '',
    deposit_account_id:     null as number|null,
    notes:                  '',
    allocations:            [] as { type: 'invoice'|'bill'; id: number; amount: number; max: number; number: string }[],
});

// Filter contacts by type
const filteredContacts = computed(() => {
    if (form.type === 'receipt') return props.contacts.filter(c => ['customer','both'].includes(c.type));
    return props.contacts.filter(c => ['supplier','both'].includes(c.type));
});

// Totals
const totalAllocated  = computed(() => form.allocations.reduce((s, a) => s + (Number(a.amount) || 0), 0));
const unallocated     = computed(() => Math.max(0, Number(form.amount) - totalAllocated.value));

// Load open documents when contact or type changes
watch([() => form.contact_id, () => form.type], async ([contactId, type]) => {
    form.allocations = [];
    openDocs.value   = [];
    if (!contactId) return;

    loadingDocs.value = true;
    try {
        const res  = await fetch(payments.openDocuments.url() + `?contact_id=${contactId}&type=${type}`);
        const data = await res.json() as OpenDoc[];
        openDocs.value = data;
        // Pre-populate allocations
        form.allocations = data.map(d => ({
            type:   d.doc_type,
            id:     d.id,
            amount: 0,
            max:    Number(d.amount_due),
            number: d.number ?? String(d.id),
        }));
    } finally {
        loadingDocs.value = false;
    }
});

// When payment amount changes, auto-fill allocations oldest-first
function autoAllocate() {
    let remaining = Number(form.amount);
    form.allocations = form.allocations.map(a => {
        const allocated = Math.min(remaining, a.max);
        remaining       = Math.max(0, remaining - allocated);
        return { ...a, amount: Math.round(allocated * 100) / 100 };
    });
}

const methods = [
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cash',          label: 'Cash'           },
    { value: 'cheque',        label: 'Cheque'         },
    { value: 'airtel_money',  label: 'Airtel Money'   },
    { value: 'mtn_money',     label: 'MTN Money'      },
    { value: 'zamtel_money',  label: 'Zamtel Money'   },
    { value: 'other',         label: 'Other'          },
];

function submit() { form.post(payments.store.url()); }

function fmt(v: string|number) { return 'ZMW ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 }); }
</script>

<template>
    <Head :title="form.type === 'receipt' ? 'Record Receipt' : 'Record Payment'" />
    <AppLayout>
        <form @submit.prevent="submit">
            <div class="max-w-2xl mx-auto p-6 space-y-6">

                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">
                        {{ form.type === 'receipt' ? 'Record Receipt' : 'Record Payment' }}
                    </h1>
                    <Button type="button" variant="outline" @click="router.get(payments.index.url())">Cancel</Button>
                </div>

                <!-- Type toggle -->
                <div class="flex gap-3">
                    <button type="button"
                        class="flex-1 flex items-center justify-center gap-2 rounded-lg border-2 py-3 text-sm font-medium transition-colors"
                        :class="form.type === 'receipt' ? 'border-primary bg-primary/5 text-primary' : 'border-border text-muted-foreground'"
                        @click="form.type = 'receipt'">
                        <ArrowDownCircle class="h-5 w-5" /> Money In (Receipt)
                    </button>
                    <button type="button"
                        class="flex-1 flex items-center justify-center gap-2 rounded-lg border-2 py-3 text-sm font-medium transition-colors"
                        :class="form.type === 'payment' ? 'border-destructive bg-destructive/5 text-destructive' : 'border-border text-muted-foreground'"
                        @click="form.type = 'payment'">
                        <ArrowUpCircle class="h-5 w-5" /> Money Out (Payment)
                    </button>
                </div>

                <Card>
                    <CardHeader><CardTitle>Payment Details</CardTitle></CardHeader>
                    <CardContent class="space-y-4">

                        <div class="space-y-2">
                            <Label>{{ form.type === 'receipt' ? 'Customer' : 'Supplier' }}</Label>
                            <select v-model="form.contact_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">Select contact… (optional)</option>
                                <option v-for="c in filteredContacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="payment_date">Date <span class="text-destructive">*</span></Label>
                                <Input id="payment_date" v-model="form.payment_date" type="date" />
                                <InputError :message="form.errors.payment_date" />
                            </div>
                            <div class="space-y-2">
                                <Label for="amount">Amount (ZMW) <span class="text-destructive">*</span></Label>
                                <Input id="amount" v-model.number="form.amount" type="number" min="0.01" step="0.01" placeholder="0.00" />
                                <InputError :message="form.errors.amount" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label>Payment Method <span class="text-destructive">*</span></Label>
                                <select v-model="form.method" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                    <option v-for="m in methods" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                                <InputError :message="form.errors.method" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{ form.type === 'receipt' ? 'Deposit Account' : 'Payment Account' }} <span class="text-destructive">*</span></Label>
                                <select v-model="form.deposit_account_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                    <option :value="null" disabled>Select account…</option>
                                    <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                                </select>
                                <InputError :message="form.errors.deposit_account_id" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="reference">Reference / Cheque #</Label>
                                <Input id="reference" v-model="form.reference" placeholder="Transaction ID, cheque #…" />
                            </div>
                            <div class="space-y-2">
                                <Label for="wht">Withholding Tax (ZMW)</Label>
                                <Input id="wht" v-model.number="form.withholding_tax_amount" type="number" min="0" step="0.01" placeholder="0.00" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="notes">Notes</Label>
                            <textarea id="notes" v-model="form.notes" rows="2"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder="Any additional notes…" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Allocations -->
                <Card v-if="form.contact_id">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Allocate to {{ form.type === 'receipt' ? 'Invoices' : 'Bills' }}</CardTitle>
                            <Button type="button" variant="ghost" size="sm" @click="autoAllocate" :disabled="!form.amount">
                                Auto-allocate
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="loadingDocs" class="py-4 text-center text-sm text-muted-foreground">Loading open documents…</div>
                        <div v-else-if="!openDocs.length" class="py-4 text-center text-sm text-muted-foreground">
                            No open {{ form.type === 'receipt' ? 'invoices' : 'bills' }} for this contact.
                        </div>
                        <div v-else class="space-y-3">
                            <div v-for="(alloc, i) in form.allocations" :key="alloc.id"
                                class="flex items-center gap-3 text-sm">
                                <div class="flex-1">
                                    <p class="font-mono font-medium">{{ alloc.number }}</p>
                                    <p class="text-xs text-muted-foreground">Due: {{ fmt(alloc.max) }}</p>
                                </div>
                                <div class="w-36">
                                    <Input v-model.number="form.allocations[i].amount"
                                        type="number" min="0" step="0.01"
                                        :max="alloc.max"
                                        placeholder="0.00"
                                        class="text-right h-8" />
                                </div>
                            </div>

                            <div class="flex justify-between text-sm pt-2 border-t">
                                <span class="text-muted-foreground">Allocated</span>
                                <span class="font-semibold">{{ fmt(totalAllocated) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Unallocated</span>
                                <span :class="unallocated > 0 ? 'text-amber-600 font-medium' : 'text-muted-foreground'">{{ fmt(unallocated) }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" size="lg" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : `Save ${form.type === 'receipt' ? 'Receipt' : 'Payment'}` }}
                    </Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
