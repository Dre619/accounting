<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { AlertCircle, CheckCircle2, Clock, RefreshCw, Zap } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import * as billing from '@/routes/billing';

interface Plan { id: number; name: string; slug: string }
interface Subscription {
    id: number;
    status: string;
    billing_cycle: string;
    starts_at: string;
    ends_at: string;
    plan: Plan;
}
interface PendingPayment {
    id: number;
    amount: string;
    method: string;
    status: string;
    reference: string;
    created_at: string;
    plan: Plan;
}

const props = defineProps<{
    subscription: Subscription | null;
    pendingPayment: PendingPayment | null;
    trialEndsAt: string | null;
}>();

const trialDaysLeft = computed(() => {
    if (!props.trialEndsAt) return 0;
    const diff = new Date(props.trialEndsAt).getTime() - Date.now();
    return Math.max(0, Math.ceil(diff / 86_400_000));
});

const daysRemaining = computed(() => {
    if (!props.subscription) return 0;
    const diff = new Date(props.subscription.ends_at).getTime() - Date.now();
    return Math.max(0, Math.ceil(diff / 86_400_000));
});

const statusConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    active:    { label: 'Active',    variant: 'default'     },
    trialing:  { label: 'Trial',     variant: 'secondary'   },
    past_due:  { label: 'Past Due',  variant: 'destructive' },
    cancelled: { label: 'Cancelled', variant: 'outline'     },
    expired:   { label: 'Expired',   variant: 'destructive' },
};

function fmt(dateStr: string) {
    return new Date(dateStr).toLocaleDateString('en-ZM', { day: 'numeric', month: 'long', year: 'numeric' });
}

function formatZmw(value: string | number) {
    return 'ZMW ' + Number(value).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Subscription Status" />

    <AppLayout>
        <div class="mx-auto max-w-2xl px-4 py-10 space-y-6">

            <h1 class="text-2xl font-bold">Billing &amp; Subscription</h1>

            <!-- Trial status -->
            <Card v-if="!subscription && trialEndsAt">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Zap class="h-5 w-5 text-amber-500" />
                        Free Trial
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        Your free trial ends on <strong>{{ fmt(trialEndsAt!) }}</strong>
                        (<strong>{{ trialDaysLeft }} day{{ trialDaysLeft !== 1 ? 's' : '' }}</strong> remaining).
                        Subscribe before it expires to avoid interruption.
                    </p>
                    <Button @click="router.get(billing.plans.url())">
                        View Plans
                    </Button>
                </CardContent>
            </Card>

            <!-- Active subscription -->
            <Card v-if="subscription">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <CheckCircle2 class="h-5 w-5 text-green-500" />
                            {{ subscription.plan.name }} Plan
                        </CardTitle>
                        <Badge :variant="statusConfig[subscription.status]?.variant ?? 'outline'">
                            {{ statusConfig[subscription.status]?.label ?? subscription.status }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-2 gap-y-2 text-sm">
                        <span class="text-muted-foreground">Billing cycle</span>
                        <span class="capitalize">{{ subscription.billing_cycle }}</span>

                        <span class="text-muted-foreground">Started</span>
                        <span>{{ fmt(subscription.starts_at) }}</span>

                        <span class="text-muted-foreground">Renews / Expires</span>
                        <span>{{ fmt(subscription.ends_at) }} ({{ daysRemaining }} days)</span>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <Button variant="outline" size="sm" @click="router.get(billing.plans.url())">
                            <RefreshCw class="mr-2 h-4 w-4" /> Change Plan
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Pending offline payment -->
            <Card v-if="pendingPayment">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Clock class="h-5 w-5 text-amber-500" />
                        Payment Under Review
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <p class="text-muted-foreground">
                        Your proof of payment for the <strong>{{ pendingPayment.plan.name }}</strong> plan
                        has been received. We'll verify and activate within 24 hours.
                    </p>
                    <div class="grid grid-cols-2 gap-y-1">
                        <span class="text-muted-foreground">Amount</span>
                        <span>{{ formatZmw(pendingPayment.amount) }}</span>
                        <span class="text-muted-foreground">Reference</span>
                        <span class="font-mono text-xs">{{ pendingPayment.reference }}</span>
                        <span class="text-muted-foreground">Submitted</span>
                        <span>{{ fmt(pendingPayment.created_at) }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- No subscription, no trial -->
            <Card v-if="!subscription && !trialEndsAt && !pendingPayment">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <AlertCircle class="h-5 w-5 text-destructive" />
                        No Active Subscription
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <p class="text-sm text-muted-foreground">
                        Your account does not have an active subscription. Subscribe to access all features.
                    </p>
                    <Button @click="router.get(billing.plans.url())">
                        Subscribe Now
                    </Button>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
