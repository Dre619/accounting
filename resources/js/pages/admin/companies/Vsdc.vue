<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, XCircle, AlertCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import InputError from '@/components/InputError.vue';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';
import * as adminCompanies from '@/routes/admin/companies';
import * as adminCompaniesVsdc from '@/routes/admin/companies/vsdc';

interface Company {
    id: number;
    name: string;
    tpin: string | null;
    vsdc_url: string | null;
    vsdc_bhf_id: string | null;
    vsdc_dvc_srl_no: string | null;
    vsdc_initialized: boolean;
    vsdc_sdc_id: string | null;
    vsdc_mrc_no: string | null;
    vsdc_status: string | null;
    vsdc_last_seen_at: string | null;
}

const props = defineProps<{ company: Company }>();

const form = useForm({
    vsdc_url:        props.company.vsdc_url        ?? '',
    vsdc_bhf_id:     props.company.vsdc_bhf_id     ?? '',
    vsdc_dvc_srl_no: props.company.vsdc_dvc_srl_no ?? '',
});

function save() {
    form.put(adminCompaniesVsdc.update.url(props.company.id));
}

function initialize() {
    if (confirm('Send initialization request to the VSDC device now?')) {
        router.post(adminCompaniesVsdc.initialize.url(props.company.id));
    }
}
</script>

<template>
    <Head :title="`VSDC — ${company.name}`" />
    <AdminLayout>
        <div class="max-w-2xl space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">VSDC — {{ company.name }}</h1>
                    <p v-if="company.tpin" class="text-sm text-muted-foreground mt-0.5">TPIN: {{ company.tpin }}</p>
                </div>
                <Button variant="outline" size="sm" @click="router.get(adminCompanies.index.url())">
                    ← Companies
                </Button>
            </div>

            <!-- Status banner -->
            <div v-if="company.vsdc_initialized"
                class="flex items-center gap-3 rounded-lg border px-4 py-3 text-sm"
                :class="company.vsdc_status === 'online'
                    ? 'border-green-200 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200'
                    : company.vsdc_status === 'offline'
                        ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200'
                        : 'border-amber-200 bg-amber-50 text-amber-800'">
                <CheckCircle2 v-if="company.vsdc_status === 'online'" class="h-4 w-4 shrink-0" />
                <XCircle v-else-if="company.vsdc_status === 'offline'" class="h-4 w-4 shrink-0" />
                <AlertCircle v-else class="h-4 w-4 shrink-0" />
                <span>
                    Device initialized ·
                    <span class="font-medium capitalize">{{ company.vsdc_status ?? 'status unknown' }}</span>
                    <template v-if="company.vsdc_last_seen_at">
                        · Last seen {{ new Date(company.vsdc_last_seen_at).toLocaleString() }}
                    </template>
                </span>
            </div>
            <div v-else class="flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <AlertCircle class="h-4 w-4 shrink-0" />
                <span>VSDC not yet initialized. Save credentials then click <strong>Initialize Device</strong>.</span>
            </div>

            <!-- Credentials card -->
            <Card>
                <CardHeader>
                    <CardTitle>VSDC Credentials</CardTitle>
                    <CardDescription>Set the URL and branch details for this company's VSDC instance on the VPS.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="vsdc_url">VSDC URL <span class="text-destructive">*</span></Label>
                        <Input id="vsdc_url" v-model="form.vsdc_url" placeholder="http://192.168.1.x:8080" />
                        <p class="text-xs text-muted-foreground">Base URL of the VSDC instance deployed on the VPS for this company.</p>
                        <InputError :message="form.errors.vsdc_url" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="vsdc_bhf_id">Branch ID <span class="text-destructive">*</span></Label>
                            <Input id="vsdc_bhf_id" v-model="form.vsdc_bhf_id" placeholder="000" maxlength="3" class="font-mono" />
                            <InputError :message="form.errors.vsdc_bhf_id" />
                        </div>
                        <div class="space-y-2">
                            <Label for="vsdc_dvc_srl_no">Device Serial No.</Label>
                            <Input id="vsdc_dvc_srl_no" v-model="form.vsdc_dvc_srl_no" placeholder="DEVICE123" class="font-mono" />
                            <InputError :message="form.errors.vsdc_dvc_srl_no" />
                        </div>
                    </div>
                    <div class="flex justify-end pt-2">
                        <Button @click="save" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : 'Save Credentials' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Initialization card -->
            <Card>
                <CardHeader>
                    <CardTitle>Device Initialization</CardTitle>
                    <CardDescription>Calls <code class="text-xs">/initializer/selectInitInfo</code> on the VSDC and stores the returned SDC ID and MRC number.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <template v-if="company.vsdc_initialized">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-muted-foreground text-xs mb-1">SDC ID</p>
                                <p class="font-mono font-medium">{{ company.vsdc_sdc_id ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground text-xs mb-1">MRC Number</p>
                                <p class="font-mono font-medium">{{ company.vsdc_mrc_no ?? '—' }}</p>
                            </div>
                        </div>
                        <Separator />
                    </template>
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            {{ company.vsdc_initialized ? 'Re-initialize if the device has been reset or replaced.' : 'Initialize the device to activate ZRA Smart Invoice for this company.' }}
                        </p>
                        <Button
                            :variant="company.vsdc_initialized ? 'outline' : 'default'"
                            :disabled="!form.vsdc_url || !form.vsdc_bhf_id"
                            @click="initialize">
                            {{ company.vsdc_initialized ? 'Re-initialize' : 'Initialize Device' }}
                        </Button>
                    </div>
                    <InputError :message="($page.props.errors as Record<string,string>).vsdc" />
                </CardContent>
            </Card>
        </div>
    </AdminLayout>
</template>
