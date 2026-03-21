<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import * as accounts from '@/routes/accounts';

interface Category {
    id: number;
    name: string;
    type: string;
}

interface Account {
    id: number;
    account_category_id: number;
    code: string;
    name: string;
    description: string | null;
    type: string;
    subtype: string | null;
    is_bank_account: boolean;
    bank_name: string | null;
    bank_account_number: string | null;
    opening_balance: number;
    opening_balance_date: string | null;
    is_active: boolean;
    is_system: boolean;
}

const props = defineProps<{
    categories: Category[];
    account: Account | null;
}>();

const isEdit = computed(() => !!props.account);

const form = useForm({
    account_category_id: props.account?.account_category_id ?? '',
    code:                props.account?.code ?? '',
    name:                props.account?.name ?? '',
    description:         props.account?.description ?? '',
    type:                props.account?.type ?? '',
    subtype:             props.account?.subtype ?? '',
    is_bank_account:     props.account?.is_bank_account ?? false,
    bank_name:           props.account?.bank_name ?? '',
    bank_account_number: props.account?.bank_account_number ?? '',
    opening_balance:     props.account?.opening_balance ?? 0,
    opening_balance_date:props.account?.opening_balance_date ?? '',
    is_active:           props.account?.is_active ?? true,
});

const typeOptions = [
    { value: 'asset',     label: 'Asset' },
    { value: 'liability', label: 'Liability' },
    { value: 'equity',    label: 'Equity' },
    { value: 'income',    label: 'Income' },
    { value: 'expense',   label: 'Expense' },
];

const subtypeMap: Record<string, { value: string; label: string }[]> = {
    asset:     [
        { value: 'current_asset', label: 'Current Asset' },
        { value: 'fixed_asset',   label: 'Fixed Asset' },
        { value: 'other_asset',   label: 'Other Asset' },
    ],
    liability: [
        { value: 'current_liability',    label: 'Current Liability' },
        { value: 'long_term_liability',  label: 'Long-Term Liability' },
    ],
    equity:    [{ value: 'equity', label: 'Equity' }],
    income:    [
        { value: 'operating_income', label: 'Operating Income' },
        { value: 'other_income',     label: 'Other Income' },
    ],
    expense:   [
        { value: 'cost_of_goods_sold',  label: 'Cost of Goods Sold' },
        { value: 'operating_expense',   label: 'Operating Expense' },
        { value: 'other_expense',       label: 'Other Expense' },
    ],
};

const subtypeOptions = computed(() => subtypeMap[form.type] ?? []);

// Filter categories to match selected type
const filteredCategories = computed(() =>
    props.categories.filter(c => !form.type || c.type === form.type)
);

function submit() {
    if (isEdit.value && props.account) {
        form.put(accounts.update.url(props.account.id));
    } else {
        form.post(accounts.store.url());
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Account' : 'New Account'" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-2xl">

            <!-- Header -->
            <div class="flex items-center gap-3">
                <Link :href="isEdit ? accounts.show.url(account!.id) : accounts.index.url()"
                      class="text-muted-foreground hover:text-foreground transition-colors">
                    <ArrowLeft class="h-5 w-5" />
                </Link>
                <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Account' : 'New Account' }}</h1>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle class="text-base">Account Details</CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="grid gap-5">

                        <!-- Type (create only) -->
                        <div v-if="!isEdit" class="grid gap-1.5">
                            <Label>Type <span class="text-destructive">*</span></Label>
                            <Select v-model="form.type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select type…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in typeOptions" :key="t.value" :value="t.value">
                                        {{ t.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.type" />
                        </div>

                        <!-- Category -->
                        <div class="grid gap-1.5">
                            <Label>Category <span class="text-destructive">*</span></Label>
                            <Select v-model="form.account_category_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select category…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="cat in filteredCategories"
                                        :key="cat.id"
                                        :value="String(cat.id)"
                                    >
                                        {{ cat.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.account_category_id" />
                        </div>

                        <!-- Code (create only) + Name -->
                        <div class="grid grid-cols-3 gap-4">
                            <div class="grid gap-1.5 col-span-1">
                                <Label for="code">Code <span class="text-destructive">*</span></Label>
                                <Input id="code" v-model="form.code"
                                    placeholder="e.g. 6010"
                                    :readonly="isEdit || account?.is_system"
                                    :class="(isEdit || account?.is_system) ? 'opacity-60' : ''" />
                                <InputError :message="form.errors.code" />
                            </div>
                            <div class="grid gap-1.5 col-span-2">
                                <Label for="name">Name <span class="text-destructive">*</span></Label>
                                <Input id="name" v-model="form.name" placeholder="Account name" />
                                <InputError :message="form.errors.name" />
                            </div>
                        </div>

                        <!-- Subtype (create only) -->
                        <div v-if="!isEdit && subtypeOptions.length" class="grid gap-1.5">
                            <Label>Subtype</Label>
                            <Select v-model="form.subtype">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select subtype…" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in subtypeOptions" :key="s.value" :value="s.value">
                                        {{ s.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.subtype" />
                        </div>

                        <!-- Description -->
                        <div class="grid gap-1.5">
                            <Label for="description">Description</Label>
                            <Input id="description" v-model="form.description" placeholder="Optional notes" />
                            <InputError :message="form.errors.description" />
                        </div>

                        <!-- Opening balance (create only) -->
                        <div v-if="!isEdit" class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="opening_balance">Opening Balance</Label>
                                <Input id="opening_balance" type="number" step="0.01"
                                    v-model="form.opening_balance" placeholder="0.00" />
                                <InputError :message="form.errors.opening_balance" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="opening_balance_date">Opening Balance Date</Label>
                                <Input id="opening_balance_date" type="date"
                                    v-model="form.opening_balance_date" />
                                <InputError :message="form.errors.opening_balance_date" />
                            </div>
                        </div>

                        <!-- Bank account toggle -->
                        <div class="flex items-center gap-3">
                            <input id="is_bank_account" type="checkbox" v-model="form.is_bank_account"
                                class="h-4 w-4 rounded border-gray-300 text-primary" />
                            <Label for="is_bank_account" class="cursor-pointer">This is a bank / cash account</Label>
                        </div>

                        <!-- Bank details -->
                        <div v-if="form.is_bank_account" class="grid grid-cols-2 gap-4 pl-7">
                            <div class="grid gap-1.5">
                                <Label for="bank_name">Bank Name</Label>
                                <Input id="bank_name" v-model="form.bank_name" placeholder="e.g. Zanaco" />
                                <InputError :message="form.errors.bank_name" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="bank_account_number">Account Number</Label>
                                <Input id="bank_account_number" v-model="form.bank_account_number"
                                    placeholder="e.g. 1234567890" />
                                <InputError :message="form.errors.bank_account_number" />
                            </div>
                        </div>

                        <!-- Active toggle (edit only) -->
                        <div v-if="isEdit" class="flex items-center gap-3">
                            <input id="is_active" type="checkbox" v-model="form.is_active"
                                class="h-4 w-4 rounded border-gray-300 text-primary" />
                            <Label for="is_active" class="cursor-pointer">Account is active</Label>
                        </div>

                        <div class="flex gap-3 pt-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ isEdit ? 'Save Changes' : 'Create Account' }}
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <Link :href="isEdit ? accounts.show.url(account!.id) : accounts.index.url()">
                                    Cancel
                                </Link>
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
