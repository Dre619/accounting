<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, CheckCircle2 } from 'lucide-vue-next';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const highlights = [
    'ZRA-compliant invoicing with 16% VAT auto-applied',
    'Double-entry bookkeeping — always balanced',
    'P&L, Balance Sheet & VAT Summary reports',
    'Manage customers, suppliers and payments',
];
</script>

<template>
    <div class="flex min-h-svh">

        <!-- Left: branded panel — hidden on mobile -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-2/5 flex-col justify-between p-12 text-white"
             style="background: linear-gradient(135deg, #0f2044 0%, #1a3a6e 60%, #1e4080 100%);">

            <!-- Logo -->
            <Link :href="home.url()" class="flex items-center gap-2.5 w-fit">
                <div class="h-9 w-9 rounded-lg flex items-center justify-center shrink-0"
                     style="background: #f97316;">
                    <BookOpen class="h-5 w-5 text-white" />
                </div>
                <span class="font-bold text-lg tracking-tight">CloudOne Accounting</span>
            </Link>

            <!-- Centre copy -->
            <div class="space-y-8">
                <div>
                    <h2 class="text-3xl font-extrabold leading-tight mb-3">
                        Simple accounting for<br />
                        <span style="color: #fb923c;">Zambian businesses</span>
                    </h2>
                    <p class="text-blue-200 text-base leading-relaxed">
                        Everything you need to manage your books — in Kwacha, for Zambia.
                    </p>
                </div>
                <ul class="space-y-3">
                    <li v-for="h in highlights" :key="h" class="flex items-start gap-3 text-sm text-blue-100">
                        <CheckCircle2 class="h-4 w-4 mt-0.5 shrink-0" style="color: #fb923c;" />
                        {{ h }}
                    </li>
                </ul>
            </div>

            <!-- Footer note -->
            <p class="text-xs text-blue-400">© {{ new Date().getFullYear() }} CloudOne Technologies Ltd · Lusaka, Zambia</p>
        </div>

        <!-- Right: form panel -->
        <div class="flex flex-1 flex-col items-center justify-center p-6 md:p-12 bg-background">

            <!-- Mobile logo -->
            <Link :href="home.url()" class="flex items-center gap-2 mb-10 lg:hidden">
                <div class="h-8 w-8 rounded-lg flex items-center justify-center"
                     style="background: #f97316;">
                    <BookOpen class="h-4 w-4 text-white" />
                </div>
                <span class="font-bold text-base tracking-tight text-foreground">CloudOne Accounting</span>
            </Link>

            <div class="w-full max-w-sm">
                <!-- Title block -->
                <div class="mb-8">
                    <h1 class="text-2xl font-extrabold text-foreground">{{ title }}</h1>
                    <p class="mt-1.5 text-sm text-muted-foreground">{{ description }}</p>
                </div>

                <slot />
            </div>
        </div>

    </div>
</template>
