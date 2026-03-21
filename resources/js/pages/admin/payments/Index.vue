<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Eye, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import admin from '@/routes/admin';

interface Payment {
    id: number;
    method: 'online' | 'offline';
    status: string;
    amount: string;
    billing_cycle: string;
    reference: string;
    proof_path: string | null;
    notes: string | null;
    created_at: string;
    verified_at: string | null;
    company: { id: number; name: string };
    plan: { name: string };
    verified_by?: { name: string };
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{
    payments: Paginated<Payment>;
    currentStatus: string;
    counts: { pending: number; completed: number; rejected: number };
}>();

const tabs = [
    { key: 'pending',   label: 'Pending'   },
    { key: 'completed', label: 'Approved'  },
    { key: 'rejected',  label: 'Rejected'  },
    { key: 'all',       label: 'All'       },
] as const;

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    completed: 'default',
    pending:   'secondary',
    rejected:  'destructive',
};

// Reject dialog
const rejectDialog  = ref(false);
const selectedId    = ref<number | null>(null);
const rejectForm    = useForm({ notes: '' });

function openReject(id: number) {
    selectedId.value   = id;
    rejectForm.notes   = '';
    rejectDialog.value = true;
}

function submitReject() {
    rejectForm.post(admin.payments.reject.url(selectedId.value!), {
        onSuccess: () => { rejectDialog.value = false; },
    });
}

function approve(id: number) {
    if (confirm('Approve this payment and activate the subscription?')) {
        router.post(admin.payments.approve.url(id));
    }
}

function fmt(v: string | number) {
    return 'ZMW ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Payments — Admin" />

    <AdminLayout>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Payments</h1>
        </div>

        <!-- Status tabs -->
        <div class="flex gap-2 mb-6">
            <Button
                v-for="tab in tabs"
                :key="tab.key"
                :variant="currentStatus === tab.key ? 'default' : 'outline'"
                size="sm"
                @click="router.get(admin.payments.index.url(), { status: tab.key })"
            >
                {{ tab.label }}
                <Badge
                    v-if="tab.key === 'pending' && counts.pending > 0"
                    variant="destructive"
                    class="ml-1"
                >
                    {{ counts.pending }}
                </Badge>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>
                    {{ payments.total }} payment{{ payments.total !== 1 ? 's' : '' }}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Company</TableHead>
                            <TableHead>Plan</TableHead>
                            <TableHead>Amount</TableHead>
                            <TableHead>Cycle</TableHead>
                            <TableHead>Method</TableHead>
                            <TableHead>Reference</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Submitted</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="p in payments.data" :key="p.id">
                            <TableCell class="font-medium">{{ p.company.name }}</TableCell>
                            <TableCell>{{ p.plan.name }}</TableCell>
                            <TableCell>{{ fmt(p.amount) }}</TableCell>
                            <TableCell class="capitalize">{{ p.billing_cycle }}</TableCell>
                            <TableCell class="capitalize">{{ p.method }}</TableCell>
                            <TableCell class="font-mono text-xs">{{ p.reference }}</TableCell>
                            <TableCell>
                                <Badge :variant="statusVariant[p.status] ?? 'outline'" class="capitalize">
                                    {{ p.status }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-xs">
                                {{ new Date(p.created_at).toLocaleDateString() }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <!-- View proof -->
                                    <Button
                                        v-if="p.proof_path"
                                        variant="ghost"
                                        size="icon"
                                        as="a"
                                        :href="admin.payments.proof.url(p.id)"
                                        target="_blank"
                                        title="View proof of payment"
                                    >
                                        <Eye class="h-4 w-4" />
                                    </Button>

                                    <!-- Approve -->
                                    <Button
                                        v-if="p.status === 'pending'"
                                        variant="ghost"
                                        size="icon"
                                        class="text-green-600 hover:text-green-700 hover:bg-green-50"
                                        title="Approve"
                                        @click="approve(p.id)"
                                    >
                                        <CheckCircle2 class="h-4 w-4" />
                                    </Button>

                                    <!-- Reject -->
                                    <Button
                                        v-if="p.status === 'pending'"
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive hover:bg-destructive/10"
                                        title="Reject"
                                        @click="openReject(p.id)"
                                    >
                                        <XCircle class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!payments.data.length">
                            <TableCell colspan="9" class="py-10 text-center text-muted-foreground">
                                No payments found.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <!-- Pagination -->
                <div v-if="payments.last_page > 1" class="flex justify-center gap-1 mt-4">
                    <Button
                        v-for="link in payments.links"
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        @click="link.url && router.get(link.url)"
                        v-html="link.label"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- Reject dialog -->
        <Dialog v-model:open="rejectDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject Payment</DialogTitle>
                    <DialogDescription>
                        Provide a reason. The company will see this message.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-2 py-2">
                    <Label for="reject-notes">Reason <span class="text-destructive">*</span></Label>
                    <Textarea
                        id="reject-notes"
                        v-model="rejectForm.notes"
                        placeholder="e.g. Amount does not match, unclear proof of payment…"
                        rows="3"
                    />
                    <p v-if="rejectForm.errors.notes" class="text-sm text-destructive">
                        {{ rejectForm.errors.notes }}
                    </p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="rejectDialog = false">Cancel</Button>
                    <Button
                        variant="destructive"
                        :disabled="rejectForm.processing || !rejectForm.notes.trim()"
                        @click="submitReject"
                    >
                        Reject Payment
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>
