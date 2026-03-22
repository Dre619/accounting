<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Building2, CheckCircle2, LogIn, UserPlus } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    invitation: {
        token: string;
        email: string;
        role: string;
        company: { name: string };
        expires_at: string;
    };
    hasAccount: boolean;
}>();

function accept() {
    router.post(`/invitations/${props.invitation.token}/accept`);
}
</script>

<template>
    <Head title="Accept Invitation" />
    <div class="min-h-screen flex items-center justify-center p-6" style="background: linear-gradient(135deg, #0f2044 0%, #1a3a6e 100%);">
        <Card class="w-full max-w-md">
            <CardHeader class="text-center pb-2">
                <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center">
                    <Building2 class="h-7 w-7 text-primary" />
                </div>
                <CardTitle class="text-xl">You've been invited!</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6 text-center">
                <div>
                    <p class="text-muted-foreground">You've been invited to join</p>
                    <p class="text-2xl font-bold mt-1">{{ invitation.company.name }}</p>
                    <p class="text-sm text-muted-foreground mt-1">
                        as a <span class="font-medium capitalize text-foreground">{{ invitation.role }}</span>
                    </p>
                </div>

                <div class="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                    This invitation was sent to <strong class="text-foreground">{{ invitation.email }}</strong>.
                </div>

                <!-- New user: no account yet → register to accept -->
                <template v-if="!hasAccount">
                    <p class="text-sm text-muted-foreground">
                        No account exists for this email yet. Create one to join the team.
                    </p>
                    <div class="flex flex-col gap-3">
                        <Button class="w-full" as-child>
                            <Link :href="`/invitations/${invitation.token}/register`">
                                <UserPlus class="mr-2 h-4 w-4" /> Create Account &amp; Accept
                            </Link>
                        </Button>
                        <Button variant="outline" class="w-full" as-child>
                            <Link href="/">Decline</Link>
                        </Button>
                    </div>
                </template>

                <!-- Existing user: accept directly (if already logged in) or log in first -->
                <template v-else>
                    <p class="text-sm text-muted-foreground">
                        Log in as <strong class="text-foreground">{{ invitation.email }}</strong> to accept.
                    </p>
                    <div class="flex flex-col gap-3">
                        <Button class="w-full" @click="accept">
                            <CheckCircle2 class="mr-2 h-4 w-4" /> Accept Invitation
                        </Button>
                        <Button variant="outline" class="w-full" as-child>
                            <Link href="/login">
                                <LogIn class="mr-2 h-4 w-4" /> Log In First
                            </Link>
                        </Button>
                        <Button variant="ghost" size="sm" class="w-full" as-child>
                            <Link href="/">Decline</Link>
                        </Button>
                    </div>
                </template>

                <p class="text-xs text-muted-foreground">
                    Expires {{ new Date(invitation.expires_at).toLocaleDateString('en-ZM', { day: 'numeric', month: 'long', year: 'numeric' }) }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
