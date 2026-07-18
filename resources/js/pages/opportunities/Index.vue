<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

interface Opp {
    id: number; title: string; stage: string; estimated_value: number;
    expected_close_date: string | null;
    contact: { id: number; name: string } | null;
    owner: { id: number; name: string } | null;
    has_quote: boolean;
}

const props = defineProps<{
    opportunities: Opp[];
    stages: string[];
    stats: { open_value: number; won_this_month: number; open_count: number; overdue_tasks: number; activities_week: number };
}>();

const stageLabels: Record<string, string> = {
    new: 'New', qualified: 'Qualified', proposal: 'Proposal', won: 'Won', lost: 'Lost',
};

const columns = computed(() => props.stages.map(stage => {
    const items = props.opportunities.filter(o => o.stage === stage);
    return {
        stage,
        label: stageLabels[stage] ?? stage,
        items,
        value: items.reduce((s, o) => s + Number(o.estimated_value), 0),
    };
}));

function fmt(v: number) {
    return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
</script>

<template>
    <Head title="Pipeline" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Pipeline</h1>
                <Button as-child><Link href="/opportunities/create"><Plus class="mr-2 h-4 w-4" /> New Opportunity</Link></Button>
            </div>

            <!-- Stats (Phase E) -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <Card><CardContent class="pt-5"><p class="text-xs text-muted-foreground">Open pipeline</p><p class="text-xl font-bold">ZMW {{ fmt(stats.open_value) }}</p></CardContent></Card>
                <Card><CardContent class="pt-5"><p class="text-xs text-muted-foreground">Open deals</p><p class="text-xl font-bold">{{ stats.open_count }}</p></CardContent></Card>
                <Card><CardContent class="pt-5"><p class="text-xs text-muted-foreground">Won this month</p><p class="text-xl font-bold text-emerald-600">ZMW {{ fmt(stats.won_this_month) }}</p></CardContent></Card>
                <Card><CardContent class="pt-5"><p class="text-xs text-muted-foreground">Activities (7d)</p><p class="text-xl font-bold">{{ stats.activities_week }}</p></CardContent></Card>
                <Card><CardContent class="pt-5"><p class="text-xs text-muted-foreground">Overdue tasks</p><p class="text-xl font-bold" :class="stats.overdue_tasks ? 'text-destructive' : ''">{{ stats.overdue_tasks }}</p></CardContent></Card>
            </div>

            <!-- Board -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-start">
                <div v-for="col in columns" :key="col.stage" class="space-y-3">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-sm font-semibold">{{ col.label }}</span>
                        <span class="text-xs text-muted-foreground">{{ col.items.length }} · {{ fmt(col.value) }}</span>
                    </div>
                    <Link v-for="o in col.items" :key="o.id" :href="`/opportunities/${o.id}`" class="block">
                        <Card class="transition-shadow hover:shadow-md">
                            <CardContent class="p-3 space-y-1">
                                <p class="text-sm font-medium leading-snug">{{ o.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ o.contact?.name ?? '—' }}</p>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-sm font-semibold">ZMW {{ fmt(o.estimated_value) }}</span>
                                    <Badge v-if="o.has_quote" variant="outline" class="text-[10px] py-0">quoted</Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                    <p v-if="!col.items.length" class="text-xs text-muted-foreground px-1 py-4 text-center border border-dashed rounded-md">Empty</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
