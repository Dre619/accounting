<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, UserCheck, UserX } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';

interface Employee {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    job_title: string | null;
    department: string | null;
    employment_type: string;
    basic_salary: string;
    hire_date: string;
    is_active: boolean;
}

interface Paginated { data: Employee[]; links: { url: string | null; label: string; active: boolean }[]; total: number }

const props = defineProps<{ employees: Paginated; search: string }>();

const searchInput = ref(props.search);
function doSearch() { router.get('/employees', { search: searchInput.value }, { preserveState: true }); }

function fmt(v: string) { return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 }); }

function destroy(id: number) {
    if (confirm('Delete this employee?')) router.delete(`/employees/${id}`);
}

const typeLabel: Record<string, string> = {
    full_time: 'Full-time', part_time: 'Part-time', contract: 'Contract',
};
</script>

<template>
    <Head title="Employees" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Employees</h1>
                    <p class="text-sm text-muted-foreground mt-0.5">{{ employees.total }} employee{{ employees.total === 1 ? '' : 's' }}</p>
                </div>
                <Link href="/employees/create">
                    <Button size="sm"><Plus class="h-4 w-4 mr-1" />Add Employee</Button>
                </Link>
            </div>

            <!-- Search -->
            <form @submit.prevent="doSearch" class="flex gap-2 max-w-sm">
                <Input v-model="searchInput" placeholder="Search name or number…" class="h-9" />
                <Button type="submit" size="sm" variant="outline">Search</Button>
            </form>

            <Card v-if="employees.data.length === 0">
                <CardContent class="py-16 text-center text-muted-foreground">
                    No employees found. <Link href="/employees/create" class="text-primary underline">Add your first employee.</Link>
                </CardContent>
            </Card>

            <Card v-else>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Number</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Job Title</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead class="text-right">Basic Salary</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="e in employees.data" :key="e.id">
                                <TableCell class="font-mono text-sm">{{ e.employee_number }}</TableCell>
                                <TableCell class="font-medium">{{ e.first_name }} {{ e.last_name }}</TableCell>
                                <TableCell class="text-sm text-muted-foreground">{{ e.job_title ?? '—' }}</TableCell>
                                <TableCell class="text-sm">{{ typeLabel[e.employment_type] ?? e.employment_type }}</TableCell>
                                <TableCell class="text-right font-mono text-sm">ZMW {{ fmt(e.basic_salary) }}</TableCell>
                                <TableCell>
                                    <Badge :variant="e.is_active ? 'default' : 'outline'" class="gap-1">
                                        <UserCheck v-if="e.is_active" class="h-3 w-3" />
                                        <UserX v-else class="h-3 w-3" />
                                        {{ e.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Link :href="`/employees/${e.id}/edit`">
                                            <Button variant="ghost" size="icon"><Pencil class="h-4 w-4" /></Button>
                                        </Link>
                                        <Button variant="ghost" size="icon" @click="destroy(e.id)">
                                            <Trash2 class="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="employees.links.length > 3" class="flex gap-1">
                <template v-for="link in employees.links" :key="link.label">
                    <a v-if="link.url" :href="link.url"
                        class="px-3 py-1.5 text-sm rounded border"
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'border-input hover:bg-muted'"
                        v-html="link.label" />
                    <span v-else class="px-3 py-1.5 text-sm text-muted-foreground" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
