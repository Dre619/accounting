<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { CreditCard, Upload } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import * as billing from '@/routes/billing';

declare const LencoPay: {
    getPaid(options: {
        key: string;
        reference: string;
        email: string;
        amount: number;
        currency: string;
        channels: string[];
        customer: { firstName: string; lastName: string; phone: string };
        onSuccess: (response: { reference: string }) => void;
        onClose: () => void;
        onConfirmationPending: () => void;
    }): void;
};

interface Plan {
    id: number;
    name: string;
    slug: string;
    price_monthly: string;
    price_annual: string;
    features: string[];
}

interface BankingDetails {
    bank_name: string;
    account_name: string;
    account_number: string;
    branch: string;
    swift_code: string;
    sort_code: string;
    mobile_money: string;
    instructions: string;
}

const props = defineProps<{
    plan: Plan;
    cycle: 'monthly' | 'annual';
    amount: string | number;
    lencoPubKey: string;
    banking: BankingDetails;
}>();

const page = usePage();

// ── Billing selection (cycle + months) ───────────────────────────────────────
const selectedCycle  = ref<'monthly' | 'annual'>(props.cycle);
const selectedMonths = ref<number>(props.cycle === 'annual' ? 12 : 1)

// When switching to annual, lock months to 12; when switching to monthly reset to 1
watch(selectedCycle, (cycle) => {
    selectedMonths.value = cycle === 'annual' ? 12 : 1;
});

const monthlyPrice = computed(() => Number(props.plan.price_monthly));
const annualPrice  = computed(() => Number(props.plan.price_annual));

// Price per month displayed in the monthly selector
const pricePerMonth = computed(() => monthlyPrice.value);

// Total amount to charge
const totalAmount = computed(() => {
    if (selectedCycle.value === 'annual') return annualPrice.value;
    return Math.round(monthlyPrice.value * selectedMonths.value * 100) / 100;
});

// Savings vs paying monthly
const annualSaving = computed(() =>
    Math.round((monthlyPrice.value * 12 - annualPrice.value) * 100) / 100
);

// Label for the order summary
const periodLabel = computed(() => {
    if (selectedCycle.value === 'annual') return '12 months (annual)';
    return selectedMonths.value === 1 ? '1 month' : `${selectedMonths.value} months`;
});

// ── Forms ────────────────────────────────────────────────────────────────────
const activeTab   = ref<'online' | 'offline'>('online');
const lencoStatus = ref<'idle' | 'processing' | 'success' | 'error'>('idle');

const onlineForm = useForm({
    plan_id:   props.plan.id,
    cycle:     selectedCycle.value,
    months:    selectedMonths.value,
    reference: '',
    amount:    totalAmount.value,
});

const offlineForm = useForm({
    plan_id: props.plan.id,
    cycle:   selectedCycle.value,
    months:  selectedMonths.value,
    proof:   null as File | null,
    notes:   '',
});

// Keep forms in sync with selection
watch([selectedCycle, selectedMonths, totalAmount], () => {
    onlineForm.cycle  = selectedCycle.value;
    onlineForm.months = selectedMonths.value;
    onlineForm.amount = totalAmount.value;
    offlineForm.cycle  = selectedCycle.value;
    offlineForm.months = selectedMonths.value;
});

const proofFileName = ref('');

function handleFileChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    offlineForm.proof   = file;
    proofFileName.value = file?.name ?? '';
}

function launchLenco() {
    const user    = page.props.auth.user as { name: string; email: string };
    const [first, ...rest] = user.name.split(' ');

    lencoStatus.value = 'processing';

    LencoPay.getPaid({
        key:       props.lencoPubKey,
        reference: 'CLO-' + Date.now(),
        email:     user.email,
        amount:    totalAmount.value,
        currency:  'ZMW',
        channels:  ['card', 'mobile-money'],
        customer:  {
            firstName: first,
            lastName:  rest.join(' ') || first,
            phone:     '',
        },
        onSuccess(response) {
            lencoStatus.value    = 'success';
            onlineForm.reference = response.reference;
            onlineForm.amount    = totalAmount.value;
            onlineForm.post(billing.verifyOnline.url());
        },
        onClose() {
            lencoStatus.value = 'idle';
        },
        onConfirmationPending() {
            lencoStatus.value = 'idle';
            alert('Your payment is pending confirmation. We will activate your subscription once confirmed.');
        },
    });
}

