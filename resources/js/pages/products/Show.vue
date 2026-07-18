<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, SlidersHorizontal } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Ref { id: number; code: string; name: string }
interface Movement {
    id: number; type: string; quantity: string; unit_cost: string; total_cost: string;
    running_qty: string; movement_date: string; description: string | null;
    warehouse: { id: number; name: string } | null;
}
interface Product {
    id: number; sku: string | null; name: string; description: string | null; type: string;
    unit_of_measure: string | null; sales_price: string; quantity_on_hand: string; average_cost: string;
    reorder_point: string | null; is_active: boolean;
    sales_account: Ref | null; purchase_account: Ref | null;
    inventory_account: Ref | null; cogs_account: Ref | null;
    tax_rate: { id: number; name: string; rate: string } | null;
}

const props = defineProps<{ product: Product; movements: Movement[]; stockValue: number }>();

const isInventory = props.product.type === 'inventory';
const showAdjust = ref(false);

const adjustForm = useForm({
    new_quantity: Number(props.product.quantity_on_hand),
    reason: '',
    date: new Date().toISOString().slice(0, 10),
});

function fmt(v: string | number) {
    return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

const typeLabel: Record<string, string> = {
    purchase: 'Purchase', sale: 'Sale', adjustment: 'Adjustment',
    opening: 'Opening', transfer: 'Transfer', return: 'Return',
};

function submitAdjust() {
    adjustForm.post(`/products/${props.product.id}/adjust`, {
        onSuccess: () => { showAdjust.value = false; },
    });
}
</script>

<template>
    <Head :title="product.name" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-5xl mx-auto w-full">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        {{ product.name }}
                        <Badge v-if="!product.is_active" variant="outline">Inactive</Badge>
                    </h1>
                    <p class="text-sm text-muted-foreground capitalize">
                        {{ product.type.replace('_', ' ') }}
                        <span v-if="product.sku"> · SKU {{ product.sku }}</span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="isInventory" variant="outline" @click="showAdjust = !showAdjust">
                        <SlidersHorizontal class="mr-2 h-4 w-4" /> Adjust stock
                    </Button>
                    <Button as-child>
                        <Link :href="`/products/${product.id}/edit`"><Pencil class="mr-2 h-4 w-4" /> Edit</Link>
                    </Button>
                </div>
            </div>

            <!-- Stock summary -->
            <div v-if="isInventory" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <Card><CardContent class="pt-5">
                    <p class="text-xs text-muted-foreground">On hand</p>
                    <p class="text-2xl font-bold">{{ Number(product.quantity_on_hand) }}
                        <span class="text-sm font-normal text-muted-foreground">{{ product.unit_of_measure }}</span></p>
                </CardContent></Card>
                <Card><CardContent class="pt-5">
                    <p class="text-xs text-muted-foreground">Average cost</p>
                    <p class="text-2xl font-bold">{{ fmt(product.average_cost) }}</p>
                </CardContent></Card>
                <Card><CardContent class="pt-5">
                    <p class="text-xs text-muted-foreground">Stock value</p>
                    <p class="text-2xl font-bold">{{ fmt(stockValue) }}</p>
                </CardContent></Card>
                <Card><CardContent class="pt-5">
                    <p class="text-xs text-muted-foreground">Reorder point</p>
                    <p class="text-2xl font-bold">{{ product.reorder_point !== null ? Number(product.reorder_point) : '—' }}</p>
                </CardContent></Card>
            </div>

            <!-- Adjust panel -->
            <Card v-if="showAdjust && isInventory">
                <CardHeader><CardTitle>Adjust stock on hand</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end" @submit.prevent="submitAdjust">
                        <div class="space-y-2">
                            <Label for="nq">New quantity on hand</Label>
                            <Input id="nq" v-model.number="adjustForm.new_quantity" type="number" min="0" step="0.001" />
                            <InputError :message="adjustForm.errors.new_quantity" />
                        </div>
                        <div class="space-y-2">
                            <Label for="rn">Reason</Label>
                            <Input id="rn" v-model="adjustForm.reason" placeholder="Stock take, breakage…" />
                        </div>
                        <div class="space-y-2">
                            <Label for="dt">Date</Label>
                            <Input id="dt" v-model="adjustForm.date" type="date" />
                        </div>
                        <div class="md:col-span-3 flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="showAdjust = false">Cancel</Button>
                            <Button type="submit" :disabled="adjustForm.processing">Post adjustment</Button>
                        </div>
                        <p class="md:col-span-3 text-xs text-muted-foreground">
                            The difference is valued at the current average cost and posted between Inventory and COGS.
                        </p>
                    </form>
                </CardContent>
            </Card>

            <!-- Details -->
            <Card>
                <CardHeader><CardTitle>Details</CardTitle></CardHeader>
                <CardContent class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div><dt class="text-muted-foreground">Sales price</dt><dd class="font-medium">{{ fmt(product.sales_price) }}</dd></div>
                    <div><dt class="text-muted-foreground">Tax rate</dt><dd class="font-medium">{{ product.tax_rate ? `${product.tax_rate.name} (${product.tax_rate.rate}%)` : '—' }}</dd></div>
                    <div><dt class="text-muted-foreground">Sales account</dt><dd class="font-medium">{{ product.sales_account ? `${product.sales_account.code} — ${product.sales_account.name}` : '—' }}</dd></div>
                    <div v-if="isInventory"><dt class="text-muted-foreground">Inventory account</dt><dd class="font-medium">{{ product.inventory_account ? `${product.inventory_account.code} — ${product.inventory_account.name}` : '1300 (default)' }}</dd></div>
                    <div v-if="isInventory"><dt class="text-muted-foreground">COGS account</dt><dd class="font-medium">{{ product.cogs_account ? `${product.cogs_account.code} — ${product.cogs_account.name}` : '5000 (default)' }}</dd></div>
                    <div><dt class="text-muted-foreground">Purchase account</dt><dd class="font-medium">{{ product.purchase_account ? `${product.purchase_account.code} — ${product.purchase_account.name}` : '—' }}</dd></div>
                </CardContent>
            </Card>

            <!-- Movement ledger -->
            <Card v-if="isInventory">
                <CardHeader><CardTitle>Stock movements</CardTitle></CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Note</TableHead>
                                <TableHead class="text-right">Qty</TableHead>
                                <TableHead class="text-right">Unit cost</TableHead>
                                <TableHead class="text-right">On hand</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="m in movements" :key="m.id">
                                <TableCell class="text-muted-foreground">{{ m.movement_date }}</TableCell>
                                <TableCell><Badge variant="outline">{{ typeLabel[m.type] ?? m.type }}</Badge></TableCell>
                                <TableCell class="text-muted-foreground">{{ m.description ?? '—' }}</TableCell>
                                <TableCell class="text-right" :class="Number(m.quantity) < 0 ? 'text-destructive' : 'text-emerald-600'">
                                    {{ Number(m.quantity) > 0 ? '+' : '' }}{{ Number(m.quantity) }}
                                </TableCell>
                                <TableCell class="text-right text-muted-foreground">{{ fmt(m.unit_cost) }}</TableCell>
                                <TableCell class="text-right font-medium">{{ Number(m.running_qty) }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!movements.length">
                                <TableCell colspan="6" class="py-8 text-center text-muted-foreground">No stock movements yet.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <div>
                <Button variant="ghost" @click="router.get('/products')">← Back to products</Button>
            </div>
        </div>
    </AppLayout>
</template>
