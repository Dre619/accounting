<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AdminSettingsLayout from '@/layouts/admin/AdminSettingsLayout.vue';
</script>

<template>
    <Head title="Security" />
    <AdminSettingsLayout>
        <div class="space-y-6 max-w-xl">
            <div>
                <p class="font-semibold text-sm">Change Password</p>
                <p class="text-xs text-muted-foreground mt-0.5">Use a long, random password to keep your admin account secure.</p>
            </div>

            <Form
                v-bind="SecurityController.update.form()"
                :options="{ preserveScroll: true }"
                reset-on-success
                :reset-on-error="['password', 'password_confirmation', 'current_password']"
                class="space-y-4"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-1.5">
                    <Label for="current_password">Current Password</Label>
                    <PasswordInput id="current_password" name="current_password" autocomplete="current-password" placeholder="Current password" />
                    <InputError :message="errors.current_password" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password">New Password</Label>
                    <PasswordInput id="password" name="password" autocomplete="new-password" placeholder="New password" />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-1.5">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <PasswordInput id="password_confirmation" name="password_confirmation" autocomplete="new-password" placeholder="Confirm password" />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <div class="flex items-center gap-4 pt-1">
                    <Button :disabled="processing">Save Password</Button>
                    <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                        <p v-show="recentlySuccessful" class="text-sm text-green-600">Saved.</p>
                    </Transition>
                </div>
            </Form>
        </div>
    </AdminSettingsLayout>
</template>
