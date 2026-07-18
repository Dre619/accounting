<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Trophy, XCircle, FileText, Mail, Phone, StickyNote, Users, Trash2 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

interface Activity { id: number; type: 'note'|'call'|'email'|'meeting'; body: string; occurred_at: string; user: { name: string } | null }
interface Task { id: number; title: string; due_date: string | null; completed_at: string | null; assignee: { name: string } | null }
interface Opportunity {
    id: number; title: string; description: string | null; stage: string;
    estimated_value: string; expected_close_date: string | null; lost_reason: string | null;
    contact: { id: number; name: string; email: string | null } | null;
    owner: { id: number; name: string } | null;
    sales_order: { id: number; order_number: string; status: string; total: string } | null;
    activities: Activity[]; tasks: Task[];
}

const props = defineProps<{ opportunity: Opportunity }>();

const isOpen = ['new', 'qualified', 'proposal'].includes(props.opportunity.stage);
const stageVariant: Record<string, 'default'|'secondary'|'outline'|'destructive'> = {
    new: 'outline', qualified: 'secondary', proposal: 'secondary', won: 'default', lost: 'destructive',
};
const activityIcon = { note: StickyNote, call: Phone, email: Mail, meeting: Users };
const activityTypes = [
    { key: 'note', label: 'Note' }, { key: 'call', label: 'Call' },
    { key: 'email', label: 'Email' }, { key: 'meeting', label: 'Meeting' },
];

const composer = useForm({ type: 'note', body: '', occurred_at: '' });
function logActivity() {
    composer.post(`/opportunities/${props.opportunity.id}/activities`, {
        preserveScroll: true, onSuccess: () => composer.reset('body', 'occurred_at'),
    });
}
function removeActivity(id: number) { if (confirm('Remove this activity?')) router.delete(`/activities/${id}`, { preserveScroll: true }); }

function markWon() { router.post(`/opportunities/${props.opportunity.id}/won`); }
function markLost() {
    const reason = prompt('Reason for losing this opportunity? (optional)');
    if (reason !== null) router.post(`/opportunities/${props.opportunity.id}/lost`, { lost_reason: reason });
}
function convert() {
    if (confirm('Create a quote from this opportunity?')) router.post(`/opportunities/${props.opportunity.id}/convert`);
}

function fmt(v: string | number) { return Number(v).toLocaleString('en-ZM', { minimumFractionDigits: 2 }); }
function whenLabel(iso: string) { return new Date(iso).toLocaleString('en-ZM', { dateStyle: 'medium', timeStyle: 'short' }); }
</script>

