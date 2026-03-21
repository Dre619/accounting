<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { PlusCircle, Search } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as accounts from '@/routes/accounts';

interface AccountRow {
    id: number;
    code: string;
    name: string;
    type: string;
    subtype: string | null;
    is_system: boolean;
    is_bank_account: boolean;
    is_active: boolean;
    balance: number;
}

const props = defineProps<{
    grouped: Record<string, AccountRow[]>;
    totals: Record<string, number>;
    company: { name: string; currency: string };
}>();

const search = ref('');

const typeLabels: Record<string, string> = {
    asset:     'Assets',
    liability: 'Liabilities',
    equity:    'Equity',
    income:    'Income',
    expense:   'Expenses',
};

const typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];

const filtered = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return props.grouped;
    const out: Record<string, AccountRow[]> = {};
    for (const type of typeOrder) {
        out[type] = (props.grouped[type] ?? []).filter(
            a => a.code.toLowerCase().includes(q) || a.name.toLowerCase().includes(q)
        );
    }
    return out;
});

function fmt(v: number) {
    return (props.company.currency ?? 'ZMW') + ' ' + v.toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

const typeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    asset: 'default', liability: 'secondary', equity: 'secondary',
    income: 'default', expense: 'outline',
};
</script>

<template>
    <Head title="Chart of Accounts" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h1 class="text-2xl font-bold">Chart of Accounts</h1>
                <Button as-child>
                    <Link :href="accounts.create.url()">
                        <PlusCircle class="mr-2 h-4 w-4" /> New Account
                    </Link>
                </Button>
            </div>

            <!-- Search -->
            <div class="relative w-full max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search by code or name…" class="pl-9" />
            </div>

            <!-- One card per account type -->
            <template v-for="type in typeOrder" :key="type">
                <Card v-if="(filtered[type] ?? []).length > 0">
                    <CardHeader class="pb-2 flex flex-row items-center justify-between">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            {{ typeLabels[type] }}
                        </CardTitle>
                        <span class="text-sm font-bold">{{ fmt(totals[type] ?? 0) }}</span>
                    </CardHeader>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-24">Code</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead class="hidden sm:table-cell">Category</TableHead>
                                    <TableHead class="hidden md:table-cell">Tags</TableHead>
                                    <TableHead class="text-right">Balance</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="acc in filtered[type]"
                                    :key="acc.id"
                                    class="cursor-pointer hover:bg-muted/50"
                                    :class="!acc.is_active ? 'opacity-50' : ''"
                                    @click="router.visit(accounts.show.url(acc.id))"
                                >
                                    <TableCell class="font-mono font-semibold text-sm">{{ acc.code }}</TableCell>
                                    <TableCell>
                                        {{ acc.name }}
                                        <span v-if="!acc.is_active" class="ml-2 text-xs text-muted-foreground">(inactive)</span>
                                    </TableCell>
                                    <TableCell class="hidden sm:table-cell text-sm text-muted-foreground capitalize">
                                        {{ acc.subtype?.replace(/_/g, ' ') ?? '—' }}
                                    </TableCell>
                                    <TableCell class="hidden md:table-cell">
                                        <div class="flex gap-1 flex-wrap">
                                            <Badge v-if="acc.is_system" variant="outline" class="text-[10px] py-0">System</Badge>
                                            <Badge v-if="acc.is_bank_account" variant="secondary" class="text-[10px] py-0">Bank</Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-right font-medium tabular-nums">
                                        {{ fmt(acc.balance) }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </template>

            <p v-if="typeOrder.every(t => !(filtered[t] ?? []).length)"
               class="py-10 text-center text-muted-foreground">
                No accounts match your search.
            </p>

        </div>
    </AppLayout>
</template>
