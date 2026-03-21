<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Pencil, Plus } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as contacts from '@/routes/contacts';
import * as invoices from '@/routes/invoices';

interface Invoice { id: number; invoice_number: string; status: string; total: string; due_date: string; amount_due: string }
interface Contact {
    id: number; name: string; type: string; email: string | null; phone: string | null;
    tpin: string | null; address: string | null; city: string | null;
    withholding_tax_applicable: boolean; notes: string | null;
    invoices: Invoice[];
}

defineProps<{
    contact: Contact;
    stats: { total_invoiced: number; total_paid: number; outstanding: number };
}>();

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    paid: 'default', sent: 'secondary', partial: 'secondary',
    overdue: 'destructive', draft: 'outline', void: 'outline',
};

function fmt(v: string | number) {
    return 'ZMW ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head :title="contact.name" />
    <AppLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">

            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold">{{ contact.name }}</h1>
                    <Badge variant="secondary" class="mt-1 capitalize">{{ contact.type }}</Badge>
                </div>
                <Button variant="outline" as-child>
                    <Link :href="contacts.edit.url(contact.id)">
                        <Pencil class="mr-2 h-4 w-4" /> Edit
                    </Link>
                </Button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <Card>
                    <CardContent class="pt-4">
                        <p class="text-xs text-muted-foreground">Total Invoiced</p>
                        <p class="text-xl font-bold">{{ fmt(stats.total_invoiced) }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-4">
                        <p class="text-xs text-muted-foreground">Total Paid</p>
                        <p class="text-xl font-bold text-green-600">{{ fmt(stats.total_paid) }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-4">
                        <p class="text-xs text-muted-foreground">Outstanding</p>
                        <p class="text-xl font-bold" :class="stats.outstanding > 0 ? 'text-amber-600' : ''">
                            {{ fmt(stats.outstanding) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Details -->
            <Card>
                <CardHeader><CardTitle>Contact Details</CardTitle></CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-y-2 text-sm">
                        <dt class="text-muted-foreground">Email</dt>       <dd>{{ contact.email ?? '—' }}</dd>
                        <dt class="text-muted-foreground">Phone</dt>       <dd>{{ contact.phone ?? '—' }}</dd>
                        <dt class="text-muted-foreground">TPIN</dt>        <dd class="font-mono">{{ contact.tpin ?? '—' }}</dd>
                        <dt class="text-muted-foreground">Address</dt>     <dd>{{ [contact.address, contact.city].filter(Boolean).join(', ') || '—' }}</dd>
                        <dt class="text-muted-foreground">WHT</dt>         <dd>{{ contact.withholding_tax_applicable ? 'Applicable' : 'Not applicable' }}</dd>
                        <dt class="text-muted-foreground">Notes</dt>       <dd>{{ contact.notes ?? '—' }}</dd>
                    </dl>
                </CardContent>
            </Card>

            <!-- Invoices -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Invoices</CardTitle>
                    <Button size="sm" as-child>
                        <Link :href="invoices.create.url() + '?contact_id=' + contact.id">
                            <Plus class="mr-1 h-4 w-4" /> New Invoice
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Invoice #</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                                <TableHead class="text-right">Due</TableHead>
                                <TableHead>Due Date</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="inv in contact.invoices" :key="inv.id">
                                <TableCell>
                                    <Link :href="invoices.show.url(inv.id)" class="font-mono font-medium hover:underline">
                                        {{ inv.invoice_number }}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="statusVariant[inv.status] ?? 'outline'" class="capitalize">{{ inv.status }}</Badge>
                                </TableCell>
                                <TableCell class="text-right">{{ fmt(inv.total) }}</TableCell>
                                <TableCell class="text-right" :class="Number(inv.amount_due) > 0 ? 'text-amber-600 font-medium' : ''">
                                    {{ fmt(inv.amount_due) }}
                                </TableCell>
                                <TableCell class="text-muted-foreground text-sm">{{ new Date(inv.due_date).toLocaleDateString() }}</TableCell>
                            </TableRow>
                            <TableRow v-if="!contact.invoices.length">
                                <TableCell colspan="5" class="py-6 text-center text-muted-foreground">No invoices yet.</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
