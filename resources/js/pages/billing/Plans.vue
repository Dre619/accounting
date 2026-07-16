<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { AlertCircle, CheckCircle2, Zap } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import * as billing from '@/routes/billing';

interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string;
    price_monthly: string;
    price_annual: string;
    max_users: number;
    features: string[];
}

interface Subscription {
    id: number;
    status: string;
    billing_cycle: string;
    ends_at: string;
    plan: { id: number; name: string; slug: string };
}

const props = defineProps<{
    plans: Plan[];
    subscription: Subscription | null;
    subscriptionActive: boolean;
    trialEndsAt: string | null;
    lencoPubKey: string;
}>();

const page = usePage();

const trialDaysLeft = computed(() => {
    if (!props.trialEndsAt) return 0;
    const diff = new Date(props.trialEndsAt).getTime() - Date.now();
    return Math.max(0, Math.ceil(diff / 86_400_000));
});

const activeSubscription = computed(() => (props.subscriptionActive ? props.subscription : null));

const lapsedSubscription = computed(() => (props.subscriptionActive ? null : props.subscription));

const currentSlug = computed(() => activeSubscription.value?.plan?.slug ?? null);

const planOrder: Record<string, number> = { starter: 0, growth: 1, business: 2 };

function isCurrentPlan(plan: Plan) {
    return currentSlug.value === plan.slug;
}

function isRenewal(plan: Plan) {
    return lapsedSubscription.value?.plan?.slug === plan.slug;
}

function isDowngrade(plan: Plan) {
    if (!currentSlug.value) return false;
    return (planOrder[plan.slug] ?? 0) < (planOrder[currentSlug.value] ?? 0);
}

function goToCheckout(plan: Plan, cycle: 'monthly' | 'annual') {
    router.get(billing.checkout.url(plan.id), { cycle });
}

