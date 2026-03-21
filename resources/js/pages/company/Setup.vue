<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Building2, CheckCircle2, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import * as company from '@/routes/company';

// ── Step definitions ───────────────────────────────────────────────────────
const STEPS = [
    { title: 'Business Details',  description: 'Tell us about your business' },
    { title: 'Tax Information',   description: 'ZRA compliance details' },
    { title: 'Preferences',       description: 'Currency and invoice settings' },
    { title: 'Review',            description: 'Confirm and get started' },
] as const;

const currentStep = ref(0);
const totalSteps  = STEPS.length;

// ── Form ───────────────────────────────────────────────────────────────────
const form = useForm({
    name:               '',
    email:              '',
    phone:              '',
    address:            '',
    city:               '',
    tpin:               '',
    vat_number:         '',
    financial_year_end: '12-31',
    invoice_prefix:     'INV',
});

// ── Navigation ────────────────────────────────────────────────────────────
const isFirstStep = computed(() => currentStep.value === 0);
const isLastStep  = computed(() => currentStep.value === totalSteps - 1);
const isReview    = computed(() => currentStep.value === totalSteps - 1);

function next() {
    if (currentStep.value < totalSteps - 1) currentStep.value++;
}

function back() {
    if (currentStep.value > 0) currentStep.value--;
}

function submit() {
    form.post(company.store.url());
}

// ── Month options for financial year end ──────────────────────────────────
const monthOptions = [
    { label: 'January (01-31)',   value: '01-31' },
    { label: 'February (02-28)',  value: '02-28' },
    { label: 'March (03-31)',     value: '03-31' },
    { label: 'April (04-30)',     value: '04-30' },
    { label: 'May (05-31)',       value: '05-31' },
    { label: 'June (06-30)',      value: '06-30' },
    { label: 'July (07-31)',      value: '07-31' },
    { label: 'August (08-31)',    value: '08-31' },
    { label: 'September (09-30)', value: '09-30' },
    { label: 'October (10-31)',   value: '10-31' },
    { label: 'November (11-30)',  value: '11-30' },
    { label: 'December (12-31)',  value: '12-31' },
];
</script>

