<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface Run {
    id: number;
    period: string;
    status: string;
    total_gross: string;
    total_paye: string;
    total_net: string;
    period_end: string;
    approved_at: string | null;
}

interface Paginated { data: Run[]; links: { url: string | null; label: string; active: boolean }[]; total: number }

defineProps<{ runs: Paginated; employeeCount: number }>();

function fmt(v: string) { return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 }); }
</script>

<template>
    <Head title="Payroll" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Payroll</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">{{ employeeCount }} active employee{{ employeeCount === 1 ? '' : 's' }}</p>
                </div>
                <Link href="/payroll/create">
                    <Button size="sm" :disabled="employeeCount === 0">
                        <Plus class="h-4 w-4 mr-1" />Run Payroll
                    </Button>
                </Link>
            </div>

            <Card v-if="runs.data.length === 0">
                <CardContent class="py-16 text-center text-muted-foreground">
                    No payroll runs yet.
                    <template v-if="employeeCount === 0">
                        <Link href="/employees/create" class="text-primary underline">Add employees first.</Link>
                    </template>
                </CardContent>
            </Card>

            <Card v-else>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Period</TableHead>
                                <TableHead class="text-right">Gross</TableHead>
                                <TableHead class="text-right">PAYE</TableHead>
                                <TableHead class="text-right">Net Pay</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="r in runs.data" :key="r.id">
                                <TableCell class="font-medium">{{ r.period }}</TableCell>
                                <TableCell class="text-right font-mono text-sm">ZMW {{ fmt(r.total_gross) }}</TableCell>
                                <TableCell class="text-right font-mono text-sm text-muted-foreground">ZMW {{ fmt(r.total_paye) }}</TableCell>
                                <TableCell class="text-right font-mono text-sm font-semibold">ZMW {{ fmt(r.total_net) }}</TableCell>
                                <TableCell>
                                    <Badge :variant="r.status === 'approved' ? 'default' : 'outline'" class="capitalize">
                                        {{ r.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Link :href="`/payroll/${r.id}`">
                                        <Button variant="ghost" size="sm">View</Button>
                                    </Link>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
