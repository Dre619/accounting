<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Contact { id: number; name: string }
interface Account { id: number; code: string; name: string }
interface TaxRate { id: number; name: string; code: string; rate: string }
interface ClsCode { id: number; name: string; hs_code: number }
interface Product { id: number; sku: string | null; name: string; type: string; purchase_account_id: number | null; tax_rate_id: number | null; item_type: 'goods' | 'service'; cls_code_id: number | null; quantity_on_hand: string; average_cost: string }
interface OrderItem { id?: number; description: string; account_id: number | null; product_id: number | null; tax_rate_id: number | null; quantity: number; unit_price: number; discount_percent: number; item_type: 'goods' | 'service'; cls_code_id: number | null }
interface Order { id: number; contact_id: number; reference: string | null; order_date: string; expected_date: string | null; notes: string | null; discount_amount: number; items: (OrderItem & { subtotal: number })[] }

const props = defineProps<{ order: Order | null; contacts: Contact[]; accounts: Account[]; taxRates: TaxRate[]; vsdcEnabled: boolean; goodsCodes: ClsCode[]; serviceCodes: ClsCode[]; products: Product[] }>();

const isEdit = !!props.order;
const today  = new Date().toISOString().slice(0, 10);
const blank  = (): OrderItem => ({ description: '', account_id: null, product_id: null, tax_rate_id: null, quantity: 1, unit_price: 0, discount_percent: 0, item_type: 'goods', cls_code_id: null });

function onProductChange(item: OrderItem) {
    const p = props.products.find(x => x.id === item.product_id);
    if (!p) return;
    item.description = p.name;
    item.account_id  = p.purchase_account_id ?? item.account_id;
    item.tax_rate_id = p.tax_rate_id ?? item.tax_rate_id;
    item.item_type   = p.item_type;
    item.cls_code_id = p.cls_code_id;
    if (Number(p.average_cost) > 0) item.unit_price = Number(p.average_cost);
}

const form = useForm({
    contact_id:      props.order?.contact_id ?? null as number | null,
    reference:       props.order?.reference ?? '',
    order_date:      props.order?.order_date ?? today,
    expected_date:   props.order?.expected_date ?? '',
    notes:           props.order?.notes ?? '',
    discount_amount: props.order?.discount_amount ?? 0,
    items:           props.order?.items?.map(i => ({ id: i.id, description: i.description, account_id: i.account_id, product_id: i.product_id ?? null, tax_rate_id: i.tax_rate_id, quantity: i.quantity, unit_price: i.unit_price, discount_percent: i.discount_percent, item_type: i.item_type ?? 'goods', cls_code_id: i.cls_code_id ?? null })) ?? [blank()],
});

function calcItem(item: OrderItem) {
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
    if (isEdit) {
        form.put(`/purchase-orders/${props.order!.id}`);
    } else {
        form.post('/purchase-orders');
    }
}

function fmt(v: number) { return v.toLocaleString('en-ZM', { minimumFractionDigits: 2 }); }
</script>

<template>
    <Head :title="isEdit ? 'Edit Purchase Order' : 'New Purchase Order'" />
    <AppLayout>
        <form @submit.prevent="submit">
            <div class="max-w-9xl mx-auto p-6 space-y-6">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Purchase Order' : 'New Purchase Order' }}</h1>
                    <Button type="button" variant="outline" @click="router.get('/purchase-orders')">Cancel</Button>
                </div>

                <Card>
                    <CardHeader><CardTitle>Order Details</CardTitle></CardHeader>
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
                            <Label for="order_date">Order Date <span class="text-destructive">*</span></Label>
                            <Input id="order_date" v-model="form.order_date" type="date" />
                            <InputError :message="form.errors.order_date" />
                        </div>
                        <div class="space-y-2">
                            <Label for="expected_date">Expected Date</Label>
                            <Input id="expected_date" v-model="form.expected_date" type="date" />
                            <InputError :message="form.errors.expected_date" />
                        </div>
                        <div class="col-span-2 space-y-2">
                            <Label for="reference">Reference</Label>
                            <Input id="reference" v-model="form.reference" placeholder="Internal reference" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Line Items</CardTitle></CardHeader>
                    <CardContent class="space-y-3">
                        <div class="hidden md:grid gap-2 text-xs font-medium text-muted-foreground px-1 grid-cols-[1fr_140px_120px_100px_120px_100px_110px_36px]">
                            <span>Description</span><span>Account</span><span>Tax Rate</span>
                            <span class="text-right">Qty</span><span class="text-right">Unit Price</span>
                            <span class="text-right">Disc %</span><span class="text-right">Total</span><span></span>
                        </div>

                        <div v-for="(item, i) in form.items" :key="i"
                            class="grid grid-cols-1 gap-2 items-start border-b pb-3 last:border-0 md:grid-cols-[1fr_140px_120px_100px_120px_100px_110px_36px]">
                            <div class="space-y-1">
                                <select v-if="products.length" v-model="item.product_id" @change="onProductChange(item)"
                                    class="h-8 w-full rounded-md border border-input bg-transparent px-2 py-0.5 text-xs text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                    <option :value="null">— Free text / pick a product…</option>
                                    <option v-for="p in products" :key="p.id" :value="p.id">
                                        {{ p.name }}<template v-if="p.type === 'inventory'"> ({{ Number(p.quantity_on_hand) }} in stock)</template>
                                    </option>
                                </select>
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
                                <div class="flex justify-between"><dt class="text-muted-foreground">Subtotal</dt><dd class="font-medium">ZMW {{ fmt(subtotal) }}</dd></div>
                                <div class="flex justify-between"><dt class="text-muted-foreground">VAT</dt><dd class="font-medium">ZMW {{ fmt(taxTotal) }}</dd></div>
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
                    <CardContent class="pt-4 space-y-2">
                        <Label for="notes">Notes</Label>
                        <textarea id="notes" v-model="form.notes" rows="3"
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            placeholder="Delivery instructions, terms…" />
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : isEdit ? 'Update Order' : 'Save as Draft' }}
                    </Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