function formatZmw(value: string | number) {
    return 'ZMW ' + Number(value).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Subscription Plans" />

    <AppLayout>
        <div class="mx-auto max-w-5xl px-4 py-10">

            <!-- Trial banner -->
            <div
                v-if="trialEndsAt && trialDaysLeft > 0"
                class="mb-8 flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
            >
                <Zap class="h-5 w-5 shrink-0" />
                <span>
                    You're on a free trial — <strong>{{ trialDaysLeft }} day{{ trialDaysLeft !== 1 ? 's' : '' }} remaining</strong>.
                    All features are unlocked during your trial. Subscribe to keep access.
                </span>
            </div>

            <!-- Active subscription banner -->
            <div
                v-if="activeSubscription"
                class="mb-8 flex items-center gap-3 rounded-lg border border-green-300 bg-green-50 px-4 py-3 text-green-800 dark:border-green-700 dark:bg-green-950 dark:text-green-200"
            >
                <CheckCircle2 class="h-5 w-5 shrink-0" />
                <span>
                    Active plan: <strong>{{ activeSubscription.plan.name }}</strong> ({{ activeSubscription.billing_cycle }})
                    — renews {{ new Date(activeSubscription.ends_at).toLocaleDateString('en-ZM', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                </span>
            </div>

            <!-- Expired subscription banner -->
            <div
                v-else-if="lapsedSubscription"
                class="mb-8 flex items-center gap-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-red-800 dark:border-red-700 dark:bg-red-950 dark:text-red-200"
            >
                <AlertCircle class="h-5 w-5 shrink-0" />
                <span>
                    Your <strong>{{ lapsedSubscription.plan.name }}</strong> plan ended on
                    {{ new Date(lapsedSubscription.ends_at).toLocaleDateString('en-ZM', { day: 'numeric', month: 'long', year: 'numeric' }) }}.
                    Renew it below to restore access, or pick a different plan.
                </span>
            </div>

            <div class="text-center mb-10">
                <h1 class="text-3xl font-bold">Choose a Plan</h1>
                <p class="text-muted-foreground mt-2">All prices in Zambian Kwacha (ZMW) · Save ~17% with annual billing.</p>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <Card
                    v-for="plan in plans"
                    :key="plan.id"
                    :class="[
                        'relative flex flex-col',
                        plan.slug === 'growth' ? 'border-primary shadow-lg ring-2 ring-primary' : '',
                        isCurrentPlan(plan) ? 'ring-2 ring-green-500 border-green-500' : '',
                        isRenewal(plan) ? 'ring-2 ring-red-500 border-red-500' : '',
                    ]"
                >
                    <!-- Badges -->
                    <Badge
                        v-if="isCurrentPlan(plan)"
                        class="absolute -top-3 left-1/2 -translate-x-1/2 bg-green-600 hover:bg-green-600"
                    >
                        Current Plan
                    </Badge>
                    <Badge
                        v-else-if="isRenewal(plan)"
                        variant="destructive"
                        class="absolute -top-3 left-1/2 -translate-x-1/2"
                    >
                        Expired
                    </Badge>
                    <Badge
                        v-else-if="plan.slug === 'growth' && !currentSlug"
                        class="absolute -top-3 left-1/2 -translate-x-1/2"
                    >
                        Most Popular
                    </Badge>

                    <CardHeader>
                        <CardTitle class="text-xl">{{ plan.name }}</CardTitle>
                        <CardDescription>{{ plan.description }}</CardDescription>
                    </CardHeader>

                    <CardContent class="flex-1 space-y-4">
                        <!-- Pricing -->
                        <div>
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-bold">{{ formatZmw(plan.price_monthly) }}</span>
                                <span class="text-muted-foreground text-sm">/month</span>
                            </div>
                            <p class="text-sm text-muted-foreground mt-0.5">
                                or {{ formatZmw(plan.price_annual) }}/year
                            </p>
                        </div>

                        <!-- Features -->
                        <ul class="space-y-2 text-sm">
                            <li
                                v-for="feature in plan.features"
                                :key="feature"
                                class="flex items-start gap-2"
                            >
                                <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                {{ feature }}
                            </li>
                        </ul>
                    </CardContent>

                    <CardFooter class="flex flex-col gap-2 pt-4">
                        <!-- Already on this plan -->
                        <template v-if="isCurrentPlan(plan)">
                            <Button class="w-full" variant="outline" disabled>
                                Current Plan
                            </Button>
                        </template>

                        <!-- Renew the plan that just expired -->
                        <template v-else-if="isRenewal(plan)">
                            <Button class="w-full" @click="goToCheckout(plan, 'monthly')">
                                Renew Monthly
                            </Button>
                            <Button
                                variant="ghost"
                                class="w-full text-xs"
                                @click="goToCheckout(plan, 'annual')"
                            >
                                Renew Annually (save ~17%)
                            </Button>
                        </template>

                        <!-- Downgrade -->
                        <template v-else-if="isDowngrade(plan)">
                            <Button
                                class="w-full"
                                variant="outline"
                                @click="goToCheckout(plan, 'monthly')"
                            >
                                Downgrade to {{ plan.name }}
                            </Button>
                        </template>

                        <!-- Upgrade / New subscribe -->
                        <template v-else>
                            <Button
                                class="w-full"
                                :variant="plan.slug === 'growth' ? 'default' : 'outline'"
                                @click="goToCheckout(plan, 'monthly')"
                            >
                                {{ activeSubscription ? 'Upgrade' : 'Subscribe' }} Monthly
                            </Button>
                            <Button
                                variant="ghost"
                                class="w-full text-xs"
                                @click="goToCheckout(plan, 'annual')"
                            >
                                {{ activeSubscription ? 'Upgrade' : 'Subscribe' }} Annually (save ~17%)
                            </Button>
                        </template>
                    </CardFooter>
                </Card>
            </div>

            <p class="mt-8 text-center text-xs text-muted-foreground">
                Need help? Email <a href="mailto:support@cloudone.co.zm" class="underline">support@cloudone.co.zm</a>
            </p>
        </div>
    </AppLayout>
</template>
