<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle, Printer, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface Employee { id: number; first_name: string; last_name: string; job_title: string | null; employee_number: string }
interface Payslip {
    id: number;
    employee: Employee;
    basic_salary: string;
    gross_salary: string;
    paye: string;
    napsa_employee: string;
    napsa_employer: string;
    nhima_employee: string;
    nhima_employer: string;
    total_deductions: string;
    net_salary: string;
}
interface Run {
    id: number;
    period: string;
    period_start: string;
    period_end: string;
    status: string;
    total_gross: string;
    total_paye: string;
    total_napsa_employee: string;
    total_napsa_employer: string;
    total_nhima_employee: string;
    total_nhima_employer: string;
    total_net: string;
    notes: string | null;
    payslips: Payslip[];
    processed_by: { name: string } | null;
    approved_by: { name: string } | null;
    approved_at: string | null;
}
interface Company { name: string; address: string | null; city: string | null; tpin: string | null }

const props = defineProps<{ run: Run; company: Company }>();

function fmt(v: string | number) { return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 }); }

function approve() {
    if (confirm(`Approve payroll for ${props.run.period}? This will post a journal entry and cannot be undone.`)) {
        router.post(`/payroll/${props.run.id}/approve`);
    }
}

function destroy() {
    if (confirm('Delete this payroll run?')) {
        router.delete(`/payroll/${props.run.id}`);
    }
}
</script>

<template>
    <Head :title="`Payroll — ${run.period}`" />
    <AppLayout>
        <div class="max-w-5xl mx-auto p-6 space-y-4">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold">Payroll — {{ run.period }}</h1>
                    <Badge :variant="run.status === 'approved' ? 'default' : 'outline'" class="capitalize">
                        {{ run.status }}
                    </Badge>
                </div>
                <div class="flex gap-2">
                    <Button v-if="run.status === 'draft'" variant="destructive" size="sm" @click="destroy">
                        <Trash2 class="mr-1 h-4 w-4" /> Delete
                    </Button>
                    <Button v-if="run.status === 'draft'" @click="approve">
                        <CheckCircle class="mr-2 h-4 w-4" /> Approve & Post
                    </Button>
                </div>
            </div>

            <!-- Summary card -->
            <Card>
                <CardContent class="pt-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-muted-foreground text-xs uppercase tracking-wider">Period</p>
                            <p class="font-medium mt-1">{{ run.period_start }} → {{ run.period_end }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase tracking-wider">Employees</p>
                            <p class="font-medium mt-1">{{ run.payslips.length }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase tracking-wider">Total Gross</p>
                            <p class="font-semibold mt-1">ZMW {{ fmt(run.total_gross) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs uppercase tracking-wider">Net Pay</p>
                            <p class="font-bold text-base mt-1">ZMW {{ fmt(run.total_net) }}</p>
                        </div>
                    </div>

                    <Separator class="my-4" />

                    <!-- Deduction breakdown -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-muted-foreground text-xs">PAYE</p>
                            <p class="font-mono">ZMW {{ fmt(run.total_paye) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">NAPSA (employee)</p>
                            <p class="font-mono">ZMW {{ fmt(run.total_napsa_employee) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">NAPSA (employer)</p>
                            <p class="font-mono">ZMW {{ fmt(run.total_napsa_employer) }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground text-xs">NHIMA (each)</p>
                            <p class="font-mono">ZMW {{ fmt(run.total_nhima_employee) }}</p>
                        </div>
                    </div>

                    <div v-if="run.notes" class="mt-4 text-sm text-muted-foreground">
                        <span class="font-medium text-foreground">Notes:</span> {{ run.notes }}
                    </div>
                    <div v-if="run.approved_by" class="mt-2 text-xs text-muted-foreground">
                        Approved by {{ run.approved_by.name }} on {{ new Date(run.approved_at!).toLocaleDateString() }}
                    </div>
                </CardContent>
            </Card>

            <!-- Payslips table -->
            <Card>
                <CardHeader><CardTitle>Payslips</CardTitle></CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead class="text-right">Gross</TableHead>
                                <TableHead class="text-right">PAYE</TableHead>
                                <TableHead class="text-right">NAPSA</TableHead>
                                <TableHead class="text-right">NHIMA</TableHead>
                                <TableHead class="text-right font-semibold">Net Pay</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="slip in run.payslips" :key="slip.id">
                                <TableCell>
                                    <p class="font-medium">{{ slip.employee.first_name }} {{ slip.employee.last_name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ slip.employee.employee_number }}{{ slip.employee.job_title ? ' · ' + slip.employee.job_title : '' }}</p>
                                </TableCell>
                                <TableCell class="text-right font-mono text-sm">{{ fmt(slip.gross_salary) }}</TableCell>
                                <TableCell class="text-right font-mono text-sm text-muted-foreground">{{ fmt(slip.paye) }}</TableCell>
                                <TableCell class="text-right font-mono text-sm text-muted-foreground">{{ fmt(slip.napsa_employee) }}</TableCell>
                                <TableCell class="text-right font-mono text-sm text-muted-foreground">{{ fmt(slip.nhima_employee) }}</TableCell>
                                <TableCell class="text-right font-mono text-sm font-bold">{{ fmt(slip.net_salary) }}</TableCell>
                                <TableCell class="text-right">
                                    <a :href="`/payroll/${run.id}/payslips/${slip.id}/print`" target="_blank">
                                        <Button variant="ghost" size="icon" title="Print payslip">
                                            <Printer class="h-4 w-4" />
                                        </Button>
                                    </a>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
