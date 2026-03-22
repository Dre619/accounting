<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import * as bills from '@/routes/bills';

interface Contact  { id: number; name: string }
interface Account  { id: number; code: string; name: string }
interface TaxRate  { id: number; name: string; code: string; rate: string }
interface ClsCode  { id: number; name: string; hs_code: number }
interface BillItem { id?: number; description: string; account_id: number|null; tax_rate_id: number|null; quantity: number; unit_price: number; discount_percent: number; item_type: 'goods'|'service'; cls_code_id: number|null }
interface Bill     { id: number; contact_id: number; bill_number: string|null; reference: string|null; issue_date: string; due_date: string; notes: string|null; discount_amount: number; items: (BillItem & { subtotal: number; tax_amount: number; total: number })[] }

const props = defineProps<{ bill: Bill|null; contacts: Contact[]; accounts: Account[]; taxRates: TaxRate[]; vsdcEnabled: boolean; goodsCodes: ClsCode[]; serviceCodes: ClsCode[] }>();

const isEdit = !!props.bill;
const today  = new Date().toISOString().slice(0, 10);
const in30   = new Date(Date.now() + 30 * 86_400_000).toISOString().slice(0, 10);
const blank  = (): BillItem => ({ description: '', account_id: null, tax_rate_id: null, quantity: 1, unit_price: 0, discount_percent: 0, item_type: 'service', cls_code_id: null });

const clsSearch = ref<string[]>([]);
const clsOpen   = ref<boolean[]>([]);

function clsOptions(item: BillItem) { return item.item_type === 'goods' ? props.goodsCodes : props.serviceCodes; }
function filteredClsOptions(item: BillItem, i: number) {
    const q = (clsSearch.value[i] ?? '').toLowerCase();
    if (!q) return clsOptions(item);
    return clsOptions(item).filter(c => c.name.toLowerCase().includes(q) || String(c.hs_code).includes(q));
}
function toggleCls(i: number) {
    const opening = !clsOpen.value[i];
    clsOpen.value = [];
    if (opening) { clsOpen.value[i] = true; clsSearch.value[i] = ''; }
}
function closeCls(i: number)  { clsOpen.value[i] = false; clsSearch.value[i] = ''; }
function selectCls(item: BillItem, i: number, id: number) { item.cls_code_id = id; clsOpen.value[i] = false; clsSearch.value[i] = ''; }
function clearCls(item: BillItem, i: number)  { item.cls_code_id = null; clsOpen.value[i] = false; clsSearch.value[i] = ''; }
function selectedClsLabel(item: BillItem) { return item.cls_code_id ? clsOptions(item).find(c => c.id === item.cls_code_id) ?? null : null; }
function onItemTypeChange(item: BillItem, i: number) { item.cls_code_id = null; clsSearch.value[i] = ''; clsOpen.value[i] = false; }

const form = useForm({
    contact_id:      props.bill?.contact_id ?? null as number|null,
    bill_number:     props.bill?.bill_number ?? '',
    reference:       props.bill?.reference ?? '',
    issue_date:      props.bill?.issue_date ?? today,
    due_date:        props.bill?.due_date ?? in30,
    notes:           props.bill?.notes ?? '',
    discount_amount: props.bill?.discount_amount ?? 0,
    items:           props.bill?.items?.map(i => ({ id: i.id, description: i.description, account_id: i.account_id, tax_rate_id: i.tax_rate_id, quantity: i.quantity, unit_price: i.unit_price, discount_percent: i.discount_percent, item_type: i.item_type ?? 'service', cls_code_id: i.cls_code_id ?? null })) ?? [blank()],
});

function calcItem(item: BillItem) {
    const gross    = (item.quantity ?? 0) * (item.unit_price ?? 0);
    const disc     = gross * ((item.discount_percent ?? 0) / 100);
    const subtotal = Math.round((gross - disc) * 100) / 100;
    const tr       = item.tax_rate_id ? props.taxRates.find(t => t.id === item.tax_rate_id) : null;
    const tax      = tr ? Math.round(subtotal * (Number(tr.rate) / 100) * 100) / 100 : 0;
    return { subtotal, tax, total: subtotal + tax };
}

const lineCalcs = computed(() => form.items.map(calcItem));
const subtotal  = computed(() => lineCalcs.value.reduce((s, c) => s + c.subtotal, 0));
const taxTotal  = computed(() => lineCalcs.value.reduce((s, c) => s + c.tax, 0));
const total     = computed(() => subtotal.value + taxTotal.value - (Number(form.discount_amount) || 0));

function addItem()          { form.items.push(blank()); }
function removeItem(i: number) { form.items.splice(i, 1); }

function submit() {
    isEdit ? form.put(bills.update.url(props.bill!.id)) : form.post(bills.store.url());
}

function fmt(v: number) { return v.toLocaleString('en-ZM', { minimumFractionDigits: 2 }); }
</script>

