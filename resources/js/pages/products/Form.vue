<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Account { id: number; code: string; name: string; type: string }
interface TaxRate { id: number; name: string; code: string; rate: string }
interface ClsCode { id: number; name: string; hs_code: number }
interface Product {
    id: number; sku: string | null; name: string; description: string | null;
    type: 'inventory' | 'service' | 'non_inventory'; unit_of_measure: string | null;
    sales_price: string; sales_account_id: number | null; purchase_account_id: number | null;
    inventory_account_id: number | null; cogs_account_id: number | null; tax_rate_id: number | null;
    item_type: 'goods' | 'service'; cls_code_id: number | null; reorder_point: string | null; is_active: boolean;
}

const props = defineProps<{
    product: Product | null;
    accounts: Account[];
    taxRates: TaxRate[];
    goodsCodes: ClsCode[];
    serviceCodes: ClsCode[];
}>();

const isEdit = !!props.product;

const form = useForm({
    name:                 props.product?.name ?? '',
    sku:                  props.product?.sku ?? '',
    description:          props.product?.description ?? '',
    type:                 props.product?.type ?? 'inventory',
    unit_of_measure:      props.product?.unit_of_measure ?? 'each',
    sales_price:          props.product ? Number(props.product.sales_price) : 0,
    sales_account_id:     props.product?.sales_account_id ?? null,
    purchase_account_id:  props.product?.purchase_account_id ?? null,
    inventory_account_id: props.product?.inventory_account_id ?? null,
    cogs_account_id:      props.product?.cogs_account_id ?? null,
    tax_rate_id:          props.product?.tax_rate_id ?? null,
    item_type:            props.product?.item_type ?? 'goods',
    cls_code_id:          props.product?.cls_code_id ?? null,
    reorder_point:        props.product?.reorder_point ? Number(props.product.reorder_point) : null,
    is_active:            props.product?.is_active ?? true,
    opening_quantity:     0,
    opening_cost:         0,
});

const isInventory = computed(() => form.type === 'inventory');

const incomeAccounts  = computed(() => props.accounts.filter(a => a.type === 'income'));
const expenseAccounts = computed(() => props.accounts.filter(a => a.type === 'expense'));
const assetAccounts   = computed(() => props.accounts.filter(a => a.type === 'asset'));
const clsOptions      = computed(() => (form.item_type === 'goods' ? props.goodsCodes : props.serviceCodes));

function submit() {
    if (isEdit) {
        form.put(`/products/${props.product!.id}`);
    } else {
        form.post('/products');
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Product' : 'New Product'" />
    <AppLayout>
        <form @submit.prevent="submit">
            <div class="max-w-4xl mx-auto p-6 space-y-6">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Product' : 'New Product' }}</h1>
                    <Button type="button" variant="outline" @click="router.get('/products')">Cancel</Button>
                </div>

                <Card>
                    <CardHeader><CardTitle>Details</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 space-y-2">
                            <Label for="name">Name <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="e.g. Steel Bolt M8" />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="sku">SKU</Label>
                            <Input id="sku" v-model="form.sku" placeholder="Optional code" />
                            <InputError :message="form.errors.sku" />
                        </div>

                        <div class="space-y-2">
                            <Label>Type <span class="text-destructive">*</span></Label>
                            <select v-model="form.type"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option value="inventory">Inventory (tracks stock)</option>
                                <option value="service">Service</option>
                                <option value="non_inventory">Non-inventory</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="uom">Unit of measure</Label>
                            <Input id="uom" v-model="form.unit_of_measure" placeholder="each, kg, box…" />
                        </div>

                        <div class="space-y-2">
                            <Label for="price">Sales price</Label>
                            <Input id="price" v-model.number="form.sales_price" type="number" min="0" step="0.01" />
                        </div>

                        <div class="col-span-2 space-y-2">
                            <Label for="desc">Description</Label>
                            <textarea id="desc" v-model="form.description" rows="2"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Accounting &amp; Tax</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>Sales (income) account</Label>
                            <select v-model="form.sales_account_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">— Select —</option>
                                <option v-for="a in incomeAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label>Default tax rate</Label>
                            <select v-model="form.tax_rate_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">No tax</option>
                                <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.code }} ({{ t.rate }}%)</option>
                            </select>
                        </div>

                        <template v-if="isInventory">
                            <div class="space-y-2">
                                <Label>Inventory (asset) account</Label>
                                <select v-model="form.inventory_account_id"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                    <option :value="null">— Default (1300) —</option>
                                    <option v-for="a in assetAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label>Cost of goods sold account</Label>
                                <select v-model="form.cogs_account_id"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                    <option :value="null">— Default (5000) —</option>
                                    <option v-for="a in expenseAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                                </select>
                            </div>
                        </template>

                        <div class="space-y-2">
                            <Label>Purchase (expense) account</Label>
                            <select v-model="form.purchase_account_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">— Select —</option>
                                <option v-for="a in expenseAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label>ZRA item type</Label>
                            <select v-model="form.item_type"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option value="goods">Goods</option>
                                <option value="service">Service</option>
                            </select>
                        </div>

                        <div class="col-span-2 space-y-2">
                            <Label>ZRA classification code</Label>
                            <select v-model="form.cls_code_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">— None —</option>
                                <option v-for="c in clsOptions" :key="c.id" :value="c.id">{{ c.hs_code }} — {{ c.name }}</option>
                            </select>
                        </div>
                    </CardContent>
                </Card>

                <!-- Inventory-only: reorder point + opening stock -->
                <Card v-if="isInventory">
                    <CardHeader><CardTitle>Stock</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="reorder">Reorder point</Label>
                            <Input id="reorder" v-model.number="form.reorder_point" type="number" min="0" step="0.001"
                                placeholder="Alert when on-hand falls to this" />
                        </div>

                        <template v-if="!isEdit">
                            <div class="space-y-2">
                                <Label for="oq">Opening quantity</Label>
                                <Input id="oq" v-model.number="form.opening_quantity" type="number" min="0" step="0.001" />
                            </div>
                            <div class="space-y-2 col-start-1">
                                <Label for="oc">Opening unit cost</Label>
                                <Input id="oc" v-model.number="form.opening_cost" type="number" min="0" step="0.01" />
                                <p class="text-xs text-muted-foreground">Posts DR Inventory / CR Retained Earnings for the opening value.</p>
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-input" />
                        Active
                    </label>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : isEdit ? 'Update Product' : 'Save Product' }}
                    </Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
