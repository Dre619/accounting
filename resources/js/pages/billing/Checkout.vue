<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
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

const props = defineProps<{
    plan: Plan;
    cycle: 'monthly' | 'annual';
    amount: string | number;
    lencoPubKey: string;
}>();

const page = usePage();

const activeTab   = ref<'online' | 'offline'>('online');
const lencoStatus = ref<'idle' | 'processing' | 'success' | 'error'>('idle');

// Online payment form (just stores the Lenco reference after success)
const onlineForm = useForm({
    plan_id:   props.plan.id,
    cycle:     props.cycle,
    reference: '',
    amount:    Number(props.amount),
});

// Offline payment form
const offlineForm = useForm({
    plan_id: props.plan.id,
    cycle:   props.cycle,
    proof:   null as File | null,
    notes:   '',
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
        amount:    Number(props.amount), // Lenco expects amount in ngwe/cents
        currency:  'ZMW',
        channels:  ['card', 'mobile-money'],
        customer:  {
            firstName: first,
            lastName:  rest.join(' ') || first,
            phone:     '',
        },
        onSuccess(response) {
            lencoStatus.value       = 'success';
            onlineForm.reference    = response.reference;
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
    offlineForm.post(billing.uploadProof.url(), {
        forceFormData: true,
    });
}

function formatZmw(value: string | number) {
    return 'ZMW ' + Number(value).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head :title="`Subscribe — ${plan.name}`" />

    <!-- Load Lenco inline script -->
    <component :is="'script'" src="https://pay.lenco.co/js/v1/inline.js" async />

    <AppLayout>
        <div class="mx-auto max-w-lg px-4 py-10">

            <!-- Order summary -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Order Summary</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">{{ plan.name }} — {{ cycle === 'annual' ? 'Annual' : 'Monthly' }}</span>
                        <span class="font-semibold text-base">{{ formatZmw(amount) }}</span>
                    </div>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ cycle === 'annual' ? '12 months access' : '1 month access' }}
                    </p>
                </CardContent>
            </Card>

            <!-- Payment method tabs -->
            <Tabs v-model="activeTab">
                <TabsList class="w-full">
                    <TabsTrigger value="online" class="flex-1">
                        <CreditCard class="mr-2 h-4 w-4" /> Pay Online
                    </TabsTrigger>
                    <TabsTrigger value="offline" class="flex-1">
                        <Upload class="mr-2 h-4 w-4" /> Bank / Mobile Transfer
                    </TabsTrigger>
                </TabsList>

                <!-- ── Online (Lenco) ─────────────────────────────────────── -->
                <TabsContent value="online">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Pay with Lenco</CardTitle>
                            <CardDescription>
                                Card or mobile money (Airtel Money, MTN, Zamtel). Instant activation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <InputError :message="onlineForm.errors.reference" />

                            <Button
                                class="w-full"
                                size="lg"
                                :disabled="lencoStatus === 'processing' || onlineForm.processing"
                                @click="launchLenco"
                            >
                                <span v-if="lencoStatus === 'processing' || onlineForm.processing">
                                    Processing…
                                </span>
                                <span v-else>
                                    Pay {{ formatZmw(amount) }} Now
                                </span>
                            </Button>

                            <p class="text-center text-xs text-muted-foreground">
                                Secured by Lenco &mdash; your payment is encrypted
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- ── Offline (upload proof) ─────────────────────────────── -->
                <TabsContent value="offline">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Bank / Mobile Money Transfer</CardTitle>
                            <CardDescription>
                                Transfer to our account then upload your proof of payment.
                                Activation within 24 hours.
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Bank details -->
                            <div class="rounded-lg bg-muted/50 p-4 text-sm space-y-1">
                                <p class="font-semibold mb-2">Payment Details</p>
                                <div class="grid grid-cols-2 gap-y-1">
                                    <span class="text-muted-foreground">Bank</span>
                                    <span>Zanaco Bank</span>
                                    <span class="text-muted-foreground">Account Name</span>
                                    <span>CloudOne Technologies Ltd</span>
                                    <span class="text-muted-foreground">Account No.</span>
                                    <span>1234567890</span>
                                    <span class="text-muted-foreground">Branch</span>
                                    <span>Cairo Road, Lusaka</span>
                                    <span class="text-muted-foreground">Amount</span>
                                    <span class="font-semibold">{{ formatZmw(amount) }}</span>
                                </div>
                                <p class="mt-3 text-xs text-muted-foreground">
                                    Use your company name as the payment reference.
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
                                        <span class="text-sm font-medium">
                                            {{ proofFileName || 'Click to upload' }}
                                        </span>
                                        <span class="text-xs text-muted-foreground mt-1">
                                            JPG, PNG or PDF — max 4 MB
                                        </span>
                                        <input
                                            id="proof"
                                            type="file"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            class="sr-only"
                                            @change="handleFileChange"
                                        />
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
