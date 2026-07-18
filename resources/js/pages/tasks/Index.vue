<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Check, Trash2, RotateCcw } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

interface Task {
    id: number; title: string; notes: string | null; due_date: string | null;
    completed_at: string | null; assignee: { id: number; name: string } | null;
    related_name: string | null; related_id: number | null; is_overdue: boolean;
}
interface Member { id: number; name: string }

const props = defineProps<{
    tasks: Task[];
    filter: string;
    members: Member[];
    counts: Record<string, number>;
}>();

const tabs = [
    { key: 'open', label: 'Open' },
    { key: 'completed', label: 'Completed' },
    { key: 'all', label: 'All' },
];

const showNew = ref(false);
const form = useForm({ title: '', notes: '', due_date: '', assigned_to: null as number | null });

function create() {
    form.post('/tasks', { preserveScroll: true, onSuccess: () => { form.reset(); showNew.value = false; } });
}
function complete(id: number) { router.post(`/tasks/${id}/complete`, {}, { preserveScroll: true }); }
function destroy(id: number)  { if (confirm('Delete this task?')) router.delete(`/tasks/${id}`, { preserveScroll: true }); }

function due(t: Task) {
    if (!t.due_date) return '—';
    return new Date(t.due_date).toLocaleDateString('en-ZM', { dateStyle: 'medium' });
}
</script>

<template>
    <Head title="Tasks" />
    <AppLayout>
        <div class="flex flex-col gap-6 p-6 max-w-4xl mx-auto w-full">

            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Tasks</h1>
                <Button @click="showNew = !showNew"><Plus class="mr-2 h-4 w-4" /> New Task</Button>
            </div>

            <Card v-if="showNew">
                <CardHeader><CardTitle>New task</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid grid-cols-1 md:grid-cols-2 gap-3" @submit.prevent="create">
                        <div class="md:col-span-2 space-y-1">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="form.title" required placeholder="e.g. Call Acme about renewal" />
                        </div>
                        <div class="space-y-1">
                            <Label for="due">Due date</Label>
                            <Input id="due" v-model="form.due_date" type="date" />
                        </div>
                        <div class="space-y-1">
                            <Label>Assign to</Label>
                            <select v-model="form.assigned_to"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">Unassigned</option>
                                <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>
                        <div class="md:col-span-2 space-y-1">
                            <Label for="notes">Notes</Label>
                            <textarea id="notes" v-model="form.notes" rows="2"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                        </div>
                        <div class="md:col-span-2 flex justify-end gap-2">
                            <Button type="button" variant="outline" @click="showNew = false">Cancel</Button>
                            <Button type="submit" :disabled="form.processing || !form.title">Add task</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <div class="flex gap-2 flex-wrap">
                <Button v-for="t in tabs" :key="t.key"
                    :variant="filter === t.key ? 'default' : 'outline'" size="sm"
                    @click="router.get('/tasks', { filter: t.key })">
                    {{ t.label }}
                    <span class="ml-1 text-xs opacity-70">({{ counts[t.key] ?? (t.key === 'all' ? '' : 0) }})</span>
                </Button>
                <Badge v-if="counts.overdue" variant="destructive" class="ml-auto self-center">{{ counts.overdue }} overdue</Badge>
            </div>

            <Card>
                <CardContent class="pt-4 divide-y">
                    <div v-for="t in tasks" :key="t.id" class="flex items-start gap-3 py-3 first:pt-0 group">
                        <button type="button"
                            class="mt-0.5 h-5 w-5 shrink-0 rounded-full border flex items-center justify-center transition-colors"
                            :class="t.completed_at ? 'bg-primary border-primary text-primary-foreground' : 'border-input hover:border-primary'"
                            @click="complete(t.id)">
                            <Check v-if="t.completed_at" class="h-3.5 w-3.5" />
                        </button>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium" :class="t.completed_at ? 'line-through text-muted-foreground' : ''">{{ t.title }}</p>
                            <p v-if="t.notes" class="text-xs text-muted-foreground mt-0.5">{{ t.notes }}</p>
                            <div class="flex items-center gap-2 text-xs text-muted-foreground mt-1">
                                <span :class="t.is_overdue ? 'text-destructive font-medium' : ''">Due {{ due(t) }}</span>
                                <span v-if="t.assignee">· {{ t.assignee.name }}</span>
                                <Link v-if="t.related_id" :href="`/contacts/${t.related_id}`" class="underline hover:no-underline">· {{ t.related_name }}</Link>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <Button variant="ghost" size="icon" class="h-7 w-7" :title="t.completed_at ? 'Reopen' : 'Complete'" @click="complete(t.id)">
                                <RotateCcw v-if="t.completed_at" class="h-4 w-4" />
                                <Check v-else class="h-4 w-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-7 w-7 text-destructive hover:text-destructive" @click="destroy(t.id)">
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <div v-if="!tasks.length" class="py-10 text-center text-muted-foreground text-sm">No tasks here.</div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
