<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { PlusCircle } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as journal from '@/routes/journal';

interface Entry {
    id: number;
    entry_number: string;
    entry_date: string;
    description: string;
    status: 'draft' | 'posted';
    lines_count: number;
    created_by: { name: string } | null;
}

defineProps<{
    entries: {
        data: Entry[];
        links: { url: string | null; label: string; active: boolean }[];
        meta: { total: number };
    };
}>();
</script>

<template>
    <Head title="Journal Entries" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Journal Entries</h1>
                <Button as-child>
                    <Link :href="journal.create.url()">
                        <PlusCircle class="mr-2 h-4 w-4" /> New Entry
                    </Link>
                </Button>
            </div>

            <Card>
                <CardContent class="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Entry #</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead class="text-center">Lines</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="hidden md:table-cell">Created By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="entry in entries.data" :key="entry.id"
                                class="cursor-pointer hover:bg-muted/50"
                                @click="router.visit(journal.show.url(entry.id))">
                                <TableCell class="font-mono font-semibold">{{ entry.entry_number }}</TableCell>
                                <TableCell class="text-sm text-muted-foreground">
                                    {{ new Date(entry.entry_date).toLocaleDateString('en-ZM', { day: 'numeric', month: 'short', year: 'numeric' }) }}
                                </TableCell>
                                <TableCell>{{ entry.description }}</TableCell>
                                <TableCell class="text-center">{{ entry.lines_count }}</TableCell>
                                <TableCell>
                                    <Badge :variant="entry.status === 'posted' ? 'default' : 'outline'" class="capitalize">
                                        {{ entry.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="hidden md:table-cell text-sm text-muted-foreground">
                                    {{ entry.created_by?.name ?? '—' }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!entries.data.length">
                                <TableCell colspan="6" class="py-10 text-center text-muted-foreground">
                                    No manual journal entries yet.
                                    <Link :href="journal.create.url()" class="ml-1 underline">Create one</Link>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Pagination -->
            <div v-if="entries.links.length > 3" class="flex justify-center gap-1">
                <template v-for="link in entries.links" :key="link.label">
                    <Button v-if="link.url" size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        @click="router.visit(link.url)"
                        v-html="link.label" />
                    <Button v-else size="sm" variant="ghost" disabled v-html="link.label" />
                </template>
            </div>

        </div>
    </AppLayout>
</template>
