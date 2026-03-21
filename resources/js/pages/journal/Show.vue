<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCheck, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as journal from '@/routes/journal';

interface Line {
    id: number;
    description: string | null;
    debit: number;
    credit: number;
    sort_order: number;
    account: { id: number; code: string; name: string; type: string };
    contact: { name: string } | null;
}

interface Entry {
    id: number;
    entry_number: string;
    entry_date: string;
    description: string;
    status: 'draft' | 'posted';
    lines: Line[];
    created_by: { name: string } | null;
    posted_at: string | null;
}

defineProps<{ entry: Entry }>();

function fmt(v: number) {
    return v > 0 ? 'ZMW ' + v.toLocaleString('en-ZM', { minimumFractionDigits: 2 }) : '—';
}

function postEntry(id: number) {
    if (confirm('Post this journal entry? It will be recorded permanently and cannot be deleted.')) {
        router.post(journal.post.url(id));
    }
}

function deleteEntry(id: number) {
    if (confirm('Delete this draft journal entry?')) {
        router.delete(journal.destroy.url(id));
    }
}
</script>

<template>
    <Head :title="entry.entry_number" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-4xl">

            <!-- Header -->
            <div class="flex items-start justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <Link :href="journal.index.url()" class="text-muted-foreground hover:text-foreground">
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-2xl font-bold font-mono">{{ entry.entry_number }}</h1>
                            <Badge :variant="entry.status === 'posted' ? 'default' : 'outline'" class="capitalize">
                                {{ entry.status }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground mt-0.5">
                            {{ new Date(entry.entry_date).toLocaleDateString('en-ZM', { dateStyle: 'long' }) }}
                            <span v-if="entry.created_by"> · {{ entry.created_by.name }}</span>
                        </p>
                    </div>
                </div>
                <div class="flex gap-2" v-if="entry.status === 'draft'">
                    <Button variant="destructive" size="sm" @click="deleteEntry(entry.id)">
                        <Trash2 class="mr-1.5 h-3.5 w-3.5" /> Delete
                    </Button>
                    <Button size="sm" @click="postEntry(entry.id)">
                        <CheckCheck class="mr-1.5 h-4 w-4" /> Post Entry
                    </Button>
                </div>
            </div>

            <!-- Description -->
            <p class="text-base text-muted-foreground">{{ entry.description }}</p>

            <!-- Lines -->
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Journal Lines</CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Account</TableHead>
                                <TableHead class="hidden md:table-cell">Description</TableHead>
                                <TableHead class="hidden sm:table-cell">Contact</TableHead>
                                <TableHead class="text-right">Debit</TableHead>
                                <TableHead class="text-right">Credit</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="line in entry.lines" :key="line.id">
                                <TableCell>
                                    <span class="font-mono text-xs text-muted-foreground">{{ line.account.code }}</span>
                                    <span class="ml-2">{{ line.account.name }}</span>
                                </TableCell>
                                <TableCell class="hidden md:table-cell text-sm text-muted-foreground">
                                    {{ line.description || '—' }}
                                </TableCell>
                                <TableCell class="hidden sm:table-cell text-sm text-muted-foreground">
                                    {{ line.contact?.name ?? '—' }}
                                </TableCell>
                                <TableCell class="text-right tabular-nums font-medium text-blue-600">
                                    {{ fmt(line.debit) }}
                                </TableCell>
                                <TableCell class="text-right tabular-nums font-medium text-amber-600">
                                    {{ fmt(line.credit) }}
                                </TableCell>
                            </TableRow>
                        </TableBody>
                        <TableFooter>
                            <TableRow class="bg-muted/40">
                                <TableCell :colspan="3" class="text-right font-bold">Totals</TableCell>
                                <TableCell class="text-right font-bold tabular-nums text-blue-600">
                                    ZMW {{ entry.lines.reduce((s, l) => s + l.debit, 0).toLocaleString('en-ZM', { minimumFractionDigits: 2 }) }}
                                </TableCell>
                                <TableCell class="text-right font-bold tabular-nums text-amber-600">
                                    ZMW {{ entry.lines.reduce((s, l) => s + l.credit, 0).toLocaleString('en-ZM', { minimumFractionDigits: 2 }) }}
                                </TableCell>
                            </TableRow>
                        </TableFooter>
                    </Table>
                </CardContent>
            </Card>

            <p v-if="entry.status === 'posted' && entry.posted_at"
               class="text-xs text-muted-foreground text-right">
                Posted on {{ new Date(entry.posted_at).toLocaleString() }}
            </p>
        </div>
    </AppLayout>
</template>
