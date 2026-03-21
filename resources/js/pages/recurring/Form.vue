<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface Contact { id: number; name: string }
interface Account { id: number; code: string; name: string }
interface TaxRate  { id: number; name: string; code: string; rate: string }
interface Item {
    description: string; account_id: number | null; tax_rate_id: number | null;
    quantity: number; unit_price: number; discount_percent: number;
}
interface RecurringInvoice {
    id: number; contact_id: number; frequency: string; day_of_month: number;
    days_due: number; next_run_at: string; reference: string | null; notes: string | null;
    discount_amount: number; items: Item[];
}

const props = defineProps<{
    recurring: RecurringInvoice | null;
    contacts: Contact[];
    accounts: Account[];
    taxRates: TaxRate[];
}>();

const isEdit = !!props.recurring;
const today  = new Date().toISOString().slice(0, 10);

const blankItem = (): Item => ({
    description: '', account_id: null, tax_rate_id: null,
    quantity: 1, unit_price: 0, discount_percent: 0,
});

const form = useForm({
    contact_id:      props.recurring?.contact_id ?? null as number | null,
    frequency:       props.recurring?.frequency ?? 'monthly',
    day_of_month:    props.recurring?.day_of_month ?? 1,
    days_due:        props.recurring?.days_due ?? 30,
    next_run_at:     props.recurring?.next_run_at ?? today,
    reference:       props.recurring?.reference ?? '',
    notes:           props.recurring?.notes ?? '',
    discount_amount: props.recurring?.discount_amount ?? 0,
    items:           props.recurring?.items?.map(i => ({ ...i })) ?? [blankItem()],
});

function calcItem(item: Item) {
    const gross    = (item.quantity ?? 0) * (item.unit_price ?? 0);
    const disc     = gross * ((item.discount_percent ?? 0) / 100);
    const subtotal = Math.round((gross - disc) * 100) / 100;
    const taxRate  = item.tax_rate_id ? props.taxRates.find(t => t.id === item.tax_rate_id) : null;
    const tax      = taxRate ? Math.round(subtotal * (Number(taxRate.rate) / 100) * 100) / 100 : 0;
    return { subtotal, tax, total: subtotal + tax };
}

const lineCalcs = computed(() => form.items.map(calcItem));
const subtotal  = computed(() => lineCalcs.value.reduce((s, c) => s + c.subtotal, 0));
const taxTotal  = computed(() => lineCalcs.value.reduce((s, c) => s + c.tax, 0));
const discount  = computed(() => Number(form.discount_amount) || 0);
const total     = computed(() => subtotal.value + taxTotal.value - discount.value);

function addItem()            { form.items.push(blankItem()); }
function removeItem(i: number) { form.items.splice(i, 1); }

function submit() {
    if (isEdit) {
        form.put(`/recurring/${props.recurring!.id}`);
    } else {
        form.post('/recurring');
    }
}

