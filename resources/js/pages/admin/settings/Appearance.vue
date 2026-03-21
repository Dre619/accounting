<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import AdminSettingsLayout from '@/layouts/admin/AdminSettingsLayout.vue';
</script>

<template>
    <Head title="Appearance" />
    <AdminSettingsLayout>
        <div class="space-y-6 max-w-xl">
            <div>
                <p class="font-semibold text-sm">Appearance</p>
                <p class="text-xs text-muted-foreground mt-0.5">Customize the look and feel of the interface.</p>
            </div>
            <AppearanceTabs />
        </div>
    </AdminSettingsLayout>
</template>
