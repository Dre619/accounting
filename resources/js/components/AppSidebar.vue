<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    BarChart3,
    BookOpen,
    BookText,
    CreditCard,
    FileText,
    LayoutGrid,
    RefreshCw,
    Receipt,
    UserSquare2,
    Users,
    Wallet,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Badge } from '@/components/ui/badge';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import * as billing from '@/routes/billing';
import * as bills from '@/routes/bills';
import * as contacts from '@/routes/contacts';
import * as invoices from '@/routes/invoices';
import * as payments from '@/routes/payments';
import * as reports from '@/routes/reports';
import * as accounts from '@/routes/accounts';
import * as journal from '@/routes/journal';
import type { NavItem } from '@/types';

const page  = usePage();
const auth  = computed(() => page.props.auth as { onTrial: boolean; trialEndsAt: string | null; subscription: { plan: { name: string } } | null });
const features = computed(() => (page.props.planFeatures as string[]) ?? []);
const can = (f: string) => features.value.includes(f);

const trialDaysLeft = computed(() => {
    if (!auth.value.trialEndsAt) return 0;
    const diff = new Date(auth.value.trialEndsAt).getTime() - Date.now();
    return Math.max(0, Math.ceil(diff / 86_400_000));
});

const allNavItems = [
    { title: 'Dashboard',  href: dashboard.url(),          icon: LayoutGrid,   feature: null         },
    { title: 'Invoices',   href: invoices.index.url(),     icon: FileText,     feature: 'invoices'   },
    { title: 'Recurring',  href: '/recurring',             icon: RefreshCw,    feature: 'recurring'  },
    { title: 'Bills',      href: bills.index.url(),        icon: Receipt,      feature: 'bills'      },
    { title: 'Employees',  href: '/employees',             icon: UserSquare2,  feature: 'payroll'    },
    { title: 'Payroll',    href: '/payroll',               icon: Banknote,     feature: 'payroll'    },
    { title: 'Contacts',   href: contacts.index.url(),     icon: Users,        feature: 'contacts'   },
    { title: 'Payments',   href: payments.index.url(),     icon: Wallet,       feature: 'payments'   },
    { title: 'Accounts',   href: accounts.index.url(),     icon: BookOpen,     feature: 'accounts'   },
    { title: 'Journal',    href: journal.index.url(),      icon: BookText,     feature: 'journals'   },
    { title: 'Reports',    href: reports.index.url(),      icon: BarChart3,    feature: null         },
];

const mainNavItems = computed<NavItem[]>(() =>
    allNavItems.filter(item => item.feature === null || can(item.feature))
);

const footerNavItems: NavItem[] = [
    { title: 'Billing',    href: billing.status.url(),     icon: CreditCard },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard.url()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <!-- Trial / subscription status pill -->
            <div class="px-3 pb-1">
                <Link
                    :href="billing.plans.url()"
                    class="flex items-center gap-2 rounded-md px-2 py-1.5 text-xs hover:bg-sidebar-accent transition-colors"
                >
                    <Zap class="h-3.5 w-3.5 text-amber-400 shrink-0" />
                    <span v-if="auth.onTrial" class="text-sidebar-foreground/70">
                        Trial — <strong class="text-sidebar-foreground">{{ trialDaysLeft }}d left</strong>
                    </span>
                    <span v-else-if="auth.subscription" class="text-sidebar-foreground/70">
                        <strong class="text-sidebar-foreground">{{ auth.subscription.plan.name }}</strong>
                    </span>
                    <span v-else class="text-red-400 font-medium">Subscribe</span>
                    <Badge variant="outline" class="ml-auto text-[10px] py-0 border-sidebar-border text-sidebar-foreground/70">
                        {{ auth.onTrial ? 'Trial' : auth.subscription ? 'Active' : 'Inactive' }}
                    </Badge>
                </Link>
            </div>

            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
