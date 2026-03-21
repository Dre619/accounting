<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';

interface Company {
    id: number;
    name: string;
    tpin: string | null;
    currency: string;
    trial_ends_at: string | null;
    created_at: string;
    invoices_count: number;
    contacts_count: number;
    owner: { name: string; email: string };
    active_subscription: {
        status: string;
        billing_cycle: string;
        ends_at: string;
        plan: { name: string };
    } | null;
}

interface Paginated<T> {
    data: T[];
    total: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

defineProps<{
    companies: Paginated<Company>;
}>();

function trialDaysLeft(trialEndsAt: string | null): number {
    if (!trialEndsAt) return 0;
    return Math.max(0, Math.ceil((new Date(trialEndsAt).getTime() - Date.now()) / 86_400_000));
}
</script>

<template>
    <Head title="Companies — Admin" />

    <AdminLayout>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Companies</h1>
            <span class="text-sm text-muted-foreground">{{ companies.total }} total</span>
        </div>

        <Card>
            <CardContent class="pt-4">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Company</TableHead>
                            <TableHead>Owner</TableHead>
                            <TableHead>TPIN</TableHead>
                            <TableHead>Subscription</TableHead>
                            <TableHead>Invoices</TableHead>
                            <TableHead>Contacts</TableHead>
                            <TableHead>Joined</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="c in companies.data" :key="c.id">
                            <TableCell class="font-medium">{{ c.name }}</TableCell>
                            <TableCell>
                                <p class="text-sm">{{ c.owner.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ c.owner.email }}</p>
                            </TableCell>
                            <TableCell class="font-mono text-xs">{{ c.tpin ?? '—' }}</TableCell>
                            <TableCell>
                                <template v-if="c.active_subscription">
                                    <Badge variant="default">{{ c.active_subscription.plan.name }}</Badge>
                                    <p class="text-xs text-muted-foreground mt-0.5 capitalize">
                                        {{ c.active_subscription.billing_cycle }} ·
                                        expires {{ new Date(c.active_subscription.ends_at).toLocaleDateString() }}
                                    </p>
                                </template>
                                <template v-else-if="trialDaysLeft(c.trial_ends_at) > 0">
                                    <Badge variant="secondary">Trial</Badge>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        {{ trialDaysLeft(c.trial_ends_at) }}d remaining
                                    </p>
                                </template>
                                <Badge v-else variant="outline">Inactive</Badge>
                            </TableCell>
                            <TableCell>{{ c.invoices_count }}</TableCell>
                            <TableCell>{{ c.contacts_count }}</TableCell>
                            <TableCell class="text-muted-foreground text-xs">
                                {{ new Date(c.created_at).toLocaleDateString() }}
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!companies.data.length">
                            <TableCell colspan="7" class="py-10 text-center text-muted-foreground">
                                No companies yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div v-if="companies.last_page > 1" class="flex justify-center gap-1 mt-4">
                    <Button
                        v-for="link in companies.links"
                        :key="link.label"
                        :variant="link.active ? 'default' : 'outline'"
                        size="sm"
                        :disabled="!link.url"
                        @click="link.url && router.get(link.url)"
                        v-html="link.label"
                    />
                </div>
            </CardContent>
        </Card>
    </AdminLayout>
</template>