<template>
    <Head :title="isEdit ? 'Edit Bill' : 'New Bill'" />
    <AppLayout>
        <form @submit.prevent="submit">
            <div class="max-w-9xl mx-auto p-6 space-y-6">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Bill' : 'New Bill' }}</h1>
                    <Button type="button" variant="outline" @click="router.get(bills.index.url())">Cancel</Button>
                </div>

                <Card>
                    <CardHeader><CardTitle>Bill Details</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 space-y-2">
                            <Label>Supplier <span class="text-destructive">*</span></Label>
                            <select v-model="form.contact_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null" disabled>Select a supplier…</option>
                                <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <InputError :message="form.errors.contact_id" />
                        </div>
                        <div class="space-y-2">
                            <Label for="bill_number">Supplier Bill #</Label>
                            <Input id="bill_number" v-model="form.bill_number" placeholder="Supplier's invoice number" />
                        </div>
                        <div class="space-y-2">
                            <Label for="reference">Reference</Label>
                            <Input id="reference" v-model="form.reference" placeholder="PO or reference" />
                        </div>
                        <div class="space-y-2">
                            <Label for="issue_date">Bill Date <span class="text-destructive">*</span></Label>
                            <Input id="issue_date" v-model="form.issue_date" type="date" />
                            <InputError :message="form.errors.issue_date" />
                        </div>
                        <div class="space-y-2">
                            <Label for="due_date">Due Date <span class="text-destructive">*</span></Label>
                            <Input id="due_date" v-model="form.due_date" type="date" />
                            <InputError :message="form.errors.due_date" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Line Items</CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <div class="hidden md:grid gap-2 text-xs font-medium text-muted-foreground px-1"
                            :class="vsdcEnabled
                                ? 'grid-cols-[1fr_110px_180px_140px_120px_100px_120px_100px_110px_36px]'
                                : 'grid-cols-[1fr_140px_120px_100px_120px_100px_110px_36px]'">
                            <span>Description</span>
                            <template v-if="vsdcEnabled"><span>Type</span><span>ZRA Code</span></template>
                            <span>Expense Account</span><span>Tax Rate</span>
                            <span class="text-right">Qty</span><span class="text-right">Unit Price</span>
                            <span class="text-right">Disc %</span><span class="text-right">Total</span><span></span>
                        </div>

                        <div v-for="(item, i) in form.items" :key="i"
                            class="grid grid-cols-1 gap-2 items-start border-b pb-3 last:border-0"
                            :class="vsdcEnabled
                                ? 'md:grid-cols-[1fr_110px_180px_140px_120px_100px_120px_100px_110px_36px]'
                                : 'md:grid-cols-[1fr_140px_120px_100px_120px_100px_110px_36px]'">
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

                            <select v-model="item.account_id" class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">No account</option>
                                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                            </select>
                            <select v-model="item.tax_rate_id" class="h-9 w-full rounded-md border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">No tax</option>
                                <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.code }} ({{ t.rate }}%)</option>
                            </select>
                            <Input v-model.number="item.quantity" type="number" min="0.001" step="0.001" class="text-right" />
                            <Input v-model.number="item.unit_price" type="number" min="0" step="0.01" class="text-right" />
                            <Input v-model.number="item.discount_percent" type="number" min="0" max="100" step="0.01" class="text-right" placeholder="0" />
                            <div class="flex items-center justify-end text-sm font-medium pr-1">ZMW {{ fmt(lineCalcs[i].total) }}</div>
                            <Button type="button" variant="ghost" size="icon" class="text-muted-foreground hover:text-destructive" :disabled="form.items.length === 1" @click="removeItem(i)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>

                        <Button type="button" variant="outline" size="sm" @click="addItem"><Plus class="mr-2 h-4 w-4" /> Add Line</Button>

                        <div class="flex justify-end pt-4">
                            <dl class="w-64 space-y-1 text-sm">
                                <div class="flex justify-between"><dt class="text-muted-foreground">Subtotal</dt><dd class="font-medium">ZMW {{ fmt(subtotal) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-muted-foreground">VAT (Input)</dt><dd class="font-medium">ZMW {{ fmt(taxTotal) }}</dd></div>
                                <div class="flex justify-between items-center">
                                    <dt class="text-muted-foreground">Discount</dt>
                                    <dd><Input v-model.number="form.discount_amount" type="number" min="0" step="0.01" class="h-7 w-28 text-right text-sm" placeholder="0.00" /></dd>
                                </div>
                                <div class="flex justify-between border-t pt-2 font-bold text-base"><dt>Total</dt><dd>ZMW {{ fmt(total) }}</dd></div>
                            </dl>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="pt-4">
                        <div class="space-y-2">
                            <Label for="notes">Notes</Label>
                            <textarea id="notes" v-model="form.notes" rows="2" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" placeholder="Internal notes…" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-3">
                    <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : isEdit ? 'Update Bill' : 'Save as Draft' }}</Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
