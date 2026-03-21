<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: string;
    rows?: number;
    placeholder?: string;
    disabled?: boolean;
}>();

const model = defineModel<string>();
</script>

<template>
    <textarea
        v-bind="$attrs"
        :class="cn(
            'flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
            props.class
        )"
        :rows="rows"
        :placeholder="placeholder"
        :disabled="disabled"
        v-model="model"
    />
</template>
