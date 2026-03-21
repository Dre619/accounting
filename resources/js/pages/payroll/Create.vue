<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{ defaultPeriod: string; employeeCount: number }>();

const form = useForm({
    period: props.defaultPeriod,
    notes:  '',
});

function submit() { form.post('/payroll'); }
</script>

<template>
    <AppLayout>
        <form @submit.prevent="submit">
            <div class="max-w-lg mx-auto p-6 space-y-6">

                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">Run Payroll</h1>
                    <Button type="button" variant="outline" @click="router.get('/payroll')">Cancel</Button>
                </div>

                <Card>
                    <CardHeader><CardTitle>Payroll Period</CardTitle></CardHeader>
                    <CardContent class="space-y-4">

                        <div class="space-y-2">
                            <Label for="period">Month (YYYY-MM) <span class="text-destructive">*</span></Label>
                            <Input id="period" v-model="form.period" placeholder="e.g. 2026-03"
                                pattern="\d{4}-\d{2}" maxlength="7" />
                            <p class="text-xs text-muted-foreground">Payslips will be generated for all {{ employeeCount }} active employees.</p>
                            <InputError :message="form.errors.period" />
                        </div>

                        <div class="space-y-2">
                            <Label for="notes">Notes (optional)</Label>
                            <textarea id="notes" v-model="form.notes" rows="2"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder="e.g. Includes bonus" />
                        </div>

                        <div class="rounded-md bg-muted/40 p-3 text-sm space-y-1 text-muted-foreground">
                            <p class="font-medium text-foreground">Zambia Statutory Deductions Applied</p>
                            <p>PAYE — progressive: 0% / 20% / 30% / 37.5%</p>
                            <p>NAPSA — 5% employee + 5% employer (capped at ZMW 1,221.80/month)</p>
                            <p>NHIMA — 1% employee + 1% employer</p>
                        </div>

                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing || employeeCount === 0">
                        {{ form.processing ? 'Processing…' : 'Process Payroll' }}
                    </Button>
                </div>

            </div>
        </form>
    </AppLayout>
</template>
