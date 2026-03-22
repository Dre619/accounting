<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import InputError from '@/components/InputError.vue';
import AdminSettingsLayout from '@/layouts/admin/AdminSettingsLayout.vue';

const props = defineProps<{
    lenco:   { public_key: string; secret_key: string; base_url: string };
    mail:    { mailer: string; host: string; port: number; username: string; from_address: string; from_name: string };
    banking: { bank_name: string; account_name: string; account_number: string; branch: string; swift_code: string; sort_code: string; mobile_money: string; instructions: string };
}>();

const form = useForm({
    lenco_public_key:    props.lenco.public_key,
    lenco_secret_key:    props.lenco.secret_key,
    lenco_base_url:      props.lenco.base_url,
    mail_mailer:         props.mail.mailer,
    mail_host:           props.mail.host,
    mail_port:           props.mail.port,
    mail_username:       props.mail.username,
    mail_password:       '',
    mail_from_address:   props.mail.from_address,
    mail_from_name:      props.mail.from_name,
    bank_name:           props.banking.bank_name,
    bank_account_name:   props.banking.account_name,
    bank_account_number: props.banking.account_number,
    bank_branch:         props.banking.branch,
    bank_swift_code:     props.banking.swift_code,
    bank_sort_code:      props.banking.sort_code,
    bank_mobile_money:   props.banking.mobile_money,
    bank_instructions:   props.banking.instructions,
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

            <Separator />

            <!-- Banking / Offline Payment Details -->
            <div class="space-y-4">
                <div>
                    <p class="font-semibold text-sm">Offline Payment Details</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Shown to customers who choose bank transfer or mobile money on the checkout page.</p>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="bank_name">Bank Name</Label>
                        <Input id="bank_name" v-model="form.bank_name" placeholder="e.g. Zanaco Bank" />
                        <InputError :message="form.errors.bank_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="bank_account_name">Account Name</Label>
                        <Input id="bank_account_name" v-model="form.bank_account_name" placeholder="e.g. CloudOne Technologies Ltd" />
                        <InputError :message="form.errors.bank_account_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="bank_account_number">Account Number</Label>
                        <Input id="bank_account_number" v-model="form.bank_account_number" placeholder="e.g. 1234567890" />
                        <InputError :message="form.errors.bank_account_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="bank_branch">Branch</Label>
                        <Input id="bank_branch" v-model="form.bank_branch" placeholder="e.g. Cairo Road, Lusaka" />
                        <InputError :message="form.errors.bank_branch" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="bank_swift_code">SWIFT / BIC Code <span class="text-muted-foreground">(optional)</span></Label>
                        <Input id="bank_swift_code" v-model="form.bank_swift_code" placeholder="e.g. ZNCOZMLU" />
                        <InputError :message="form.errors.bank_swift_code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="bank_sort_code">Sort Code <span class="text-muted-foreground">(optional)</span></Label>
                        <Input id="bank_sort_code" v-model="form.bank_sort_code" placeholder="e.g. 12-34-56" />
                        <InputError :message="form.errors.bank_sort_code" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="bank_mobile_money">Mobile Money Number <span class="text-muted-foreground">(optional)</span></Label>
                        <Input id="bank_mobile_money" v-model="form.bank_mobile_money" placeholder="e.g. Airtel +260 97 1234567" />
                        <InputError :message="form.errors.bank_mobile_money" />
                    </div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label for="bank_instructions">Payment Instructions</Label>
                        <Input id="bank_instructions" v-model="form.bank_instructions" placeholder="Use your company name as the payment reference." />
                        <InputError :message="form.errors.bank_instructions" />
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