<template>
    <Head :title="opportunity.title" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto w-full">

            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        {{ opportunity.title }}
                        <Badge :variant="stageVariant[opportunity.stage]" class="capitalize">{{ opportunity.stage }}</Badge>
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        <Link v-if="opportunity.contact" :href="`/contacts/${opportunity.contact.id}`" class="hover:underline">{{ opportunity.contact.name }}</Link>
                        · ZMW {{ fmt(opportunity.estimated_value) }}
                        <span v-if="opportunity.owner"> · {{ opportunity.owner.name }}</span>
                    </p>
                </div>
                <div class="flex gap-2 flex-wrap justify-end">
                    <Button variant="outline" as-child><Link :href="`/opportunities/${opportunity.id}/edit`"><Pencil class="mr-2 h-4 w-4" /> Edit</Link></Button>
                    <Button v-if="!opportunity.sales_order && opportunity.stage !== 'lost'" @click="convert"><FileText class="mr-2 h-4 w-4" /> Convert to Quote</Button>
                    <Button v-if="isOpen" variant="outline" class="text-emerald-600" @click="markWon"><Trophy class="mr-2 h-4 w-4" /> Won</Button>
                    <Button v-if="isOpen" variant="outline" class="text-destructive" @click="markLost"><XCircle class="mr-2 h-4 w-4" /> Lost</Button>
                </div>
            </div>

            <div v-if="opportunity.lost_reason" class="text-sm text-muted-foreground">
                <span class="font-medium text-destructive">Lost:</span> {{ opportunity.lost_reason }}
            </div>

            <!-- Linked quote -->
            <Card v-if="opportunity.sales_order">
                <CardContent class="pt-4 flex items-center gap-3 text-sm">
                    <span class="text-muted-foreground">Quote:</span>
                    <Link :href="`/sales-orders/${opportunity.sales_order.id}`" class="inline-flex items-center gap-1 underline hover:no-underline">
                        {{ opportunity.sales_order.order_number }}
                        <Badge variant="outline" class="capitalize text-[10px] py-0">{{ opportunity.sales_order.status }}</Badge>
                    </Link>
                    <span class="ml-auto font-medium">ZMW {{ fmt(opportunity.sales_order.total) }}</span>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Timeline -->
                <Card class="md:col-span-2">
                    <CardHeader><CardTitle>Activity</CardTitle></CardHeader>
                    <CardContent class="space-y-5">
                        <form class="space-y-2" @submit.prevent="logActivity">
                            <div class="flex flex-wrap gap-2">
                                <button v-for="t in activityTypes" :key="t.key" type="button"
                                    class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs transition-colors"
                                    :class="composer.type === t.key ? 'border-primary bg-primary/10 text-primary' : 'border-input text-muted-foreground hover:bg-accent'"
                                    @click="composer.type = t.key">
                                    <component :is="activityIcon[t.key as keyof typeof activityIcon]" class="h-3.5 w-3.5" /> {{ t.label }}
                                </button>
                                <Input v-model="composer.occurred_at" type="datetime-local" class="ml-auto h-8 w-auto text-xs" />
                            </div>
                            <textarea v-model="composer.body" rows="2" required
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                :placeholder="`Log a ${composer.type}…`" />
                            <div class="flex justify-end"><Button type="submit" size="sm" :disabled="composer.processing || !composer.body">Log activity</Button></div>
                        </form>

                        <ol class="space-y-4">
                            <li v-for="a in opportunity.activities" :key="a.id" class="flex gap-3 group">
                                <div class="mt-0.5 rounded-full bg-muted p-2 shrink-0"><component :is="activityIcon[a.type]" class="h-4 w-4 text-muted-foreground" /></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                        <span class="font-medium capitalize text-foreground">{{ a.type }}</span>
                                        <span>· {{ whenLabel(a.occurred_at) }}</span>
                                        <span v-if="a.user">· {{ a.user.name }}</span>
                                        <button type="button" class="ml-auto opacity-0 group-hover:opacity-100 text-muted-foreground hover:text-destructive" @click="removeActivity(a.id)"><Trash2 class="h-3.5 w-3.5" /></button>
                                    </div>
                                    <p class="text-sm whitespace-pre-line mt-0.5">{{ a.body }}</p>
                                </div>
                            </li>
                            <li v-if="!opportunity.activities.length" class="text-sm text-muted-foreground py-2">No activity yet.</li>
                        </ol>
                    </CardContent>
                </Card>

                <!-- Side: details + tasks -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader><CardTitle>Details</CardTitle></CardHeader>
                        <CardContent class="text-sm space-y-2">
                            <div><dt class="text-muted-foreground text-xs">Expected close</dt><dd>{{ opportunity.expected_close_date ?? '—' }}</dd></div>
                            <div v-if="opportunity.description"><dt class="text-muted-foreground text-xs">Notes</dt><dd class="whitespace-pre-line">{{ opportunity.description }}</dd></div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Tasks</CardTitle></CardHeader>
                        <CardContent class="space-y-2">
                            <div v-for="t in opportunity.tasks" :key="t.id" class="text-sm flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full shrink-0" :class="t.completed_at ? 'bg-emerald-500' : 'bg-amber-500'" />
                                <span :class="t.completed_at ? 'line-through text-muted-foreground' : ''">{{ t.title }}</span>
                                <span v-if="t.due_date" class="ml-auto text-xs text-muted-foreground">{{ t.due_date }}</span>
                            </div>
                            <p v-if="!opportunity.tasks.length" class="text-sm text-muted-foreground">No tasks. Add one from the Tasks page.</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div><Button variant="ghost" @click="router.get('/opportunities')">← Back to pipeline</Button></div>
        </div>
    </AppLayout>
</template>
