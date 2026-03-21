<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, CreditCard, LayoutDashboard, LogOut, Settings, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import NavUser from '@/components/NavUser.vue';
import { Badge } from '@/components/ui/badge';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import admin from '@/routes/admin';
import { home } from '@/routes';

const page    = usePage();
const pending = computed(() => (page.props.pendingCount as number) ?? 0);

const navItems = [
    { title: 'Dashboard', href: admin.dashboard.url(),          icon: LayoutDashboard },
    { title: 'Payments',  href: admin.payments.index.url(),     icon: CreditCard      },
    { title: 'Companies', href: admin.companies.index.url(),    icon: Building2       },
    { title: 'Settings',  href: '/admin/settings/platform',     icon: Settings        },
];

function isActive(href: string) {
    const url = page.url.split('?')[0];
    // Exact match for root-level routes to avoid prefix collisions
    if (href === admin.dashboard.url()) {
        return url === href;
    }
    return url.startsWith(href);
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="admin.dashboard.url()">
                            <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                                <ShieldCheck class="size-5" />
                            </div>
                            <div class="ml-1 grid flex-1 text-left text-sm">
                                <span class="truncate font-semibold text-sidebar-foreground">Admin Panel</span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel class="sr-only">Navigation</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in navItems" :key="item.title">
                        <SidebarMenuButton
                            as-child
                            :is-active="isActive(item.href)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <Badge
                                    v-if="item.title === 'Payments' && pending > 0"
                                    variant="destructive"
                                    class="ml-auto text-xs"
                                >
                                    {{ pending }}
                                </Badge>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton as-child :tooltip="'Back to App'" class="text-sidebar-foreground/70 hover:text-sidebar-foreground hover:bg-sidebar-accent">
                            <Link :href="home.url()">
                                <LogOut class="rotate-180" />
                                <span>Back to App</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>
