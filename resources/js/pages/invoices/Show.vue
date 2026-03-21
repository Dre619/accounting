<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileCheck2, FileText, Mail, Pencil, Printer, Send, XCircle } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import * as invoices from '@/routes/invoices';

interface TaxRate { name: string; rate: string }
interface Account { code: string; name: string }
interface Item {
    id: number; description: string; quantity: string; unit_price: string;
    discount_percent: string; subtotal: string; tax_amount: string; total: string;
    tax_rate: TaxRate | null; account: Account | null;
}
interface Contact { id: number; name: string; email: string | null; address: string | null; city: string | null; tpin: string | null }
interface Invoice {
    id: number; invoice_number: string; status: string;
    issue_date: string; due_date: string; reference: string | null;
    notes: string | null; footer: string | null;
    subtotal: string; tax_amount: string; discount_amount: string; total: string;
    amount_paid: string; amount_due: string;
    contact: Contact; items: Item[];
    created_by: { name: string } | null;
    zra_submitted_at: string | null;
    zra_rcpt_no: number | null;
    zra_rcpt_sign: string | null;
    zra_sdc_id: string | null;
    zra_mrc_no: string | null;
    zra_internal_data: string | null;
    zra_invoice_path: string | null;
}
interface Company { name: string; address: string | null; city: string | null; tpin: string | null; vat_number: string | null; email: string | null; phone: string | null }

const props = defineProps<{ invoice: Invoice; company: Company }>();

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    paid: 'default', sent: 'secondary', partial: 'secondary',
    overdue: 'destructive', draft: 'outline', void: 'outline',
};

