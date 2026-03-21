<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';

const { isCurrentOrParentUrl } = useCurrentUrl();

const nav = [
    { title: 'Profile',    href: '/admin/settings/profile'     },
    { title: 'Security',   href: '/admin/settings/security'    },
    { title: 'Appearance', href: '/admin/settings/appearance'  },
    { title: 'Platform',   href: '/admin/settings/platform'    },
    { title: 'Plans',      href: '/admin/settings/plans'       },
    { title: 'Users',      href: '/admin/settings/users'       },
];
</script>

<template>
    <AdminLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Admin Settings</h1>
            <p class="text-muted-foreground text-sm mt-1">Manage platform configuration, subscription plans, and users.</p>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Side nav -->
            <aside class="lg:w-44 shrink-0">
                <nav class="flex flex-col gap-0.5">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="isCurrentOrParentUrl(item.href)
                            ? 'bg-muted text-foreground'
                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'"
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <slot />
            </div>
        </div>
    </AdminLayout>
</template>
