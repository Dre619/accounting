<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import * as contacts from '@/routes/contacts';

interface Contact {
    id: number;
    type: string; name: string; tpin: string | null;
    email: string | null; phone: string | null;
    address: string | null; city: string | null; country: string;
    withholding_tax_applicable: boolean; notes: string | null;
}

const props = defineProps<{ contact: Contact | null }>();

const isEdit = !!props.contact;

const form = useForm({
    type:                       props.contact?.type ?? 'customer',
    name:                       props.contact?.name ?? '',
    tpin:                       props.contact?.tpin ?? '',
    email:                      props.contact?.email ?? '',
    phone:                      props.contact?.phone ?? '',
    address:                    props.contact?.address ?? '',
    city:                       props.contact?.city ?? '',
    country:                    props.contact?.country ?? 'Zambia',
    withholding_tax_applicable: props.contact?.withholding_tax_applicable ?? false,
    notes:                      props.contact?.notes ?? '',
});

function submit() {
    if (isEdit) {
        form.put(contacts.update.url(props.contact!.id));
    } else {
        form.post(contacts.store.url());
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Contact' : 'New Contact'" />
    <AppLayout>
        <div class="max-w-2xl mx-auto p-6 space-y-6">
            <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Contact' : 'New Contact' }}</h1>

            <form @submit.prevent="submit">
                <Card>
                    <CardHeader><CardTitle>Basic Information</CardTitle></CardHeader>
                    <CardContent class="space-y-4">

                        <!-- Type -->
                        <div class="space-y-2">
                            <Label>Contact Type <span class="text-destructive">*</span></Label>
                            <div class="flex gap-3">
                                <label v-for="t in ['customer','supplier','both']" :key="t"
                                    class="flex items-center gap-2 cursor-pointer capitalize">
                                    <input type="radio" v-model="form.type" :value="t" class="accent-primary" />
                                    {{ t }}
                                </label>
                            </div>
                            <InputError :message="form.errors.type" />
                        </div>

                        <!-- Name -->
                        <div class="space-y-2">
                            <Label for="name">Full Name / Company Name <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="e.g. Zambia Breweries PLC" autofocus />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="email">Email</Label>
                                <Input id="email" v-model="form.email" type="email" placeholder="contact@company.zm" />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="space-y-2">
                                <Label for="phone">Phone</Label>
                                <Input id="phone" v-model="form.phone" placeholder="+260 97X XXX XXX" />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="tpin">TPIN</Label>
                                <Input id="tpin" v-model="form.tpin" placeholder="1000000000" maxlength="10" />
                                <InputError :message="form.errors.tpin" />
                            </div>
                            <div class="space-y-2">
                                <Label for="city">City</Label>
                                <Input id="city" v-model="form.city" placeholder="Lusaka" />
                                <InputError :message="form.errors.city" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="address">Address</Label>
                            <Input id="address" v-model="form.address" placeholder="Plot 123, Cairo Road" />
                            <InputError :message="form.errors.address" />
                        </div>

                        <!-- WHT -->
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.withholding_tax_applicable" class="accent-primary" />
                            <span class="text-sm">Withholding tax applicable on payments from this contact</span>
                        </label>

                        <!-- Notes -->
                        <div class="space-y-2">
                            <Label for="notes">Notes</Label>
                            <textarea id="notes" v-model="form.notes"
                                class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder="Any additional information…" />
                            <InputError :message="form.errors.notes" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-3 mt-4">
                    <Button type="button" variant="outline" @click="$inertia.visit(contacts.index.url())">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : isEdit ? 'Update Contact' : 'Create Contact' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
