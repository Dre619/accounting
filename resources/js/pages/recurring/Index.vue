<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Play, Plus, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface RecurringInvoice {
    id: number;
    contact: { id: number; name: string } | null;
    frequency: string;
    day_of_month: number;
    days_due: number;
    is_active: boolean;
    next_run_at: string | null;
    last_run_at: string | null;
}

defineProps<{ recurring: RecurringInvoice[] }>();

function run(id: number) {
    if (confirm('Generate an invoice now from this template?')) {
        router.post(`/recurring/${id}/run`);
    }
}

function destroy(id: number) {
    if (confirm('Delete this recurring invoice template?')) {
        router.delete(`/recurring/${id}`);
    }
}

const freqLabel: Record<string, string> = {
    weekly: 'Weekly', monthly: 'Monthly', quarterly: 'Quarterly', yearly: 'Yearly',
};
</script>

<template>
    <Head title="Recurring Invoices" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Recurring Invoices</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">Automatically generate invoices on a schedule</p>
                </div>
                <Link href="/recurring/create">
                    <Button size="sm"><Plus class="h-4 w-4 mr-1" />New Template</Button>
                </Link>
            </div>

            <Card v-if="recurring.length === 0">
                <CardContent class="py-16 text-center text-muted-foreground">
                    No recurring invoices yet. Create a template to get started.
                </CardContent>
            </Card>

            <Card v-else>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Contact</TableHead>
                                <TableHead>Frequency</TableHead>
                                <TableHead>Due (days)</TableHead>
                                <TableHead>Next Run</TableHead>
                                <TableHead>Last Run</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="r in recurring" :key="r.id">
                                <TableCell class="font-medium">{{ r.contact?.name ?? '—' }}</TableCell>
                                <TableCell>{{ freqLabel[r.frequency] ?? r.frequency }}</TableCell>
                                <TableCell>{{ r.days_due }}</TableCell>
                                <TableCell class="text-sm">
                                    {{ r.next_run_at ? new Date(r.next_run_at).toLocaleDateString() : '—' }}
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">
                                    {{ r.last_run_at ? new Date(r.last_run_at).toLocaleDateString() : 'Never' }}
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="r.is_active ? 'default' : 'outline'">
                                        {{ r.is_active ? 'Active' : 'Paused' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button variant="ghost" size="icon" title="Run now" @click="run(r.id)">
                                            <Play class="h-4 w-4 text-green-600" />
                                        </Button>
                                        <Link :href="`/recurring/${r.id}/edit`">
                                            <Button variant="ghost" size="icon">
                                                <Pencil class="h-4 w-4" />
                                            </Button>
                                        </Link>
                                        <Button variant="ghost" size="icon" @click="destroy(r.id)">
                                            <Trash2 class="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
