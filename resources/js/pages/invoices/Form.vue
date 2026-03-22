<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { FileUp, Plus, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import * as invoices from '@/routes/invoices';

interface Contact { id: number; name: string; email: string | null }
interface Account { id: number; code: string; name: string }
interface TaxRate { id: number; name: string; code: string; rate: string }
interface ClsCode  { id: number; name: string; hs_code: number }
interface InvoiceItem {
    id?: number; description: string; account_id: number | null;
    tax_rate_id: number | null; quantity: number; unit_price: number; discount_percent: number;
    item_type: 'goods' | 'service'; cls_code_id: number | null;
}
interface Invoice {
    id: number; contact_id: number; issue_date: string; due_date: string;
    reference: string | null; notes: string | null; footer: string | null;
    discount_amount: number; zra_invoice_path: string | null;
    items: (InvoiceItem & { subtotal: number; tax_amount: number; total: number })[];
}

const props = defineProps<{
    invoice: Invoice | null;
    contacts: Contact[];
    accounts: Account[];
    taxRates: TaxRate[];
    vsdcEnabled: boolean;
    goodsCodes: ClsCode[];
    serviceCodes: ClsCode[];
}>();

const isEdit = !!props.invoice;
const today  = new Date().toISOString().slice(0, 10);
const in30   = new Date(Date.now() + 30 * 86_400_000).toISOString().slice(0, 10);

const blankItem = (): InvoiceItem => ({
    description: '', account_id: null, tax_rate_id: null,
    quantity: 1, unit_price: 0, discount_percent: 0,
    item_type: 'service', cls_code_id: null,
});

// Per-item state for the ZRA code combobox
const clsSearch  = ref<string[]>([]);
const clsOpen    = ref<boolean[]>([]);

function clsOptions(item: InvoiceItem) {
    return item.item_type === 'goods' ? props.goodsCodes : props.serviceCodes;
}

function filteredClsOptions(item: InvoiceItem, i: number) {
    const q = (clsSearch.value[i] ?? '').toLowerCase();
    if (!q) return clsOptions(item);
    return clsOptions(item).filter(c =>
        c.name.toLowerCase().includes(q) || String(c.hs_code).includes(q)
    );
}

function toggleCls(i: number) {
    const opening = !clsOpen.value[i];
    clsOpen.value  = [];   // close all others
    if (opening) {
        clsOpen.value[i]   = true;
        clsSearch.value[i] = '';
    }
}

function closeCls(i: number) {
    clsOpen.value[i]   = false;
    clsSearch.value[i] = '';
}

function selectCls(item: InvoiceItem, i: number, id: number) {
    item.cls_code_id   = id;
    clsOpen.value[i]   = false;
    clsSearch.value[i] = '';
}

function clearCls(item: InvoiceItem, i: number) {
    item.cls_code_id   = null;
    clsOpen.value[i]   = false;
    clsSearch.value[i] = '';
}

function onItemTypeChange(item: InvoiceItem, i: number) {
    item.cls_code_id   = null;
    clsSearch.value[i] = '';
    clsOpen.value[i]   = false;
}

function selectedClsLabel(item: InvoiceItem) {
    if (!item.cls_code_id) return null;
    return clsOptions(item).find(c => c.id === item.cls_code_id) ?? null;
}

const form = useForm({
    contact_id:      props.invoice?.contact_id ?? null as number | null,
    issue_date:      props.invoice?.issue_date ?? today,
    due_date:        props.invoice?.due_date ?? in30,
    reference:       props.invoice?.reference ?? '',
    notes:           props.invoice?.notes ?? '',
    footer:          props.invoice?.footer ?? '',
    discount_amount: props.invoice?.discount_amount ?? 0,
    zra_invoice:     null as File | null,
    items:           props.invoice?.items?.map(i => ({
        id: i.id, description: i.description,
        account_id: i.account_id, tax_rate_id: i.tax_rate_id,
        quantity: i.quantity, unit_price: i.unit_price, discount_percent: i.discount_percent,
        item_type: i.item_type ?? 'service', cls_code_id: i.cls_code_id ?? null,
    })) ?? [blankItem()],
});

// ── Live calculations ────────────────────────────────────────────────────

function calcItem(item: InvoiceItem) {
    const gross     = (item.quantity ?? 0) * (item.unit_price ?? 0);
    const disc      = gross * ((item.discount_percent ?? 0) / 100);
    const subtotal  = Math.round((gross - disc) * 100) / 100;
    const taxRate   = item.tax_rate_id ? props.taxRates.find(t => t.id === item.tax_rate_id) : null;
    const tax       = taxRate ? Math.round(subtotal * (Number(taxRate.rate) / 100) * 100) / 100 : 0;
    return { subtotal, tax, total: subtotal + tax };
}

const lineCalcs = computed(() => form.items.map(calcItem));

const subtotal = computed(() => lineCalcs.value.reduce((s, c) => s + c.subtotal, 0));
const taxTotal = computed(() => lineCalcs.value.reduce((s, c) => s + c.tax, 0));
const discount = computed(() => Number(form.discount_amount) || 0);
const total    = computed(() => subtotal.value + taxTotal.value - discount.value);

// ── Item management ──────────────────────────────────────────────────────

function addItem()       { form.items.push(blankItem()); }
function removeItem(i: number) { form.items.splice(i, 1); }

// ── Submit ───────────────────────────────────────────────────────────────

function submit() {
    if (isEdit) {
        form.put(invoices.update.url(props.invoice!.id), { forceFormData: true });
    } else {
        form.post(invoices.store.url(), { forceFormData: true });
    }
}

function fmt(v: number) {
    return v.toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

const zraFileName = ref<string | null>(props.invoice?.zra_invoice_path
    ? props.invoice.zra_invoice_path.split('/').pop() ?? null
    : null);

function onZraFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.zra_invoice = file;
        zraFileName.value = file.name;
    }
}

