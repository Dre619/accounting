<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
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
    termination_date: string | null;
    tpin: string | null;
    napsa_number: string | null;
    nhima_number: string | null;
    email: string | null;
    phone: string | null;
    bank_name: string | null;
    bank_account: string | null;
    bank_branch: string | null;
    is_active: boolean;
}

const props = defineProps<{ employee: Employee | null }>();
const isEdit = !!props.employee;

const form = useForm({
    employee_number:  props.employee?.employee_number ?? '',
    first_name:       props.employee?.first_name ?? '',
    last_name:        props.employee?.last_name ?? '',
    job_title:        props.employee?.job_title ?? '',
    department:       props.employee?.department ?? '',
    employment_type:  props.employee?.employment_type ?? 'full_time',
    basic_salary:     props.employee?.basic_salary ?? '0',
    hire_date:        props.employee?.hire_date ?? new Date().toISOString().slice(0, 10),
    termination_date: props.employee?.termination_date ?? '',
    tpin:             props.employee?.tpin ?? '',
    napsa_number:     props.employee?.napsa_number ?? '',
    nhima_number:     props.employee?.nhima_number ?? '',
    email:            props.employee?.email ?? '',
    phone:            props.employee?.phone ?? '',
    bank_name:        props.employee?.bank_name ?? '',
    bank_account:     props.employee?.bank_account ?? '',
    bank_branch:      props.employee?.bank_branch ?? '',
    is_active:        props.employee?.is_active ?? true,
});

function submit() {
    if (isEdit) {
        form.put(`/employees/${props.employee!.id}`);
    } else {
        form.post('/employees');
    }
}
</script>

<template>
    <AppLayout>
        <form @submit.prevent="submit">
            <div class="max-w-3xl mx-auto p-6 space-y-6">

                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Employee' : 'New Employee' }}</h1>
                    <Button type="button" variant="outline" @click="router.get('/employees')">Cancel</Button>
                </div>

                <!-- Personal Details -->
                <Card>
                    <CardHeader><CardTitle>Personal Details</CardTitle></CardHeader>
                    <CardContent class="space-y-4">

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="first_name">First Name <span class="text-destructive">*</span></Label>
                                <Input id="first_name" v-model="form.first_name" />
                                <InputError :message="form.errors.first_name" />
                            </div>
                            <div class="space-y-2">
                                <Label for="last_name">Last Name <span class="text-destructive">*</span></Label>
                                <Input id="last_name" v-model="form.last_name" />
                                <InputError :message="form.errors.last_name" />
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <Label for="employee_number">Employee Number</Label>
                                <Input id="employee_number" v-model="form.employee_number" placeholder="Auto-generated" />
                                <InputError :message="form.errors.employee_number" />
                            </div>
                            <div class="space-y-2">
                                <Label for="email">Email</Label>
                                <Input id="email" type="email" v-model="form.email" />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="space-y-2">
                                <Label for="phone">Phone</Label>
                                <Input id="phone" v-model="form.phone" placeholder="+260 9X XXX XXXX" />
                            </div>
                        </div>

                    </CardContent>
                </Card>

                <!-- Employment -->
                <Card>
                    <CardHeader><CardTitle>Employment</CardTitle></CardHeader>
                    <CardContent class="space-y-4">

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="job_title">Job Title</Label>
                                <Input id="job_title" v-model="form.job_title" placeholder="e.g. Accountant" />
                            </div>
                            <div class="space-y-2">
                                <Label for="department">Department</Label>
                                <Input id="department" v-model="form.department" placeholder="e.g. Finance" />
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <Label>Employment Type <span class="text-destructive">*</span></Label>
                                <select v-model="form.employment_type"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                    <option value="full_time">Full-time</option>
                                    <option value="part_time">Part-time</option>
                                    <option value="contract">Contract</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label for="hire_date">Hire Date <span class="text-destructive">*</span></Label>
                                <Input id="hire_date" v-model="form.hire_date" type="date" />
                                <InputError :message="form.errors.hire_date" />
                            </div>
                            <div class="space-y-2">
                                <Label for="termination_date">Termination Date</Label>
                                <Input id="termination_date" v-model="form.termination_date" type="date" />
                            </div>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="basic_salary">Basic Monthly Salary (ZMW) <span class="text-destructive">*</span></Label>
                                <Input id="basic_salary" v-model="form.basic_salary" type="number" min="0" step="0.01" />
                                <InputError :message="form.errors.basic_salary" />
                            </div>
                            <div class="flex items-center gap-3 pt-6">
                                <input id="is_active" type="checkbox" v-model="form.is_active"
                                    class="h-4 w-4 rounded border-input" />
                                <Label for="is_active">Active employee</Label>
                            </div>
                        </div>

                    </CardContent>
                </Card>

                <!-- Statutory IDs -->
                <Card>
                    <CardHeader><CardTitle>Statutory Identification</CardTitle></CardHeader>
                    <CardContent class="grid sm:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="tpin">TPIN</Label>
                            <Input id="tpin" v-model="form.tpin" placeholder="10-digit TPIN" maxlength="10" />
                        </div>
                        <div class="space-y-2">
                            <Label for="napsa_number">NAPSA Number</Label>
                            <Input id="napsa_number" v-model="form.napsa_number" />
                        </div>
                        <div class="space-y-2">
                            <Label for="nhima_number">NHIMA Number</Label>
                            <Input id="nhima_number" v-model="form.nhima_number" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Banking -->
                <Card>
                    <CardHeader><CardTitle>Banking Details</CardTitle></CardHeader>
                    <CardContent class="grid sm:grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="bank_name">Bank Name</Label>
                            <Input id="bank_name" v-model="form.bank_name" placeholder="e.g. Zanaco" />
                        </div>
                        <div class="space-y-2">
                            <Label for="bank_account">Account Number</Label>
                            <Input id="bank_account" v-model="form.bank_account" />
                        </div>
                        <div class="space-y-2">
                            <Label for="bank_branch">Branch</Label>
                            <Input id="bank_branch" v-model="form.bank_branch" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-3">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : isEdit ? 'Update Employee' : 'Add Employee' }}
                    </Button>
                </div>

            </div>
        </form>
    </AppLayout>
</template>
