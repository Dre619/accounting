<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Trash2, Pencil, Eye } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import * as contactRoutes from '@/routes/contacts';

interface Contact {
    id: number;
    name: string;
    type: 'customer' | 'supplier' | 'both';
    email: string | null;
    phone: string | null;
    tpin: string | null;
    is_active: boolean;
    invoices_count: number;
    bills_count: number;
}

const props = defineProps<{
    contacts: { data: Contact[]; total: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
    currentType: string;
    search: string;
    counts: Record<string, number>;
}>();

const searchVal = ref(props.search);

let debounce: ReturnType<typeof setTimeout>;
function onSearch() {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(contactRoutes.index.url(), { type: props.currentType, search: searchVal.value }, { preserveState: true, replace: true });
    }, 350);
}

const tabs = [
    { key: 'all',      label: 'All' },
    { key: 'customer', label: 'Customers' },
    { key: 'supplier', label: 'Suppliers' },
    { key: 'both',     label: 'Both' },
];

const typeVariant: Record<string, 'default' | 'secondary' | 'outline'> = {
    customer: 'default',
    supplier: 'secondary',
    both:     'outline',
};

function destroy(id: number, name: string) {
    if (confirm(`Delete contact "${name}"? This cannot be undone.`)) {
        router.delete(contactRoutes.destroy.url(id));
    }
}
</script>

<template>
    <Head title="Contacts" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Contacts</h1>
                <Button as-child>
                    <Link :href="contactRoutes.create.url()">
                        <Plus class="mr-2 h-4 w-4" /> New Contact
                    </Link>
                </Button>
            </div>

            <!-- Type tabs -->
            <div class="flex gap-2 flex-wrap">
                <Button
                    v-for="tab in tabs" :key="tab.key"
                    :variant="currentType === tab.key ? 'default' : 'outline'"
                    size="sm"
                    @click="router.get(contactRoutes.index.url(), { type: tab.key, search: searchVal })"
                >
                    {{ tab.label }}
                    <span class="ml-1 text-xs opacity-70">({{ counts[tab.key] ?? 0 }})</span>
                </Button>
            </div>

            <Card>
                <CardContent class="pt-4">
                    <!-- Search -->
                    <div class="relative mb-4 max-w-sm">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input v-model="searchVal" placeholder="Search by name, email, phone…" class="pl-9" @input="onSearch" />
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Phone</TableHead>
                                <TableHead>TPIN</TableHead>
                                <TableHead class="text-right">Invoices</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="c in props.contacts.data" :key="c.id">
                                <TableCell class="font-medium">
                                    <Link :href="contactRoutes.show.url(c.id)" class="hover:underline">{{ c.name }}</Link>
                                </TableCell>
                                <TableCell>
                                    <Badge :variant="typeVariant[c.type]" class="capitalize">{{ c.type }}</Badge>
                                </TableCell>
                                <TableCell class="text-muted-foreground">{{ c.email ?? '—' }}</TableCell>
                                <TableCell class="text-muted-foreground">{{ c.phone ?? '—' }}</TableCell>
                                <TableCell class="font-mono text-xs">{{ c.tpin ?? '—' }}</TableCell>
                                <TableCell class="text-right text-sm">{{ c.invoices_count }}</TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button variant="ghost" size="icon" as-child>
                                            <Link :href="contactRoutes.show.url(c.id)" title="View"><Eye class="h-4 w-4" /></Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" as-child>
                                            <Link :href="contactRoutes.edit.url(c.id)" title="Edit"><Pencil class="h-4 w-4" /></Link>
                                        </Button>
                                        <Button variant="ghost" size="icon" class="text-destructive hover:text-destructive" title="Delete" @click="destroy(c.id, c.name)">
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!props.contacts.data?.length">
                                <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                                    No contacts found.
                                    <Link :href="contactRoutes.create.url()" class="ml-1 underline">Add one</Link>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>

                    <!-- Pagination -->
                    <div v-if="props.contacts.last_page > 1" class="flex justify-center gap-1 mt-4">
                        <Button v-for="link in props.contacts.links" :key="link.label"
                            :variant="link.active ? 'default' : 'outline'" size="sm"
                            :disabled="!link.url"
                            @click="link.url && router.get(link.url)"
                            v-html="link.label" />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