function fmt(v: string | number) {
    return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

function sendInvoice() {
    if (confirm('Mark this invoice as sent?')) {
        router.post(invoices.send.url(props.invoice.id));
    }
}

function voidInvoice() {
    if (confirm('Void this invoice? This creates a reversal journal entry and cannot be undone.')) {
        router.post(invoices.voidMethod.url(props.invoice.id));
    }
}

function submitToZra() {
    if (confirm(`Submit invoice ${props.invoice.invoice_number} to ZRA Smart Invoice?`)) {
        router.post(`/invoices/${props.invoice.id}/zra-submit`);
    }
}

function emailInvoice() {
    if (!props.invoice.contact.email) {
        alert('This contact has no email address on file.');
        return;
    }
    if (confirm(`Email invoice ${props.invoice.invoice_number} to ${props.invoice.contact.email}?`)) {
        router.post(`/invoices/${props.invoice.id}/email`);
    }
}
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_number}`" />
    <AppLayout>
        <div class="max-w-4xl mx-auto p-6 space-y-4">

            <!-- Actions bar -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold font-mono">{{ invoice.invoice_number }}</h1>
                    <Badge :variant="statusVariant[invoice.status] ?? 'outline'" class="capitalize text-sm">
                        {{ invoice.status }}
                    </Badge>
                </div>
                <div class="flex gap-2">
                    <a :href="`/invoices/${invoice.id}/print`" target="_blank">
                        <Button variant="outline" size="sm">
                            <Printer class="mr-2 h-4 w-4" /> Print
                        </Button>
                    </a>
                    <Button v-if="invoice.status === 'draft'" variant="outline" as-child>
                        <Link :href="invoices.edit.url(invoice.id)">
                            <Pencil class="mr-2 h-4 w-4" /> Edit
                        </Link>
                    </Button>
                    <Button v-if="['draft','sent'].includes(invoice.status)" @click="sendInvoice">
                        <Send class="mr-2 h-4 w-4" /> Mark as Sent
                    </Button>
                    <Button v-if="invoice.contact.email && ['draft','sent'].includes(invoice.status)"
                        variant="outline" @click="emailInvoice">
                        <Mail class="mr-2 h-4 w-4" /> Email
                    </Button>
                    <a v-if="invoice.zra_invoice_path"
                        :href="`/storage/${invoice.zra_invoice_path}`" target="_blank">
                        <Button variant="outline" size="sm">
                            <FileText class="mr-2 h-4 w-4" /> ZRA Invoice
                        </Button>
                    </a>
                    <Button v-if="['sent','partial','paid'].includes(invoice.status) && !invoice.zra_submitted_at"
                        variant="outline" @click="submitToZra">
                        <FileCheck2 class="mr-2 h-4 w-4" /> Submit to ZRA
                    </Button>
                    <Button v-if="!['void','paid'].includes(invoice.status)"
                        variant="destructive" @click="voidInvoice">
                        <XCircle class="mr-2 h-4 w-4" /> Void
                    </Button>
                </div>
            </div>

            <!-- Invoice document -->
            <Card>
                <CardContent class="p-8 space-y-8">

                    <!-- Header: from / to -->
                    <div class="flex justify-between">
                        <div class="space-y-0.5">
                            <p class="font-bold text-lg">{{ company.name }}</p>
                            <p v-if="company.address" class="text-sm text-muted-foreground">{{ company.address }}{{ company.city ? ', ' + company.city : '' }}</p>
                            <p v-if="company.tpin" class="text-sm text-muted-foreground">TPIN: {{ company.tpin }}</p>
                            <p v-if="company.vat_number" class="text-sm text-muted-foreground">VAT: {{ company.vat_number }}</p>
                            <p v-if="company.email" class="text-sm text-muted-foreground">{{ company.email }}</p>
                        </div>
                        <div class="text-right space-y-0.5">
                            <p class="text-3xl font-bold text-primary">INVOICE</p>
                            <p class="font-mono font-semibold">{{ invoice.invoice_number }}</p>
                            <p class="text-sm text-muted-foreground">Issued: {{ new Date(invoice.issue_date).toLocaleDateString('en-ZM', { day:'numeric', month:'long', year:'numeric' }) }}</p>
                            <p class="text-sm" :class="invoice.status === 'overdue' ? 'text-destructive font-medium' : 'text-muted-foreground'">
                                Due: {{ new Date(invoice.due_date).toLocaleDateString('en-ZM', { day:'numeric', month:'long', year:'numeric' }) }}
                            </p>
                            <p v-if="invoice.reference" class="text-sm text-muted-foreground">Ref: {{ invoice.reference }}</p>
                        </div>
                    </div>

                    <!-- Bill to -->
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-1">Bill To</p>
                        <p class="font-semibold">{{ invoice.contact.name }}</p>
                        <p v-if="invoice.contact.address" class="text-sm text-muted-foreground">{{ invoice.contact.address }}</p>
                        <p v-if="invoice.contact.tpin" class="text-sm text-muted-foreground">TPIN: {{ invoice.contact.tpin }}</p>
                        <p v-if="invoice.contact.email" class="text-sm text-muted-foreground">{{ invoice.contact.email }}</p>
                    </div>

                    <!-- Line items table -->
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="pb-2 font-medium">Description</th>
                                <th class="pb-2 font-medium text-right w-16">Qty</th>
                                <th class="pb-2 font-medium text-right w-28">Unit Price</th>
                                <th class="pb-2 font-medium text-right w-20">Disc%</th>
                                <th class="pb-2 font-medium text-right w-24">Tax</th>
                                <th class="pb-2 font-medium text-right w-28">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in invoice.items" :key="item.id" class="border-b last:border-0">
                                <td class="py-2">
                                    {{ item.description }}
                                    <span v-if="item.account" class="block text-xs text-muted-foreground">{{ item.account.code }} — {{ item.account.name }}</span>
                                </td>
                                <td class="py-2 text-right">{{ item.quantity }}</td>
                                <td class="py-2 text-right">{{ fmt(item.unit_price) }}</td>
                                <td class="py-2 text-right text-muted-foreground">
                                    {{ Number(item.discount_percent) > 0 ? item.discount_percent + '%' : '—' }}
                                </td>
                                <td class="py-2 text-right text-muted-foreground">
                                    {{ item.tax_rate ? item.tax_rate.name : '—' }}
                                </td>
                                <td class="py-2 text-right font-medium">ZMW {{ fmt(item.total) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Totals -->
                    <div class="flex justify-end">
                        <dl class="w-64 space-y-1.5 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">Subtotal</dt>
                                <dd>ZMW {{ fmt(invoice.subtotal) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-muted-foreground">VAT</dt>
                                <dd>ZMW {{ fmt(invoice.tax_amount) }}</dd>
                            </div>
                            <div v-if="Number(invoice.discount_amount) > 0" class="flex justify-between">
                                <dt class="text-muted-foreground">Discount</dt>
                                <dd class="text-red-600">− ZMW {{ fmt(invoice.discount_amount) }}</dd>
                            </div>
                            <Separator />
                            <div class="flex justify-between font-bold text-base">
                                <dt>Total</dt>
                                <dd>ZMW {{ fmt(invoice.total) }}</dd>
                            </div>
                            <div v-if="Number(invoice.amount_paid) > 0" class="flex justify-between text-green-600">
                                <dt>Paid</dt>
                                <dd>ZMW {{ fmt(invoice.amount_paid) }}</dd>
                            </div>
                            <div v-if="Number(invoice.amount_due) > 0" class="flex justify-between font-semibold text-amber-600">
                                <dt>Amount Due</dt>
                                <dd>ZMW {{ fmt(invoice.amount_due) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- ZRA Smart Invoice receipt data -->
                    <div v-if="invoice.zra_submitted_at" class="border rounded-md p-4 bg-muted/40 text-sm space-y-1">
                        <p class="font-semibold text-xs uppercase tracking-wider text-muted-foreground mb-2">ZRA Smart Invoice</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                            <span class="text-muted-foreground">Receipt No.</span>
                            <span class="font-mono">{{ invoice.zra_rcpt_no }}</span>
                            <span class="text-muted-foreground">SDC ID</span>
                            <span class="font-mono">{{ invoice.zra_sdc_id }}</span>
                            <span class="text-muted-foreground">MRC No.</span>
                            <span class="font-mono">{{ invoice.zra_mrc_no }}</span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-2 break-all">
                            <span class="font-medium">Signature: </span>{{ invoice.zra_rcpt_sign }}
                        </p>
                    </div>

                    <!-- Notes / Footer -->
                    <div v-if="invoice.notes || invoice.footer" class="space-y-2 text-sm text-muted-foreground border-t pt-4">
                        <p v-if="invoice.notes"><span class="font-medium text-foreground">Notes:</span> {{ invoice.notes }}</p>
                        <p v-if="invoice.footer">{{ invoice.footer }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
