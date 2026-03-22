<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Building2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

const props = defineProps<{
    invitation: {
        token: string;
        email: string;
        role: string;
        company: { name: string };
    };
}>();

const form = useForm({
    name:                  '',
    password:              '',
    password_confirmation: '',
});

function submit() {
    form.post(`/invitations/${props.invitation.token}/register`, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Create Account" />
    <div class="min-h-screen flex items-center justify-center p-6" style="background: linear-gradient(135deg, #0f2044 0%, #1a3a6e 100%);">
        <Card class="w-full max-w-md">
            <CardHeader class="text-center pb-2">
                <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-primary/10 flex items-center justify-center">
                    <Building2 class="h-7 w-7 text-primary" />
                </div>
                <CardTitle class="text-xl">Create your account</CardTitle>
                <p class="text-sm text-muted-foreground mt-1">
                    Join <strong>{{ invitation.company.name }}</strong> as
                    <span class="capitalize">{{ invitation.role }}</span>
                </p>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit" class="space-y-4">

                    <!-- Email — read only, fixed to invitation email -->
                    <div class="space-y-1.5">
                        <Label>Email</Label>
                        <Input :value="invitation.email" disabled class="bg-muted text-muted-foreground cursor-not-allowed" />
                        <p class="text-xs text-muted-foreground">This is the email the invitation was sent to.</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="name">Full Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Your full name"
                            autofocus
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="password">Password</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            placeholder="Choose a strong password"
                            required
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="password_confirmation">Confirm Password</Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            placeholder="Repeat your password"
                            required
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>

                    <Button type="submit" class="w-full mt-2" :disabled="form.processing">
                        Create Account &amp; Join Team
                    </Button>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