<template>
    <Head title="Set Up Your Company" />

    <div class="min-h-screen bg-background flex items-center justify-center p-4">
        <div class="w-full max-w-xl">

            <!-- Logo / Brand -->
            <div class="flex items-center justify-center gap-2 mb-8">
                <Building2 class="h-8 w-8 text-primary" />
                <span class="text-2xl font-bold">CloudOne Accounting</span>
            </div>

            <!-- Progress steps -->
            <div class="flex items-center mb-8">
                <template v-for="(step, index) in STEPS" :key="index">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium transition-colors"
                            :class="{
                                'bg-primary text-primary-foreground': index === currentStep,
                                'bg-primary/20 text-primary': index < currentStep,
                                'bg-muted text-muted-foreground': index > currentStep,
                            }"
                        >
                            <CheckCircle2 v-if="index < currentStep" class="h-5 w-5" />
                            <span v-else>{{ index + 1 }}</span>
                        </div>
                        <span
                            class="hidden sm:block text-sm font-medium"
                            :class="index === currentStep ? 'text-foreground' : 'text-muted-foreground'"
                        >
                            {{ step.title }}
                        </span>
                    </div>
                    <div
                        v-if="index < STEPS.length - 1"
                        class="flex-1 h-px mx-2"
                        :class="index < currentStep ? 'bg-primary' : 'bg-muted'"
                    />
                </template>
            </div>

            <!-- Step card -->
            <Card>
                <CardHeader>
                    <CardTitle>{{ STEPS[currentStep].title }}</CardTitle>
                    <CardDescription>{{ STEPS[currentStep].description }}</CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">

                    <!-- Step 1: Business Details -->
                    <template v-if="currentStep === 0">
                        <div class="space-y-2">
                            <Label for="name">Business Name <span class="text-destructive">*</span></Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                placeholder="e.g. Acme Trading Ltd"
                                autofocus
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email">Business Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                placeholder="accounts@yourbusiness.zm"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">Phone Number</Label>
                            <Input
                                id="phone"
                                v-model="form.phone"
                                placeholder="+260 97X XXX XXX"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="city">City</Label>
                                <Input id="city" v-model="form.city" placeholder="Lusaka" />
                                <InputError :message="form.errors.city" />
                            </div>
                            <div class="space-y-2">
                                <Label for="address">Address</Label>
                                <Input id="address" v-model="form.address" placeholder="Plot 123, Cairo Rd" />
                                <InputError :message="form.errors.address" />
                            </div>
                        </div>
                    </template>

                    <!-- Step 2: Tax Information -->
                    <template v-if="currentStep === 1">
                        <div class="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground mb-2">
                            Your TPIN is issued by the Zambia Revenue Authority (ZRA). It appears on all
                            your invoices and tax returns.
                        </div>

                        <div class="space-y-2">
                            <Label for="tpin">TPIN (Tax Payer Identification Number)</Label>
                            <Input
                                id="tpin"
                                v-model="form.tpin"
                                placeholder="1000000000"
                                maxlength="10"
                            />
                            <InputError :message="form.errors.tpin" />
                        </div>

                        <div class="space-y-2">
                            <Label for="vat_number">VAT Registration Number</Label>
                            <Input
                                id="vat_number"
                                v-model="form.vat_number"
                                placeholder="Leave blank if not VAT-registered"
                            />
                            <p class="text-xs text-muted-foreground">
                                Only required if your turnover exceeds ZMW 800,000/year.
                            </p>
                            <InputError :message="form.errors.vat_number" />
                        </div>
                    </template>

                    <!-- Step 3: Preferences -->
                    <template v-if="currentStep === 2">
                        <div class="space-y-2">
                            <Label>Currency</Label>
                            <div class="flex items-center gap-3 rounded-md border bg-muted/40 px-3 py-2">
                                <span class="font-semibold">ZMW</span>
                                <span class="text-muted-foreground text-sm">Zambian Kwacha (fixed)</span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="financial_year_end">Financial Year End</Label>
                            <select
                                id="financial_year_end"
                                v-model="form.financial_year_end"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            >
                                <option v-for="m in monthOptions" :key="m.value" :value="m.value">
                                    {{ m.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.financial_year_end" />
                        </div>

                        <div class="space-y-2">
                            <Label for="invoice_prefix">Invoice Number Prefix</Label>
                            <div class="flex items-center gap-2">
                                <Input
                                    id="invoice_prefix"
                                    v-model="form.invoice_prefix"
                                    class="w-28"
                                    placeholder="INV"
                                    maxlength="10"
                                />
                                <span class="text-muted-foreground text-sm">→ first invoice will be <strong>{{ form.invoice_prefix || 'INV' }}-0001</strong></span>
                            </div>
                            <InputError :message="form.errors.invoice_prefix" />
                        </div>
                    </template>

                    <!-- Step 4: Review -->
                    <template v-if="isReview">
                        <div class="space-y-3 text-sm">
                            <div class="grid grid-cols-2 gap-y-2">
                                <span class="text-muted-foreground">Business Name</span>
                                <span class="font-medium">{{ form.name || '—' }}</span>

                                <span class="text-muted-foreground">Email</span>
                                <span class="font-medium">{{ form.email || '—' }}</span>

                                <span class="text-muted-foreground">Phone</span>
                                <span class="font-medium">{{ form.phone || '—' }}</span>

                                <span class="text-muted-foreground">City</span>
                                <span class="font-medium">{{ form.city || '—' }}</span>

                                <span class="text-muted-foreground">TPIN</span>
                                <span class="font-medium">{{ form.tpin || '—' }}</span>

                                <span class="text-muted-foreground">VAT Number</span>
                                <span class="font-medium">{{ form.vat_number || 'Not registered' }}</span>

                                <span class="text-muted-foreground">Currency</span>
                                <span class="font-medium">ZMW (Zambian Kwacha)</span>

                                <span class="text-muted-foreground">Financial Year End</span>
                                <span class="font-medium">{{ monthOptions.find(m => m.value === form.financial_year_end)?.label }}</span>

                                <span class="text-muted-foreground">Invoice Prefix</span>
                                <span class="font-medium">{{ form.invoice_prefix }}-0001, {{ form.invoice_prefix }}-0002…</span>
                            </div>

                            <div class="rounded-lg bg-muted/50 p-3 mt-4 text-muted-foreground">
                                We'll automatically set up a <strong class="text-foreground">standard Zambian chart of accounts</strong>
                                and pre-configure <strong class="text-foreground">VAT (16%)</strong> and
                                <strong class="text-foreground">withholding tax rates</strong> for you.
                            </div>
                        </div>
                    </template>

                </CardContent>
            </Card>

            <!-- Navigation buttons -->
            <div class="flex items-center justify-between mt-4">
                <Button
                    variant="outline"
                    :disabled="isFirstStep"
                    @click="back"
                >
                    <ChevronLeft class="mr-1 h-4 w-4" /> Back
                </Button>

                <Button
                    v-if="!isLastStep"
                    :disabled="currentStep === 0 && !form.name.trim()"
                    @click="next"
                >
                    Next <ChevronRight class="ml-1 h-4 w-4" />
                </Button>

                <Button
                    v-else
                    :disabled="form.processing"
                    @click="submit"
                >
                    <span v-if="form.processing">Setting up…</span>
                    <span v-else>Create Company</span>
                </Button>
            </div>

        </div>
    </div>
</template>
