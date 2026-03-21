<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Mail, Shield, Trash2, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

interface Member {
    id: number; name: string; email: string; role: string; joined_at: string | null;
}
interface Invitation {
    id: number; email: string; role: string; created_at: string; expires_at: string;
}

const props = defineProps<{
    members: Member[];
    pendingInvitations: Invitation[];
    planMaxUsers: number;
    currentCount: number;
    canInvite: boolean;
}>();

const inviteEmail = ref('');
const inviteRole  = ref('member');
const inviteError = ref('');

function invite() {
    inviteError.value = '';
    router.post('/settings/team/invite', {
        email: inviteEmail.value,
        role:  inviteRole.value,
    }, {
        onSuccess: () => { inviteEmail.value = ''; inviteRole.value = 'member'; },
        onError: (errors) => { inviteError.value = errors.email ?? ''; },
    });
}

function updateRole(userId: number, role: string) {
    router.patch(`/settings/team/${userId}/role`, { role });
}

function removeMember(userId: number, name: string) {
    if (confirm(`Remove ${name} from the team?`)) {
        router.delete(`/settings/team/${userId}`);
    }
}

function cancelInvitation(id: number, email: string) {
    if (confirm(`Cancel invitation to ${email}?`)) {
        router.delete(`/settings/team/invitations/${id}`);
    }
}

function resendInvitation(id: number) {
    router.post(`/settings/team/invitations/${id}/resend`);
}

const roleBadge: Record<string, 'default' | 'secondary' | 'outline'> = {
    owner: 'default', admin: 'secondary', member: 'outline', viewer: 'outline',
};
</script>

<template>
    <AppLayout>
        <Head title="Team" />
        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Team Members"
                    description="Manage who has access to this company"
                />

                <!-- Usage indicator -->
                <div class="rounded-lg border bg-muted/40 p-4 flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">
                        <strong class="text-foreground">{{ currentCount }}</strong> of
                        <strong class="text-foreground">{{ planMaxUsers }}</strong> users on your plan
                    </span>
                    <span v-if="!canInvite" class="text-amber-600 font-medium text-xs">
                        Upgrade your plan to add more users
                    </span>
                </div>

                <!-- Invite form -->
                <Card v-if="canInvite">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold flex items-center gap-2">
                            <UserPlus class="h-4 w-4" /> Invite a Team Member
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="flex-1 grid gap-1.5">
                                <Label for="invite_email">Email address</Label>
                                <Input id="invite_email" type="email" v-model="inviteEmail"
                                    placeholder="colleague@example.com" @keyup.enter="invite" />
                                <InputError :message="inviteError" />
                            </div>
                            <div class="grid gap-1.5 w-full sm:w-40">
                                <Label>Role</Label>
                                <Select v-model="inviteRole">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="admin">Admin</SelectItem>
                                        <SelectItem value="member">Member</SelectItem>
                                        <SelectItem value="viewer">Viewer</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="grid gap-1.5 justify-end">
                                <Label class="invisible">Send</Label>
                                <Button @click="invite" :disabled="!inviteEmail">
                                    <Mail class="mr-2 h-4 w-4" /> Send Invite
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Role descriptions -->
                <div class="grid sm:grid-cols-3 gap-3 text-xs text-muted-foreground">
                    <div class="rounded border p-3">
                        <p class="font-semibold text-foreground mb-1">Admin</p>
                        <p>Full access. Can manage invoices, bills, payments, journal entries, and settings.</p>
                    </div>
                    <div class="rounded border p-3">
                        <p class="font-semibold text-foreground mb-1">Member</p>
                        <p>Can create and edit invoices, bills, payments. Cannot access settings or team management.</p>
                    </div>
                    <div class="rounded border p-3">
                        <p class="font-semibold text-foreground mb-1">Viewer</p>
                        <p>Read-only access to all data. Cannot create or modify any records.</p>
                    </div>
                </div>

                <Separator />

                <!-- Current members -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold">Current Members</h3>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Joined</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="m in members" :key="m.id">
                                <TableCell class="font-medium">{{ m.name }}</TableCell>
                                <TableCell class="text-muted-foreground text-sm">{{ m.email }}</TableCell>
                                <TableCell>
                                    <div v-if="m.role === 'owner'">
                                        <Badge variant="default" class="capitalize">Owner</Badge>
                                    </div>
                                    <Select v-else :model-value="m.role" @update:model-value="updateRole(m.id, $event as string)">
                                        <SelectTrigger class="h-7 w-28 text-xs">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="admin">Admin</SelectItem>
                                            <SelectItem value="member">Member</SelectItem>
                                            <SelectItem value="viewer">Viewer</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </TableCell>
                                <TableCell class="text-sm text-muted-foreground">
                                    {{ m.joined_at ? new Date(m.joined_at).toLocaleDateString() : '—' }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <Button v-if="m.role !== 'owner'" variant="ghost" size="icon"
                                        class="text-destructive hover:text-destructive"
                                        @click="removeMember(m.id, m.name)">
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <!-- Pending invitations -->
                <div v-if="pendingInvitations.length" class="space-y-3">
                    <Separator />
                    <h3 class="text-sm font-semibold flex items-center gap-2">
                        <Shield class="h-4 w-4 text-muted-foreground" />
                        Pending Invitations
                    </h3>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Sent</TableHead>
                                <TableHead>Expires</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="inv in pendingInvitations" :key="inv.id">
                                <TableCell class="text-sm">{{ inv.email }}</TableCell>
                                <TableCell>
                                    <Badge :variant="roleBadge[inv.role] ?? 'outline'" class="capitalize text-xs">{{ inv.role }}</Badge>
                                </TableCell>
                                <TableCell class="text-muted-foreground text-sm">
                                    {{ new Date(inv.created_at).toLocaleDateString() }}
                                </TableCell>
                                <TableCell class="text-muted-foreground text-sm">
                                    {{ new Date(inv.expires_at).toLocaleDateString() }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button variant="ghost" size="sm" class="text-xs h-7"
                                            @click="resendInvitation(inv.id)">Resend</Button>
                                        <Button variant="ghost" size="icon" class="h-7 w-7 text-destructive hover:text-destructive"
                                            @click="cancelInvitation(inv.id, inv.email)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
