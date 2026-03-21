<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, CheckCircle2, Clock, CreditCard, DollarSign, Users } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';

interface Stats {
    total_companies: number;
    total_users: number;
    active_subscriptions: number;
    pending_payments: number;
    total_revenue: number;
    completed_payments: number;
}

interface RevenueMonth { month: string; total: string }
interface Payment {
    id: number;
    method: string;
    status: string;
    amount: string;
    reference: string;
    created_at: string;
    company: { name: string };
    plan: { name: string };
}

defineProps<{
    stats: Stats;
    revenueByMonth: RevenueMonth[];
    recentPayments: Payment[];
}>();

function fmt(v: string | number) {
    return 'ZMW ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    completed: 'default',
    pending:   'secondary',
    rejected:  'destructive',
    failed:    'destructive',
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <h1 class="text-2xl font-bold mb-6">Dashboard</h1>

        <!-- Stat cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 mb-8">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Total Companies</CardTitle>
                    <Building2 class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">{{ stats.total_companies }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Active Subscriptions</CardTitle>
                    <CheckCircle2 class="h-4 w-4 text-green-500" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">{{ stats.active_subscriptions }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Pending Payments</CardTitle>
                    <Clock class="h-4 w-4 text-amber-500" />
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2">
                        <p class="text-2xl font-bold">{{ stats.pending_payments }}</p>
                        <Badge v-if="stats.pending_payments > 0" variant="destructive" class="text-xs">
                            Action needed
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Total Revenue</CardTitle>
                    <DollarSign class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">{{ fmt(stats.total_revenue) }}</p>
                    <p class="text-xs text-muted-foreground">{{ stats.completed_payments }} payments</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Total Users</CardTitle>
                    <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-bold">{{ stats.total_users }}</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardTitle class="text-sm font-medium">Revenue (Last 6 mo)</CardTitle>
                    <CreditCard class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="flex items-end gap-1 h-10">
                        <template v-for="m in revenueByMonth" :key="m.month">
                            <div
                                class="flex-1 bg-primary rounded-sm"
                                :style="{ height: (Number(m.total) / Math.max(...revenueByMonth.map(x => Number(x.total)), 1) * 40) + 'px' }"
                                :title="`${m.month}: ${fmt(m.total)}`"
                            />
                        </template>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Recent payments -->
        <Card>
            <CardHeader>
                <CardTitle>Recent Payments</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Company</TableHead>
                            <TableHead>Plan</TableHead>
                            <TableHead>Amount</TableHead>
                            <TableHead>Method</TableHead>
                            <TableHead>Reference</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Date</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="p in recentPayments" :key="p.id">
                            <TableCell class="font-medium">{{ p.company.name }}</TableCell>
                            <TableCell>{{ p.plan.name }}</TableCell>
                            <TableCell>{{ fmt(p.amount) }}</TableCell>
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
                        </TableRow>
                        <TableRow v-if="!recentPayments.length">
                            <TableCell colspan="7" class="text-center text-muted-foreground py-8">
                                No payments yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </AdminLayout>
</template>
