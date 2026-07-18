<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Mail, Pencil, Phone, Plus, StickyNote, Trash2, Users } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as contacts from '@/routes/contacts';
import * as invoices from '@/routes/invoices';

interface Invoice { id: number; invoice_number: string; status: string; total: string; due_date: string; amount_due: string }
interface Activity {
    id: number; type: 'note' | 'call' | 'email' | 'meeting'; body: string;
    occurred_at: string; user: { id: number; name: string } | null;
}
interface Opportunity { id: number; title: string; stage: string; estimated_value: string }
interface Task { id: number; title: string; due_date: string | null; assignee: { name: string } | null }
interface Contact {
    id: number; name: string; type: string; lifecycle_stage: string; email: string | null; phone: string | null;
    tpin: string | null; address: string | null; city: string | null;
    withholding_tax_applicable: boolean; notes: string | null;
    invoices: Invoice[]; activities: Activity[]; opportunities: Opportunity[]; tasks: Task[];
}

const props = defineProps<{
    contact: Contact;
    stats: { total_invoiced: number; total_paid: number; outstanding: number };
}>();

const page = usePage();
const crmEnabled = computed(() => ((page.props.planFeatures as string[]) ?? []).includes('crm'));

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    paid: 'default', sent: 'secondary', partial: 'secondary',
    overdue: 'destructive', draft: 'outline', void: 'outline',
};

const activityIcon = { note: StickyNote, call: Phone, email: Mail, meeting: Users };
const activityTypes = [
    { key: 'note', label: 'Note' },
    { key: 'call', label: 'Call' },
    { key: 'email', label: 'Email' },
    { key: 'meeting', label: 'Meeting' },
];

const composer = useForm({ type: 'note', body: '', occurred_at: '' });

function logActivity() {
    composer.post(`/contacts/${props.contact.id}/activities`, {
        preserveScroll: true,
        onSuccess: () => composer.reset('body', 'occurred_at'),
    });
}

function removeActivity(id: number) {
    if (confirm('Remove this activity?')) {
        router.delete(`/activities/${id}`, { preserveScroll: true });
    }
}

function whenLabel(iso: string) {
    return new Date(iso).toLocaleString('en-ZM', { dateStyle: 'medium', timeStyle: 'short' });
}

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
                    <div class="mt-1 flex gap-2">
                        <Badge variant="secondary" class="capitalize">{{ contact.type }}</Badge>
                        <Badge v-if="crmEnabled" variant="outline" class="capitalize">{{ contact.lifecycle_stage }}</Badge>
                    </div>
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

            <!-- Activity timeline (CRM) -->
            <Card v-if="crmEnabled">
                <CardHeader><CardTitle>Activity</CardTitle></CardHeader>
                <CardContent class="space-y-5">
                    <!-- Composer -->
                    <form class="space-y-2" @submit.prevent="logActivity">
                        <div class="flex flex-wrap gap-2">
                            <button v-for="t in activityTypes" :key="t.key" type="button"
                                class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs transition-colors"
                                :class="composer.type === t.key ? 'border-primary bg-primary/10 text-primary' : 'border-input text-muted-foreground hover:bg-accent'"
                                @click="composer.type = t.key">
                                <component :is="activityIcon[t.key as keyof typeof activityIcon]" class="h-3.5 w-3.5" />
                                {{ t.label }}
                            </button>
                            <Input v-model="composer.occurred_at" type="datetime-local" class="ml-auto h-8 w-auto text-xs" />
                        </div>
                        <textarea v-model="composer.body" rows="2" required
                            class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            :placeholder="`Log a ${composer.type}…`" />
                        <div class="flex justify-end">
                            <Button type="submit" size="sm" :disabled="composer.processing || !composer.body">Log activity</Button>
                        </div>
                    </form>

                    <!-- Feed -->
                    <ol class="space-y-4">
                        <li v-for="a in contact.activities" :key="a.id" class="flex gap-3 group">
                            <div class="mt-0.5 rounded-full bg-muted p-2 shrink-0">
                                <component :is="activityIcon[a.type]" class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                    <span class="font-medium capitalize text-foreground">{{ a.type }}</span>
                                    <span>·</span>
                                    <span>{{ whenLabel(a.occurred_at) }}</span>
                                    <span v-if="a.user">· {{ a.user.name }}</span>
                                    <button type="button"
                                        class="ml-auto opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive transition-opacity"
                                        @click="removeActivity(a.id)">
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                                <p class="text-sm whitespace-pre-line mt-0.5">{{ a.body }}</p>
                            </div>
                        </li>
                        <li v-if="!contact.activities.length" class="text-sm text-muted-foreground py-2">
                            No activity yet — log the first interaction above.
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <!-- CRM: opportunities + open tasks -->
            <div v-if="crmEnabled" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>Opportunities</CardTitle>
                        <Button size="sm" variant="outline" as-child>
                            <Link :href="`/opportunities/create?contact_id=${contact.id}`"><Plus class="mr-1 h-4 w-4" /> New</Link>
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <Link v-for="o in contact.opportunities" :key="o.id" :href="`/opportunities/${o.id}`"
                            class="flex items-center justify-between text-sm hover:underline">
                            <span>{{ o.title }} <Badge variant="outline" class="ml-1 capitalize text-[10px] py-0">{{ o.stage }}</Badge></span>
                            <span class="font-medium">{{ fmt(o.estimated_value) }}</span>
                        </Link>
                        <p v-if="!contact.opportunities.length" class="text-sm text-muted-foreground">No opportunities.</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Open Tasks</CardTitle></CardHeader>
                    <CardContent class="space-y-2">
                        <div v-for="t in contact.tasks" :key="t.id" class="flex items-center gap-2 text-sm">
                            <span class="h-2 w-2 rounded-full bg-amber-500 shrink-0" />
                            <span>{{ t.title }}</span>
                            <span v-if="t.due_date" class="ml-auto text-xs text-muted-foreground">{{ t.due_date }}</span>
                        </div>
                        <p v-if="!contact.tasks.length" class="text-sm text-muted-foreground">No open tasks.</p>
                    </CardContent>
                </Card>
            </div>

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