function fmt(v: number) {
    return v.toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <AppLayout>
        <form @submit.prevent="submit">
            <div class="max-w-5xl mx-auto p-6 space-y-6">

                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Recurring Invoice' : 'New Recurring Invoice' }}</h1>
                    <Button type="button" variant="outline" @click="router.get('/recurring')">Cancel</Button>
                </div>

                <!-- Schedule settings -->
                <Card>
                    <CardHeader><CardTitle>Schedule &amp; Contact</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">

                        <div class="col-span-2 space-y-2">
                            <Label>Customer <span class="text-destructive">*</span></Label>
                            <select v-model="form.contact_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null" disabled>Select a customer…</option>
                                <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <InputError :message="form.errors.contact_id" />
                        </div>

                        <div class="space-y-2">
                            <Label>Frequency <span class="text-destructive">*</span></Label>
                            <select v-model="form.frequency"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                            <InputError :message="form.errors.frequency" />
                        </div>

                        <div class="space-y-2">
                            <Label for="day_of_month">Day of Month</Label>
                            <Input id="day_of_month" v-model.number="form.day_of_month" type="number" min="1" max="31" />
                            <InputError :message="form.errors.day_of_month" />
                        </div>

                        <div class="space-y-2">
                            <Label for="days_due">Payment Due (days after issue)</Label>
                            <Input id="days_due" v-model.number="form.days_due" type="number" min="0" />
                            <InputError :message="form.errors.days_due" />
                        </div>

                        <div class="space-y-2">
                            <Label for="next_run_at">First / Next Run Date</Label>
                            <Input id="next_run_at" v-model="form.next_run_at" type="date" />
                            <InputError :message="form.errors.next_run_at" />
                        </div>

                        <div class="space-y-2">
                            <Label for="reference">Reference / PO Number</Label>
                            <Input id="reference" v-model="form.reference" placeholder="e.g. CONTRACT-001" />
                        </div>

                    </CardContent>
                </Card>

                <!-- Line items -->
                <Card>
                    <CardHeader><CardTitle>Line Items</CardTitle></CardHeader>
                    <CardContent class="space-y-3">

                        <div class="hidden md:grid grid-cols-[1fr_140px_120px_100px_120px_100px_110px_36px] gap-2 text-xs font-medium text-muted-foreground px-1">
                            <span>Description</span><span>Account</span><span>Tax Rate</span>
                            <span class="text-right">Qty</span><span class="text-right">Unit Price</span>
                            <span class="text-right">Disc %</span><span class="text-right">Total</span><span></span>
                        </div>

                        <div v-for="(item, i) in form.items" :key="i"
                            class="grid grid-cols-1 md:grid-cols-[1fr_140px_120px_100px_120px_100px_110px_36px] gap-2 items-start border-b pb-3 last:border-0">

                            <div>
                                <Input v-model="item.description" placeholder="Description" />
                                <InputError :message="(form.errors as Record<string,string>)[`items.${i}.description`]" />
                            </div>

                            <select v-model="item.account_id"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">No account</option>
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                            </select>

                            <select v-model="item.tax_rate_id"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">No tax</option>
                                <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.code }} ({{ t.rate }}%)</option>
                            </select>

                            <Input v-model.number="item.quantity" type="number" min="0.001" step="0.001" class="text-right" />
                            <Input v-model.number="item.unit_price" type="number" min="0" step="0.01" class="text-right" />
                            <Input v-model.number="item.discount_percent" type="number" min="0" max="100" step="0.01" class="text-right" placeholder="0" />

                            <div class="flex items-center justify-end text-sm font-medium pr-1">
                                ZMW {{ fmt(lineCalcs[i].total) }}
                            </div>

                            <Button type="button" variant="ghost" size="icon" class="text-muted-foreground hover:text-destructive"
                                :disabled="form.items.length === 1" @click="removeItem(i)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>

                        <Button type="button" variant="outline" size="sm" @click="addItem">
                            <Plus class="mr-2 h-4 w-4" /> Add Line
                        </Button>

                        <div class="flex justify-end pt-4">
                            <dl class="w-64 space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-muted-foreground">Subtotal</dt>
                                    <dd class="font-medium">ZMW {{ fmt(subtotal) }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-muted-foreground">VAT</dt>
                                    <dd class="font-medium">ZMW {{ fmt(taxTotal) }}</dd>
                                </div>
                                <div class="flex justify-between items-center">
                                    <dt class="text-muted-foreground">Discount</dt>
                                    <dd>
                                        <Input v-model.number="form.discount_amount" type="number" min="0" step="0.01"
                                            class="h-7 w-28 text-right text-sm" placeholder="0.00" />
                                    </dd>
                                </div>
                                <div class="flex justify-between border-t pt-2 font-bold text-base">
                                    <dt>Total</dt>
                                    <dd>ZMW {{ fmt(total) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </CardContent>
                </Card>

                <!-- Notes -->
                <Card>
                    <CardContent class="pt-4 space-y-2">
                        <Label for="notes">Notes (applied to each generated invoice)</Label>
                        <textarea id="notes" v-model="form.notes" rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Payment instructions, thank you note…" />
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : isEdit ? 'Update Template' : 'Create Template' }}
                    </Button>
                </div>

            </div>
        </form>
    </AppLayout>
</template>