function clearZraFile(e: Event) {
    form.zra_invoice = null;
    zraFileName.value = null;
    (e.target as HTMLElement).closest('.zra-file-row')
        ?.querySelector('input[type=file]')
        ?.dispatchEvent(new Event('reset'));
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Invoice' : 'New Invoice'" />
    <AppLayout>
        <form @submit.prevent="submit">
            <div class="max-w-9xl mx-auto p-6 space-y-6">

                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Invoice' : 'New Invoice' }}</h1>
                    <Button type="button" variant="outline" @click="router.get(invoices.index.url())">Cancel</Button>
                </div>

                <!-- Header card -->
                <Card>
                    <CardHeader><CardTitle>Invoice Details</CardTitle></CardHeader>
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
                            <Label for="issue_date">Issue Date <span class="text-destructive">*</span></Label>
                            <Input id="issue_date" v-model="form.issue_date" type="date" />
                            <InputError :message="form.errors.issue_date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="due_date">Due Date <span class="text-destructive">*</span></Label>
                            <Input id="due_date" v-model="form.due_date" type="date" />
                            <InputError :message="form.errors.due_date" />
                        </div>

                        <div class="space-y-2">
                            <Label for="reference">Reference / PO Number</Label>
                            <Input id="reference" v-model="form.reference" placeholder="e.g. PO-2026-001" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Line items -->
                <Card>
                    <CardHeader><CardTitle>Line Items</CardTitle></CardHeader>
                    <CardContent class="space-y-3">

                        <!-- Header row -->
                        <div class="hidden md:grid gap-2 text-xs font-medium text-muted-foreground px-1"
                            :class="vsdcEnabled
                                ? 'grid-cols-[1fr_110px_180px_140px_120px_100px_120px_100px_110px_36px]'
                                : 'grid-cols-[1fr_140px_120px_100px_120px_100px_110px_36px]'">
                            <span>Description</span>
                            <template v-if="vsdcEnabled"><span>Type</span><span>ZRA Code</span></template>
                            <span>Account</span><span>Tax Rate</span>
                            <span class="text-right">Qty</span><span class="text-right">Unit Price</span>
                            <span class="text-right">Disc %</span><span class="text-right">Total</span><span></span>
                        </div>

                        <div v-for="(item, i) in form.items" :key="i"
                            class="grid grid-cols-1 gap-2 items-start border-b pb-3 last:border-0"
                            :class="vsdcEnabled
                                ? 'md:grid-cols-[1fr_110px_180px_140px_120px_100px_120px_100px_110px_36px]'
                                : 'md:grid-cols-[1fr_140px_120px_100px_120px_100px_110px_36px]'">

                            <!-- Description -->
                            <div>
                                <Input v-model="item.description" placeholder="Description" />
                                <InputError :message="(form.errors as Record<string,string>)[`items.${i}.description`]" />
                            </div>

                            <!-- Item Type + ZRA Code — only for VSDC-registered companies -->
                            <template v-if="vsdcEnabled">
                                <select v-model="item.item_type" @change="onItemTypeChange(item, i)"
                                    class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                    <option value="service">Service</option>
                                    <option value="goods">Goods</option>
                                </select>

                                <div class="relative">
                                    <div v-if="clsOpen[i]" class="fixed inset-0 z-40" @click="closeCls(i)" />
                                    <button type="button"
                                        class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-2 py-1 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                        @click="toggleCls(i)">
                                        <span v-if="selectedClsLabel(item)" class="truncate text-left">{{ selectedClsLabel(item)!.name }}</span>
                                        <span v-else class="text-muted-foreground truncate">Select ZRA code…</span>
                                        <svg class="ml-1 h-4 w-4 shrink-0 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div v-if="clsOpen[i]" class="absolute z-50 mt-1 w-72 rounded-md border border-input bg-popover shadow-lg text-sm">
                                        <div class="p-2 border-b border-input">
                                            <input v-model="clsSearch[i]" type="text" placeholder="Type to filter…" autofocus
                                                class="w-full rounded-sm border border-input bg-transparent px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-ring" />
                                        </div>
                                        <ul class="max-h-52 overflow-y-auto">
                                            <li><button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-muted-foreground hover:bg-accent italic" @click="clearCls(item, i)">— None</button></li>
                                            <li v-for="c in filteredClsOptions(item, i)" :key="c.id">
                                                <button type="button"
                                                    class="flex w-full items-start gap-2 px-3 py-2 hover:bg-accent text-left"
                                                    :class="{ 'bg-accent font-medium': item.cls_code_id === c.id }"
                                                    @click="selectCls(item, i, c.id)">
                                                    <span class="font-mono text-xs text-muted-foreground shrink-0 pt-0.5 w-20">{{ c.hs_code }}</span>
                                                    <span class="leading-snug">{{ c.name }}</span>
                                                </button>
                                            </li>
                                            <li v-if="filteredClsOptions(item, i).length === 0" class="px-3 py-2 text-muted-foreground">No results</li>
                                        </ul>
                                    </div>
                                </div>
                            </template>

                            <!-- Account -->
                            <select v-model="item.account_id"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">No account</option>
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                            </select>

                            <!-- Tax Rate -->
                            <select v-model="item.tax_rate_id"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">No tax</option>
                                <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.code }} ({{ t.rate }}%)</option>
                            </select>

                            <!-- Qty -->
                            <Input v-model.number="item.quantity" type="number" min="0.001" step="0.001" class="text-right" />

                            <!-- Unit Price -->
                            <Input v-model.number="item.unit_price" type="number" min="0" step="0.01" class="text-right" />

                            <!-- Discount % -->
                            <Input v-model.number="item.discount_percent" type="number" min="0" max="100" step="0.01" class="text-right" placeholder="0" />

                            <!-- Total (computed) -->
                            <div class="flex items-center justify-end text-sm font-medium pr-1">
                                ZMW {{ fmt(lineCalcs[i].total) }}
                            </div>

                            <!-- Remove -->
                            <Button type="button" variant="ghost" size="icon" class="text-muted-foreground hover:text-destructive"
                                :disabled="form.items.length === 1" @click="removeItem(i)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>

                        <Button type="button" variant="outline" size="sm" @click="addItem">
                            <Plus class="mr-2 h-4 w-4" /> Add Line
                        </Button>

                        <!-- Totals -->
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

                <!-- Notes & Footer -->
                <Card>
                    <CardContent class="pt-4 grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="notes">Notes (visible to customer)</Label>
                            <textarea id="notes" v-model="form.notes" rows="3"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder="Payment instructions, thank you note…" />
                        </div>
                        <div class="space-y-2">
                            <Label for="footer">Footer</Label>
                            <textarea id="footer" v-model="form.footer" rows="3"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder="e.g. Bank details, terms…" />
                        </div>
                    </CardContent>
                </Card>

                <!-- ZRA Invoice Upload (optional) -->
                <Card>
                    <CardHeader><CardTitle>ZRA Invoice <span class="text-muted-foreground font-normal text-sm">(optional)</span></CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-sm text-muted-foreground">
                            If you have a ZRA Smart Invoice document, upload it here to attach it to this invoice.
                            Accepted: PDF, JPG, PNG — max 5 MB.
                        </p>

                        <!-- Existing file indicator -->
                        <div v-if="invoice?.zra_invoice_path && !form.zra_invoice"
                            class="flex items-center gap-2 text-sm">
                            <a :href="`/storage/${invoice.zra_invoice_path}`" target="_blank"
                                class="text-primary underline underline-offset-2">
                                View current ZRA invoice
                            </a>
                            <span class="text-muted-foreground">(upload a new file to replace)</span>
                        </div>

                        <div class="zra-file-row flex items-center gap-3">
                            <label class="flex-1 flex items-center gap-2 cursor-pointer rounded-md border border-dashed border-input px-4 py-3 hover:bg-muted/40 transition-colors">
                                <FileUp class="h-5 w-5 text-muted-foreground shrink-0" />
                                <span class="text-sm text-muted-foreground truncate">
                                    {{ zraFileName ?? 'Click to choose a file…' }}
                                </span>
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="sr-only" @change="onZraFileChange" />
                            </label>
                            <Button v-if="form.zra_invoice" type="button" variant="ghost" size="icon"
                                class="text-muted-foreground hover:text-destructive shrink-0" @click="clearZraFile">
                                <X class="h-4 w-4" />
                            </Button>
                        </div>
                        <InputError :message="form.errors.zra_invoice" />
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : isEdit ? 'Update Invoice' : 'Save as Draft' }}
                    </Button>
                </div>

            </div>
        </form>
    </AppLayout>
</template>
