<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, PlusCircle, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as journal from '@/routes/journal';

interface Account { id: number; code: string; name: string; type: string }
interface Contact { id: number; name: string }

const props = defineProps<{
    accounts: Account[];
    contacts: Contact[];
    today: string;
}>();

const form = useForm({
    entry_date:  props.today,
    description: '',
    lines: [
        { account_id: '', description: '', debit: '', credit: '', contact_id: '' },
        { account_id: '', description: '', debit: '', credit: '', contact_id: '' },
    ] as { account_id: string; description: string; debit: string; credit: string; contact_id: string }[],
});

const totalDebit  = computed(() => form.lines.reduce((s, l) => s + (parseFloat(l.debit)  || 0), 0));
const totalCredit = computed(() => form.lines.reduce((s, l) => s + (parseFloat(l.credit) || 0), 0));
const isBalanced  = computed(() => Math.abs(totalDebit.value - totalCredit.value) < 0.01);

function addLine() {
    form.lines.push({ account_id: '', description: '', debit: '', credit: '', contact_id: '' });
}

function removeLine(i: number) {
    if (form.lines.length > 2) form.lines.splice(i, 1);
}

function fmt(v: number) {
    return v.toLocaleString('en-ZM', { minimumFractionDigits: 2 });
}

function submit() {
    form.post(journal.store.url());
}

// Group accounts for display
const accountOptions = computed(() =>
    props.accounts.map(a => ({ value: String(a.id), label: `${a.code} — ${a.name}` }))
);
</script>

<template>
    <Head title="New Journal Entry" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-5xl">

            <div class="flex items-center gap-3">
                <Link :href="journal.index.url()" class="text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="h-5 w-5" />
                </Link>
                <h1 class="text-2xl font-bold">New Journal Entry</h1>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">

                <!-- Header fields -->
                <Card>
                    <CardContent class="pt-6 grid sm:grid-cols-2 gap-5">
                        <div class="grid gap-1.5">
                            <Label for="entry_date">Date <span class="text-destructive">*</span></Label>
                            <Input id="entry_date" type="date" v-model="form.entry_date" />
                            <InputError :message="form.errors.entry_date" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="description">Narration / Description <span class="text-destructive">*</span></Label>
                            <Input id="description" v-model="form.description" placeholder="e.g. Depreciation adjustment" />
                            <InputError :message="form.errors.description" />
                        </div>
                    </CardContent>
                </Card>

                <!-- Lines -->
                <Card>
                    <CardHeader class="pb-2 flex flex-row items-center justify-between">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">
                            Journal Lines
                        </CardTitle>
                        <div class="flex items-center gap-3 text-sm">
                            <span :class="isBalanced ? 'text-green-600 font-medium' : 'text-destructive font-medium'">
                                {{ isBalanced ? '✓ Balanced' : `Difference: ZMW ${fmt(Math.abs(totalDebit - totalCredit))}` }}
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Account</TableHead>
                                    <TableHead class="hidden lg:table-cell">Description</TableHead>
                                    <TableHead class="hidden md:table-cell" style="width:160px">Contact</TableHead>
                                    <TableHead class="text-right" style="width:140px">Debit (ZMW)</TableHead>
                                    <TableHead class="text-right" style="width:140px">Credit (ZMW)</TableHead>
                                    <TableHead style="width:44px"></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="(line, i) in form.lines" :key="i">
                                    <TableCell class="py-2">
                                        <Select v-model="line.account_id">
                                            <SelectTrigger class="w-full min-w-[180px]">
                                                <SelectValue placeholder="Select account…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="a in accountOptions" :key="a.value" :value="a.value">
                                                    {{ a.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="(form.errors as any)[`lines.${i}.account_id`]" />
                                    </TableCell>
                                    <TableCell class="hidden lg:table-cell py-2">
                                        <Input v-model="line.description" placeholder="Optional note" class="min-w-[120px]" />
                                    </TableCell>
                                    <TableCell class="hidden md:table-cell py-2">
                                        <Select v-model="line.contact_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="None" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="">None</SelectItem>
                                                <SelectItem v-for="c in contacts" :key="c.id" :value="String(c.id)">
                                                    {{ c.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </TableCell>
                                    <TableCell class="py-2 text-right">
                                        <Input v-model="line.debit" type="number" step="0.01" min="0"
                                            placeholder="0.00" class="text-right tabular-nums w-full" />
                                    </TableCell>
                                    <TableCell class="py-2 text-right">
                                        <Input v-model="line.credit" type="number" step="0.01" min="0"
                                            placeholder="0.00" class="text-right tabular-nums w-full" />
                                    </TableCell>
                                    <TableCell class="py-2">
                                        <Button type="button" variant="ghost" size="icon"
                                            :disabled="form.lines.length <= 2"
                                            @click="removeLine(i)">
                                            <Trash2 class="h-4 w-4 text-muted-foreground" />
                                        </Button>
                                    </TableCell>
                                </TableRow>

                                <!-- Totals row -->
                                <TableRow class="bg-muted/40 font-semibold">
                                    <TableCell :colspan="3" class="text-right text-sm text-muted-foreground">Totals</TableCell>
                                    <TableCell class="text-right tabular-nums text-blue-600">
                                        ZMW {{ fmt(totalDebit) }}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums text-amber-600">
                                        ZMW {{ fmt(totalCredit) }}
                                    </TableCell>
                                    <TableCell />
                                </TableRow>
                            </TableBody>
                        </Table>

                        <div class="p-4">
                            <Button type="button" variant="outline" size="sm" @click="addLine">
                                <PlusCircle class="mr-2 h-4 w-4" /> Add Line
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <InputError :message="form.errors.lines" />

                <div class="flex gap-3">
                    <Button type="submit" :disabled="form.processing || !isBalanced">
                        Save as Draft
                    </Button>
                    <Button type="button" variant="outline" as-child>
                        <Link :href="journal.index.url()">Cancel</Link>
                    </Button>
                </div>

            </form>
        </div>
    </AppLayout>
</template>
