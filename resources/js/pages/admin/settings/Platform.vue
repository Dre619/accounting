<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import InputError from '@/components/InputError.vue';
import AdminSettingsLayout from '@/layouts/admin/AdminSettingsLayout.vue';

const props = defineProps<{
    lenco: { public_key: string; secret_key: string; base_url: string };
    mail:  { mailer: string; host: string; port: number; username: string; from_address: string; from_name: string };
}>();

const form = useForm({
    lenco_public_key:  props.lenco.public_key,
    lenco_secret_key:  props.lenco.secret_key,
    lenco_base_url:    props.lenco.base_url,
    mail_mailer:       props.mail.mailer,
    mail_host:         props.mail.host,
    mail_port:         props.mail.port,
    mail_username:     props.mail.username,
    mail_password:     '',
    mail_from_address: props.mail.from_address,
    mail_from_name:    props.mail.from_name,
});

function submit() {
    form.post('/admin/settings/platform');
}
</script>

<template>
    <Head title="Platform Settings" />
    <AdminSettingsLayout>
        <form @submit.prevent="submit" class="space-y-8">

            <!-- Lenco Payment Gateway -->
            <div class="space-y-4">
                <div>
                    <p class="font-semibold text-sm">Lenco Payment Gateway</p>
                    <p class="text-xs text-muted-foreground mt-0.5">API keys for processing online subscription payments.</p>
                </div>

                <div class="grid gap-3">
                    <div class="grid gap-1.5">
                        <Label for="lenco_public_key">Public Key</Label>
                        <Input id="lenco_public_key" v-model="form.lenco_public_key" placeholder="pk_live_…" />
                        <InputError :message="form.errors.lenco_public_key" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="lenco_secret_key">Secret Key</Label>
                        <Input id="lenco_secret_key" v-model="form.lenco_secret_key" type="password" placeholder="sk_live_…" />
                        <InputError :message="form.errors.lenco_secret_key" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="lenco_base_url">API Base URL</Label>
                        <Input id="lenco_base_url" v-model="form.lenco_base_url" placeholder="https://api.lenco.co/access/v1" />
                        <InputError :message="form.errors.lenco_base_url" />
                    </div>
                </div>
            </div>

            <Separator />

            <!-- Email / SMTP -->
            <div class="space-y-4">
                <div>
                    <p class="font-semibold text-sm">Email (SMTP)</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Used to send invoices, payment confirmations, and invitations. Use <code class="font-mono bg-muted px-1 rounded">log</code> for local dev.</p>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="mail_mailer">Mailer</Label>
                        <Input id="mail_mailer" v-model="form.mail_mailer" placeholder="smtp / log" />
                        <InputError :message="form.errors.mail_mailer" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mail_host">SMTP Host</Label>
                        <Input id="mail_host" v-model="form.mail_host" placeholder="smtp.mailtrap.io" />
                        <InputError :message="form.errors.mail_host" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mail_port">SMTP Port</Label>
                        <Input id="mail_port" v-model="form.mail_port" type="number" placeholder="587" />
                        <InputError :message="form.errors.mail_port" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mail_username">SMTP Username</Label>
                        <Input id="mail_username" v-model="form.mail_username" placeholder="user@example.com" />
                        <InputError :message="form.errors.mail_username" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mail_password">SMTP Password</Label>
                        <Input id="mail_password" v-model="form.mail_password" type="password" placeholder="Leave blank to keep current" />
                        <InputError :message="form.errors.mail_password" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mail_from_address">From Address</Label>
                        <Input id="mail_from_address" v-model="form.mail_from_address" type="email" placeholder="noreply@yourapp.com" />
                        <InputError :message="form.errors.mail_from_address" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="mail_from_name">From Name</Label>
                        <Input id="mail_from_name" v-model="form.mail_from_name" placeholder="CloudOne Accounting" />
                        <InputError :message="form.errors.mail_from_name" />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <Button type="submit" :disabled="form.processing">Save Platform Settings</Button>
                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <p v-show="form.recentlySuccessful" class="text-sm text-green-600">Saved.</p>
                </Transition>
            </div>
        </form>
    </AdminSettingsLayout>
</template>
