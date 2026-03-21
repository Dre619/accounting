<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as accounts from '@/routes/accounts';

interface LedgerLine {
    date: string;
    entry_number: string;
    description: string;
    source: string;
    contact: string | null;
    debit: number;
    credit: number;
    balance: number;
}

const props = defineProps<{
    account: {
        id: number;
        code: string;
        name: string;
        type: string;
        subtype: string | null;
        is_system: boolean;
    };
    lines: LedgerLine[];
    openingBalance: number;
    closingBalance: number;
    from: string | null;
    to: string | null;
    company: { name: string; currency: string };
}>();

const from = ref(props.from ?? '');
const to   = ref(props.to ?? '');

function applyFilter() {
    router.get(accounts.show.url(props.account.id, {
        query: {
            ...(from.value ? { from: from.value } : {}),
            ...(to.value   ? { to: to.value }     : {}),
        },
    }));
}

function clearFilter() {
    from.value = '';
    to.value   = '';
    router.get(accounts.show.url(props.account.id));
}

function fmt(v: number) {
    return (props.company.currency ?? 'ZMW') + ' ' + v.toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

const sourceLabel: Record<string, string> = {
    invoice: 'Invoice', bill: 'Bill', payment: 'Payment',
    manual: 'Journal', opening: 'Opening',
};
</script>

<template>
    <Head :title="account.code + ' — ' + account.name" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">

            <!-- Header -->
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="accounts.index.url()" class="text-muted-foreground hover:text-foreground transition-colors">
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold">{{ account.code }} — {{ account.name }}</h1>
                            <Badge variant="outline" class="capitalize hidden sm:inline-flex">{{ account.type }}</Badge>
                            <Badge v-if="account.is_system" variant="secondary" class="hidden sm:inline-flex">System</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground mt-0.5 capitalize">
                            {{ account.subtype?.replace(/_/g, ' ') ?? account.type }}
                        </p>
                    </div>
                </div>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="accounts.edit.url(account.id)">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" /> Edit
                    </Link>
                </Button>
            </div>

            <!-- Date filter -->
            <div class="flex items-center gap-2 flex-wrap">
                <input type="date" v-model="from"
                    class="border rounded-md px-3 py-1.5 text-sm bg-background" />
                <span class="text-muted-foreground text-sm">to</span>
                <input type="date" v-model="to"
                    class="border rounded-md px-3 py-1.5 text-sm bg-background" />
                <Button size="sm" @click="applyFilter">Apply</Button>
                <Button v-if="from || to" size="sm" variant="ghost" @click="clearFilter">Clear</Button>
            </div>

            <!-- Ledger table -->
            <Card>
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Ledger</CardTitle>
                    <span class="text-xs text-muted-foreground">
                        Opening balance: <strong>{{ fmt(openingBalance) }}</strong>
                    </span>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Reference</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead class="hidden md:table-cell">Source</TableHead>
                                <TableHead class="text-right">Debit</TableHead>
                                <TableHead class="text-right">Credit</TableHead>
                                <TableHead class="text-right">Balance</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="(line, i) in lines" :key="i">
                                <TableCell class="text-sm text-muted-foreground whitespace-nowrap">
                                    {{ new Date(line.date).toLocaleDateString() }}
                                </TableCell>
                                <TableCell class="font-mono text-xs">{{ line.entry_number }}</TableCell>
                                <TableCell>
                                    <div>{{ line.description }}</div>
                                    <div v-if="line.contact" class="text-xs text-muted-foreground">{{ line.contact }}</div>
                                </TableCell>
                                <TableCell class="hidden md:table-cell">
                                    <Badge variant="outline" class="text-[10px] py-0 capitalize">
                                        {{ sourceLabel[line.source] ?? line.source }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right tabular-nums">
                                    <span v-if="line.debit">{{ fmt(line.debit) }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-right tabular-nums">
                                    <span v-if="line.credit">{{ fmt(line.credit) }}</span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell class="text-right font-semibold tabular-nums">
                                    {{ fmt(line.balance) }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!lines.length">
                                <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                                    No transactions for this period.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                        <TableFooter v-if="lines.length">
                            <TableRow class="bg-muted/40">
                                <TableCell colspan="6" class="text-right font-bold">Closing Balance</TableCell>
                                <TableCell class="text-right font-bold tabular-nums">{{ fmt(closingBalance) }}</TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </CardContent>
            </Card>

        </div>
    </AppLayout>
</template>
