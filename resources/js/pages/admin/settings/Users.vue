<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ShieldCheck, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminSettingsLayout from '@/layouts/admin/AdminSettingsLayout.vue';

interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    companies_count: number;
    created_at: string;
}

const props = defineProps<{
    users: { data: User[]; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
}>();

function toggleAdmin(user: User) {
    const action = user.is_admin ? 'Remove admin from' : 'Grant admin to';
    if (confirm(`${action} ${user.name}?`)) {
        router.post(`/admin/settings/users/${user.id}/toggle-admin`);
    }
}

function destroy(user: User) {
    if (confirm(`Permanently delete ${user.email} and all their data? This cannot be undone.`)) {
        router.delete(`/admin/settings/users/${user.id}`);
    }
}
</script>

<template>
    <Head title="Users" />
    <AdminSettingsLayout>
        <Card>
            <CardContent class="pt-4">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead class="text-center">Companies</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead>Joined</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in props.users.data" :key="user.id">
                            <TableCell class="font-medium">{{ user.name }}</TableCell>
                            <TableCell class="text-muted-foreground text-sm">{{ user.email }}</TableCell>
                            <TableCell class="text-center text-sm">{{ user.companies_count }}</TableCell>
                            <TableCell>
                                <Badge v-if="user.is_admin" variant="default" class="gap-1">
                                    <ShieldCheck class="h-3 w-3" /> Admin
                                </Badge>
                                <Badge v-else variant="outline">User</Badge>
                            </TableCell>
                            <TableCell class="text-muted-foreground text-xs">
                                {{ new Date(user.created_at).toLocaleDateString() }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost" size="sm"
                                        class="text-xs"
                                        @click="toggleAdmin(user)"
                                    >
                                        <ShieldCheck class="h-3.5 w-3.5 mr-1" />
                                        {{ user.is_admin ? 'Revoke Admin' : 'Make Admin' }}
                                    </Button>
                                    <Button
                                        variant="ghost" size="icon"
                                        class="text-destructive hover:text-destructive"
                                        @click="destroy(user)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <div v-if="props.users.last_page > 1" class="flex justify-center gap-1 mt-4">
                    <Button
                        v-for="link in props.users.links" :key="link.label"
                        :variant="link.active ? 'default' : 'outline'" size="sm"
                        :disabled="!link.url"
                        @click="link.url && router.get(link.url)"
                        v-html="link.label"
                    />
                </div>
            </CardContent>
        </Card>
    </AdminSettingsLayout>
</template>
