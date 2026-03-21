<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import * as companySettings from '@/routes/company/settings';

interface Company {
    name: string;
    tpin: string | null;
    vat_number: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    financial_year_end: string;
    invoice_prefix: string;
    logo_path: string | null;
    vsdc_url: string | null;
    vsdc_bhf_id: string | null;
    vsdc_dvc_srl_no: string | null;
    vsdc_initialized: boolean;
    vsdc_sdc_id: string | null;
    vsdc_mrc_no: string | null;
}

const props = defineProps<{ company: Company }>();

const form = useForm({
    name:               props.company.name,
    tpin:               props.company.tpin ?? '',
    vat_number:         props.company.vat_number ?? '',
    email:              props.company.email ?? '',
    phone:              props.company.phone ?? '',
    address:            props.company.address ?? '',
    city:               props.company.city ?? '',
    financial_year_end: props.company.financial_year_end ?? '12-31',
    invoice_prefix:     props.company.invoice_prefix ?? 'INV-',
    logo:               null as File | null,
    vsdc_url:           props.company.vsdc_url ?? '',
    vsdc_bhf_id:        props.company.vsdc_bhf_id ?? '',
    vsdc_dvc_srl_no:    props.company.vsdc_dvc_srl_no ?? '',
});

const logoPreview = ref<string | null>(
    props.company.logo_path ? `/storage/${props.company.logo_path}` : null
);

function onLogoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        form.logo = file;
        const reader = new FileReader();
        reader.onload = (ev) => { logoPreview.value = ev.target?.result as string; };
        reader.readAsDataURL(file);
    }
}

function submit() {
    form.patch(companySettings.update.url(), {
        forceFormData: true,
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Company Settings" />
        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title="Company"
                    description="Update your company profile and accounting preferences"
                />

                <form @submit.prevent="submit" class="space-y-6">

                    <!-- Identity -->
                    <div class="space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Identity</p>

                        <div class="grid gap-2">
                            <Label for="name">Company Name <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="e.g. Acme Ltd" required />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="tpin">TPIN</Label>
                                <Input id="tpin" v-model="form.tpin" placeholder="e.g. 1000000000" maxlength="10" />
                                <InputError :message="form.errors.tpin" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="vat_number">VAT Number</Label>
                                <Input id="vat_number" v-model="form.vat_number" placeholder="e.g. V00000000Z" />
                                <InputError :message="form.errors.vat_number" />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Contact -->
                    <div class="space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Contact</p>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input id="email" type="email" v-model="form.email" placeholder="accounts@example.com" />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="phone">Phone</Label>
                                <Input id="phone" v-model="form.phone" placeholder="+260 211 000 000" />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="address">Address</Label>
                            <Input id="address" v-model="form.address" placeholder="Plot 123, Cairo Road" />
                            <InputError :message="form.errors.address" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="city">City</Label>
                            <Input id="city" v-model="form.city" placeholder="Lusaka" />
                            <InputError :message="form.errors.city" />
                        </div>
                    </div>

                    <Separator />

                    <!-- Accounting Preferences -->
                    <div class="space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Accounting</p>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="financial_year_end">Financial Year End <span class="text-destructive">*</span></Label>
                                <Input id="financial_year_end" v-model="form.financial_year_end"
                                    placeholder="MM-DD" maxlength="5"
                                    pattern="\d{2}-\d{2}" />
                                <p class="text-xs text-muted-foreground">Format: MM-DD (e.g. 12-31 for 31 December)</p>
                                <InputError :message="form.errors.financial_year_end" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="invoice_prefix">Invoice Prefix <span class="text-destructive">*</span></Label>
                                <Input id="invoice_prefix" v-model="form.invoice_prefix"
                                    placeholder="INV-" maxlength="10" />
                                <p class="text-xs text-muted-foreground">Prefix for invoice numbers (e.g. INV-, 2024-)</p>
                                <InputError :message="form.errors.invoice_prefix" />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- Logo -->
                    <div class="space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Logo</p>
                        <div class="flex items-start gap-4">
                            <div class="h-16 w-16 rounded-lg border bg-muted flex items-center justify-center shrink-0 overflow-hidden">
                                <img v-if="logoPreview" :src="logoPreview" alt="Company logo" class="h-full w-full object-contain p-1" />
                                <Building2 v-else class="h-8 w-8 text-muted-foreground" />
                            </div>
                            <div class="grid gap-2 flex-1">
                                <Label for="logo">Upload Logo</Label>
                                <Input id="logo" type="file" accept="image/*" @change="onLogoChange" class="cursor-pointer" />
                                <p class="text-xs text-muted-foreground">PNG, JPG or SVG. Max 2 MB.</p>
                                <InputError :message="form.errors.logo" />
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <!-- ZRA Smart Invoice (VSDC) -->
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">ZRA Smart Invoice (VSDC)</p>
                            <p class="text-xs text-muted-foreground mt-1">Configure the local Virtual Sales Data Controller for ZRA e-invoicing.</p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="vsdc_url">VSDC URL</Label>
                            <Input id="vsdc_url" v-model="form.vsdc_url" placeholder="http://localhost:8080" />
                            <p class="text-xs text-muted-foreground">Base URL of the locally-running VSDC service.</p>
                            <InputError :message="form.errors.vsdc_url" />
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="vsdc_bhf_id">Branch / HQ ID</Label>
                                <Input id="vsdc_bhf_id" v-model="form.vsdc_bhf_id" placeholder="00" maxlength="3" />
                                <InputError :message="form.errors.vsdc_bhf_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="vsdc_dvc_srl_no">Device Serial No. (optional)</Label>
                                <Input id="vsdc_dvc_srl_no" v-model="form.vsdc_dvc_srl_no" placeholder="e.g. SN12345678" />
                                <InputError :message="form.errors.vsdc_dvc_srl_no" />
                            </div>
                        </div>

                        <div v-if="company.vsdc_initialized" class="rounded-md bg-muted p-3 text-sm space-y-1">
                            <p class="font-medium text-green-700">VSDC Initialised</p>
                            <p class="text-muted-foreground">SDC ID: <span class="font-mono">{{ company.vsdc_sdc_id }}</span></p>
                            <p class="text-muted-foreground">MRC No: <span class="font-mono">{{ company.vsdc_mrc_no }}</span></p>
                        </div>

                        <div>
                            <Button type="button" variant="outline" size="sm"
                                @click="router.post('/settings/vsdc/initialize')">
                                {{ company.vsdc_initialized ? 'Re-initialise VSDC' : 'Initialise VSDC Device' }}
                            </Button>
                            <p class="text-xs text-muted-foreground mt-1">Save your URL and Branch ID first, then initialise.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-2">
                        <Button type="submit" :disabled="form.processing">Save Changes</Button>
                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">Saved.</p>
                        </Transition>
                    </div>

                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
