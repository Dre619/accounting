<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import AdminSidebar from '@/components/AdminSidebar.vue';
</script>

<template>
    <AppShell variant="sidebar">
        <AdminSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader />

            <!-- Flash messages -->
            <div class="px-6">
                <div
                    v-if="$page.props.flash?.success"
                    class="mt-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800 dark:bg-green-950 dark:border-green-800 dark:text-green-200"
                >
                    {{ $page.props.flash.success }}
                </div>
                <div
                    v-if="$page.props.flash?.warning"
                    class="mt-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800"
                >
                    {{ $page.props.flash.warning }}
                </div>
            </div>

            <main class="flex-1 p-6">
                <slot />
            </main>
        </AppContent>
    </AppShell>
</template>
