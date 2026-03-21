<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Pencil, Printer, XCircle } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import * as bills from '@/routes/bills';

interface Item { id: number; description: string; quantity: string; unit_price: string; discount_percent: string; subtotal: string; tax_amount: string; total: string; tax_rate: { name: string }|null; account: { code: string; name: string }|null }
interface Bill { id: number; bill_number: string|null; status: string; issue_date: string; due_date: string; reference: string|null; notes: string|null; subtotal: string; tax_amount: string; discount_amount: string; total: string; amount_paid: string; amount_due: string; contact: { name: string; tpin: string|null }; items: Item[] }

const props = defineProps<{ bill: Bill; company: { name: string } }>();

const statusVariant: Record<string, 'default'|'secondary'|'destructive'|'outline'> = {
    paid: 'default', approved: 'secondary', partial: 'secondary',
    overdue: 'destructive', draft: 'outline', void: 'outline',
};

function fmt(v: string|number) { return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 }); }
function approveBill() { if (confirm('Approve this bill and post to accounts payable?')) router.post(bills.approve.url(props.bill.id)); }
function voidBill()    { if (confirm('Void this bill? A reversal journal entry will be created.')) router.post(bills.voidMethod.url(props.bill.id)); }
</script>

<template>
    <Head :title="`Bill ${bill.bill_number ?? bill.id}`" />
    <AppLayout>
        <div class="max-w-4xl mx-auto p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold font-mono">{{ bill.bill_number ?? `BILL-${bill.id}` }}</h1>
                    <Badge :variant="statusVariant[bill.status] ?? 'outline'" class="capitalize">{{ bill.status }}</Badge>
                </div>
                <div class="flex gap-2">
                    <a :href="`/bills/${bill.id}/print`" target="_blank">
                        <Button variant="outline" size="sm">
                            <Printer class="mr-2 h-4 w-4" /> Print
                        </Button>
                    </a>
                    <Button v-if="bill.status === 'draft'" variant="outline" as-child>
                        <Link :href="bills.edit.url(bill.id)"><Pencil class="mr-2 h-4 w-4" /> Edit</Link>
                    </Button>
                    <Button v-if="bill.status === 'draft'" @click="approveBill">
                        <CheckCircle2 class="mr-2 h-4 w-4" /> Approve
                    </Button>
                    <Button v-if="!['void','paid'].includes(bill.status)" variant="destructive" @click="voidBill">
                        <XCircle class="mr-2 h-4 w-4" /> Void
                    </Button>
                </div>
            </div>

            <Card>
                <CardContent class="p-8 space-y-8">
                    <div class="flex justify-between">
                        <div class="space-y-0.5">
                            <p class="font-bold text-lg">{{ company.name }}</p>
                            <p class="text-sm text-muted-foreground">Bill from: <span class="font-medium text-foreground">{{ bill.contact.name }}</span></p>
                            <p v-if="bill.contact.tpin" class="text-sm text-muted-foreground">Supplier TPIN: {{ bill.contact.tpin }}</p>
                        </div>
                        <div class="text-right space-y-0.5">
                            <p class="text-3xl font-bold text-primary">BILL</p>
                            <p v-if="bill.bill_number" class="font-mono font-semibold">{{ bill.bill_number }}</p>
                            <p class="text-sm text-muted-foreground">Date: {{ new Date(bill.issue_date).toLocaleDateString('en-ZM', { day:'numeric', month:'long', year:'numeric' }) }}</p>
                            <p class="text-sm text-muted-foreground">Due: {{ new Date(bill.due_date).toLocaleDateString('en-ZM', { day:'numeric', month:'long', year:'numeric' }) }}</p>
                            <p v-if="bill.reference" class="text-sm text-muted-foreground">Ref: {{ bill.reference }}</p>
                        </div>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="pb-2 font-medium">Description</th>
                                <th class="pb-2 font-medium text-right w-16">Qty</th>
                                <th class="pb-2 font-medium text-right w-28">Unit Price</th>
                                <th class="pb-2 font-medium text-right w-24">Tax</th>
                                <th class="pb-2 font-medium text-right w-28">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in bill.items" :key="item.id" class="border-b last:border-0">
                                <td class="py-2">
                                    {{ item.description }}
                                    <span v-if="item.account" class="block text-xs text-muted-foreground">{{ item.account.code }} — {{ item.account.name }}</span>
                                </td>
                                <td class="py-2 text-right">{{ item.quantity }}</td>
                                <td class="py-2 text-right">{{ fmt(item.unit_price) }}</td>
                                <td class="py-2 text-right text-muted-foreground">{{ item.tax_rate?.name ?? '—' }}</td>
                                <td class="py-2 text-right font-medium">ZMW {{ fmt(item.total) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-end">
                        <dl class="w-64 space-y-1.5 text-sm">
                            <div class="flex justify-between"><dt class="text-muted-foreground">Subtotal</dt><dd>ZMW {{ fmt(bill.subtotal) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-muted-foreground">Input VAT</dt><dd>ZMW {{ fmt(bill.tax_amount) }}</dd></div>
                            <div v-if="Number(bill.discount_amount) > 0" class="flex justify-between"><dt class="text-muted-foreground">Discount</dt><dd class="text-red-600">− ZMW {{ fmt(bill.discount_amount) }}</dd></div>
                            <Separator />
                            <div class="flex justify-between font-bold text-base"><dt>Total</dt><dd>ZMW {{ fmt(bill.total) }}</dd></div>
                            <div v-if="Number(bill.amount_paid) > 0" class="flex justify-between text-green-600"><dt>Paid</dt><dd>ZMW {{ fmt(bill.amount_paid) }}</dd></div>
                            <div v-if="Number(bill.amount_due) > 0" class="flex justify-between font-semibold text-amber-600"><dt>Amount Due</dt><dd>ZMW {{ fmt(bill.amount_due) }}</dd></div>
                        </dl>
                    </div>
                    <p v-if="bill.notes" class="text-sm text-muted-foreground border-t pt-4">{{ bill.notes }}</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
