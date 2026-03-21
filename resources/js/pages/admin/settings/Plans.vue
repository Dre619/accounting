<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Pencil, X, Plus, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import AdminSettingsLayout from '@/layouts/admin/AdminSettingsLayout.vue';

interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_monthly: string;
    price_annual: string;
    currency: string;
    max_users: number | null;
    features: string[];
    is_active: boolean;
    sort_order: number;
}

const props = defineProps<{ plans: Plan[] }>();

const editing = ref<number | null>(null);

function makeForm(plan: Plan) {
    return useForm({
        name:          plan.name,
        description:   plan.description ?? '',
        price_monthly: plan.price_monthly,
        price_annual:  plan.price_annual,
        max_users:     plan.max_users ?? '',
        features:      [...(plan.features ?? [])],
        is_active:     plan.is_active,
        sort_order:    plan.sort_order,
    });
}

const forms = Object.fromEntries(props.plans.map(p => [p.id, makeForm(p)]));

function addFeature(planId: number) {
    forms[planId].features.push('');
}

function removeFeature(planId: number, i: number) {
    forms[planId].features.splice(i, 1);
}

function save(plan: Plan) {
    forms[plan.id].patch(`/admin/settings/plans/${plan.id}`, {
        onSuccess: () => { editing.value = null; },
    });
}

function fmt(v: string | number) {
    return 'ZMW ' + Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="Subscription Plans" />
    <AdminSettingsLayout>
        <div class="space-y-4">
            <div v-for="plan in plans" :key="plan.id">
                <Card>
                    <CardHeader class="pb-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <CardTitle class="text-base">{{ plan.name }}</CardTitle>
                                <Badge :variant="plan.is_active ? 'default' : 'outline'">
                                    {{ plan.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </div>
                            <Button
                                variant="ghost" size="icon"
                                @click="editing = editing === plan.id ? null : plan.id"
                            >
                                <X v-if="editing === plan.id" class="h-4 w-4" />
                                <Pencil v-else class="h-4 w-4" />
                            </Button>
                        </div>
                        <div v-if="editing !== plan.id" class="text-sm text-muted-foreground flex gap-4 mt-1">
                            <span>Monthly: <strong>{{ fmt(plan.price_monthly) }}</strong></span>
                            <span>Annual: <strong>{{ fmt(plan.price_annual) }}</strong></span>
                            <span v-if="plan.max_users">Max users: {{ plan.max_users }}</span>
                        </div>
                    </CardHeader>

                    <!-- View mode: features list -->
                    <CardContent v-if="editing !== plan.id">
                        <ul v-if="plan.features?.length" class="text-sm text-muted-foreground list-disc list-inside space-y-0.5">
                            <li v-for="f in plan.features" :key="f">{{ f }}</li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground italic">No features listed.</p>
                    </CardContent>

                    <!-- Edit mode -->
                    <CardContent v-else>
                        <form @submit.prevent="save(plan)" class="space-y-4">
                            <div class="grid sm:grid-cols-2 gap-3">
                                <div class="grid gap-1.5">
                                    <Label>Plan Name</Label>
                                    <Input v-model="forms[plan.id].name" required />
                                    <InputError :message="forms[plan.id].errors.name" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Description</Label>
                                    <Input v-model="forms[plan.id].description" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Monthly Price (ZMW)</Label>
                                    <Input v-model="forms[plan.id].price_monthly" type="number" step="0.01" min="0" />
                                    <InputError :message="forms[plan.id].errors.price_monthly" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Annual Price (ZMW)</Label>
                                    <Input v-model="forms[plan.id].price_annual" type="number" step="0.01" min="0" />
                                    <InputError :message="forms[plan.id].errors.price_annual" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Max Users</Label>
                                    <Input v-model="forms[plan.id].max_users" type="number" min="1" placeholder="Unlimited" />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label>Sort Order</Label>
                                    <Input v-model="forms[plan.id].sort_order" type="number" min="0" />
                                </div>
                            </div>

                            <!-- Features -->
                            <div class="space-y-2">
                                <Label>Features</Label>
                                <div v-for="(f, i) in forms[plan.id].features" :key="i" class="flex gap-2">
                                    <Input v-model="forms[plan.id].features[i]" placeholder="e.g. Unlimited invoices" />
                                    <Button type="button" variant="ghost" size="icon" @click="removeFeature(plan.id, i)">
                                        <Trash2 class="h-4 w-4 text-destructive" />
                                    </Button>
                                </div>
                                <Button type="button" variant="outline" size="sm" @click="addFeature(plan.id)">
                                    <Plus class="h-3.5 w-3.5 mr-1" /> Add Feature
                                </Button>
                            </div>

                            <!-- Active toggle -->
                            <div class="flex items-center gap-2">
                                <input id="active" type="checkbox" v-model="forms[plan.id].is_active" class="h-4 w-4" />
                                <Label for="active" class="cursor-pointer">Plan is active (visible to customers)</Label>
                            </div>

                            <div class="flex gap-2 pt-1">
                                <Button type="submit" size="sm" :disabled="forms[plan.id].processing">Save</Button>
                                <Button type="button" size="sm" variant="outline" @click="editing = null">Cancel</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AdminSettingsLayout>
</template>
