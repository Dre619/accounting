<script setup lang="ts">
import { Toaster } from 'vue-sonner';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AdminSidebar from '@/components/AdminSidebar.vue';
import { useFlashToast } from '@/composables/useFlashToast';

useFlashToast();
</script>

<template>
    <AppShell variant="sidebar">
        <AdminSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader />
            <main class="flex-1 p-6">
                <slot />
            </main>
        </AppContent>
    </AppShell>
    <Toaster position="top-right" rich-colors close-button />
</template>
