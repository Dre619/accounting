<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AdminSettingsLayout from '@/layouts/admin/AdminSettingsLayout.vue';
import { send } from '@/routes/verification';

defineProps<{ mustVerifyEmail: boolean; status?: string }>();

const user = computed(() => usePage().props.auth.user);
</script>

<template>
    <Head title="Profile" />
    <AdminSettingsLayout>
        <div class="space-y-6 max-w-xl">
            <div>
                <p class="font-semibold text-sm">Profile Information</p>
                <p class="text-xs text-muted-foreground mt-0.5">Update your name and email address.</p>
            </div>

            <Form
                v-bind="ProfileController.update.form()"
                class="space-y-4"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-1.5">
                    <Label for="name">Name</Label>
                    <Input id="name" name="name" :default-value="user.name" required autocomplete="name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="email">Email Address</Label>
                    <Input id="email" name="email" type="email" :default-value="user.email" required autocomplete="username" />
                    <InputError :message="errors.email" />
                </div>

                <div v-if="mustVerifyEmail && !user.email_verified_at" class="text-sm text-muted-foreground">
                    Your email is unverified.
                    <Link :href="send()" as="button" class="underline hover:text-foreground transition-colors">
                        Resend verification email.
                    </Link>
                    <p v-if="status === 'verification-link-sent'" class="mt-1 text-green-600 font-medium text-xs">
                        Verification link sent.
                    </p>
                </div>

                <div class="flex items-center gap-4 pt-1">
                    <Button :disabled="processing">Save</Button>
                    <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                        <p v-show="recentlySuccessful" class="text-sm text-green-600">Saved.</p>
                    </Transition>
                </div>
            </Form>
        </div>
    </AdminSettingsLayout>
</template>
