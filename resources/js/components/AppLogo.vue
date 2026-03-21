<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const company = computed(() => (page.props.auth as any)?.company);
const logoUrl = computed(() =>
    company.value?.logo_path ? `/storage/${company.value.logo_path}` : null
);
const companyName = computed(() => company.value?.name ?? 'CloudOne Accounting');
</script>

<template>
    <!-- Company logo image -->
    <div
        v-if="logoUrl"
        class="flex aspect-square size-8 items-center justify-center rounded-md overflow-hidden bg-sidebar-primary shrink-0"
    >
        <img :src="logoUrl" :alt="companyName" class="size-8 object-cover" />
    </div>

    <!-- Default icon fallback -->
    <div
        v-else
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground shrink-0"
    >
        <AppLogoIcon class="size-5 fill-current text-white" />
    </div>

    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold text-sidebar-foreground">
            {{ companyName }}
        </span>
    </div>
</template>