function submitOffline() {
    offlineForm.post(billing.uploadProof.url(), { forceFormData: true });
}

function formatZmw(value: string | number) {
    return 'ZMW ' + Number(value).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head :title="`Subscribe — ${plan.name}`" />
    <component :is="'script'" src="https://pay.lenco.co/js/v1/inline.js" async />

    <AppLayout>
        <div class="mx-auto max-w-lg px-4 py-10 space-y-6">

            <!-- ── Billing cycle selector ─────────────────────────────────── -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">{{ plan.name }} — Choose Billing Period</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">

                    <!-- Cycle toggle -->
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            :class="[
                                'rounded-lg border-2 p-3 text-sm font-medium transition-colors text-left',
                                selectedCycle === 'monthly'
                                    ? 'border-primary bg-primary/5 text-primary'
                                    : 'border-border text-muted-foreground hover:border-primary/50',
                            ]"
                            @click="selectedCycle = 'monthly'"
                        >
                            <span class="block font-semibold">Monthly</span>
                            <span class="text-xs opacity-75">{{ formatZmw(monthlyPrice) }} / month</span>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-lg border-2 p-3 text-sm font-medium transition-colors text-left relative',
                                selectedCycle === 'annual'
                                    ? 'border-primary bg-primary/5 text-primary'
                                    : 'border-border text-muted-foreground hover:border-primary/50',
                            ]"
                            @click="selectedCycle = 'annual'"
                        >
                            <span class="absolute -top-2.5 right-2 rounded-full bg-green-600 px-2 py-0.5 text-[10px] font-bold text-white">
                                SAVE {{ formatZmw(annualSaving) }}
                            </span>
                            <span class="block font-semibold">Annual</span>
                            <span class="text-xs opacity-75">{{ formatZmw(annualPrice) }} / year</span>
                        </button>
                    </div>

                    <!-- Monthly: number of months picker -->
                    <div v-if="selectedCycle === 'monthly'" class="space-y-2">
                        <Label>Number of Months</Label>
                        <div class="grid grid-cols-6 gap-2">
                            <button
                                v-for="m in 12"
                                :key="m"
                                type="button"
                                :class="[
                                    'rounded-lg border py-2 text-sm font-medium transition-colors',
                                    selectedMonths === m
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border hover:border-primary/50 text-muted-foreground',
                                ]"
                                @click="selectedMonths = m"
                            >
                                {{ m }}
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ selectedMonths }} month{{ selectedMonths > 1 ? 's' : '' }} ×
                            {{ formatZmw(monthlyPrice) }} = <strong>{{ formatZmw(totalAmount) }}</strong>
                        </p>
                    </div>

                    <!-- Annual summary -->
                    <div v-else class="rounded-lg bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-200">
                        12 months access · You save {{ formatZmw(annualSaving) }} compared to paying monthly.
                    </div>
                </CardContent>
            </Card>

            <!-- ── Order summary ─────────────────────────────────────────── -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Order Summary</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">{{ plan.name }} — {{ periodLabel }}</span>
                        <span class="font-bold text-lg">{{ formatZmw(totalAmount) }}</span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        Access until {{ (() => {
                            const d = new Date();
                            d.setMonth(d.getMonth() + (selectedCycle === 'annual' ? 12 : selectedMonths));
                            return d.toLocaleDateString('en-ZM', { day: 'numeric', month: 'long', year: 'numeric' });
                        })() }}
                    </p>
                </CardContent>
            </Card>

            <!-- ── Payment method tabs ────────────────────────────────────── -->
            <Tabs v-model="activeTab">
                <TabsList class="w-full">
                    <TabsTrigger value="online" class="flex-1">
                        <CreditCard class="mr-2 h-4 w-4" /> Pay Online
                    </TabsTrigger>
                    <TabsTrigger value="offline" class="flex-1">
                        <Upload class="mr-2 h-4 w-4" /> Bank / Mobile Transfer
                    </TabsTrigger>
                </TabsList>

                <!-- Online (Lenco) -->
                <TabsContent value="online">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Pay with Lenco</CardTitle>
                            <CardDescription>Card or mobile money (Airtel Money, MTN, Zamtel). Instant activation.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <InputError :message="onlineForm.errors.reference" />
                            <Button
                                class="w-full"
                                size="lg"
                                :disabled="lencoStatus === 'processing' || onlineForm.processing"
                                @click="launchLenco"
                            >
                                <span v-if="lencoStatus === 'processing' || onlineForm.processing">Processing…</span>
                                <span v-else>Pay {{ formatZmw(totalAmount) }} Now</span>
                            </Button>
                            <p class="text-center text-xs text-muted-foreground">
                                Secured by Lenco &mdash; your payment is encrypted
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Offline (upload proof) -->
                <TabsContent value="offline">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Bank / Mobile Money Transfer</CardTitle>
                            <CardDescription>Transfer to our account then upload your proof of payment. Activation within 24 hours.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Bank details -->
                            <div class="rounded-lg bg-muted/50 p-4 text-sm space-y-1">
                                <p class="font-semibold mb-2">Payment Details</p>
                                <div class="grid grid-cols-2 gap-y-1">
                                    <template v-if="banking.bank_name">
                                        <span class="text-muted-foreground">Bank</span>
                                        <span>{{ banking.bank_name }}</span>
                                    </template>
                                    <template v-if="banking.account_name">
                                        <span class="text-muted-foreground">Account Name</span>
                                        <span>{{ banking.account_name }}</span>
                                    </template>
                                    <template v-if="banking.account_number">
                                        <span class="text-muted-foreground">Account No.</span>
                                        <span>{{ banking.account_number }}</span>
                                    </template>
                                    <template v-if="banking.branch">
                                        <span class="text-muted-foreground">Branch</span>
                                        <span>{{ banking.branch }}</span>
                                    </template>
                                    <template v-if="banking.swift_code">
                                        <span class="text-muted-foreground">SWIFT / BIC</span>
                                        <span>{{ banking.swift_code }}</span>
                                    </template>
                                    <template v-if="banking.sort_code">
                                        <span class="text-muted-foreground">Sort Code</span>
                                        <span>{{ banking.sort_code }}</span>
                                    </template>
                                    <template v-if="banking.mobile_money">
                                        <span class="text-muted-foreground">Mobile Money</span>
                                        <span>{{ banking.mobile_money }}</span>
                                    </template>
                                    <span class="text-muted-foreground font-medium">Amount to Transfer</span>
                                    <span class="font-bold text-primary">{{ formatZmw(totalAmount) }}</span>
                                </div>
                                <p v-if="banking.instructions" class="mt-3 text-xs text-muted-foreground">
                                    {{ banking.instructions }}
                                </p>
                            </div>

                            <!-- Upload form -->
                            <form @submit.prevent="submitOffline" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="proof">Proof of Payment <span class="text-destructive">*</span></Label>
                                    <label
                                        for="proof"
                                        class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-input py-8 hover:border-primary transition-colors"
                                    >
                                        <Upload class="mb-2 h-6 w-6 text-muted-foreground" />
                                        <span class="text-sm font-medium">{{ proofFileName || 'Click to upload' }}</span>
                                        <span class="text-xs text-muted-foreground mt-1">JPG, PNG or PDF — max 4 MB</span>
                                        <input id="proof" type="file" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" @change="handleFileChange" />
                                    </label>
                                    <InputError :message="offlineForm.errors.proof" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="notes">Notes (optional)</Label>
                                    <Textarea
                                        id="notes"
                                        v-model="offlineForm.notes"
                                        placeholder="e.g. Transfer date, mobile money number used…"
                                        rows="2"
                                    />
                                    <InputError :message="offlineForm.errors.notes" />
                                </div>

                                <Button
                                    type="submit"
                                    class="w-full"
                                    :disabled="offlineForm.processing || !offlineForm.proof"
                                >
                                    {{ offlineForm.processing ? 'Uploading…' : 'Submit Proof of Payment' }}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

        </div>
    </AppLayout>
</template>
