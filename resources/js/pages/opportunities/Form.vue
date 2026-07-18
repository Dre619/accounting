<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Ref { id: number; name: string }
interface Opportunity {
    id: number; contact_id: number; title: string; description: string | null;
    stage: string; estimated_value: string; expected_close_date: string | null; owner_id: number | null;
}

const props = defineProps<{
    opportunity: Opportunity | null;
    contacts: Ref[];
    members: Ref[];
    stages: string[];
    defaultContactId?: number | null;
}>();

const isEdit = !!props.opportunity;
const stageLabels: Record<string, string> = { new: 'New', qualified: 'Qualified', proposal: 'Proposal' };

const form = useForm({
    contact_id:          props.opportunity?.contact_id ?? props.defaultContactId ?? null as number | null,
    title:               props.opportunity?.title ?? '',
    description:         props.opportunity?.description ?? '',
    stage:               props.opportunity?.stage ?? 'new',
    estimated_value:     props.opportunity ? Number(props.opportunity.estimated_value) : 0,
    expected_close_date: props.opportunity?.expected_close_date ?? '',
    owner_id:            props.opportunity?.owner_id ?? null,
});

function submit() {
    if (isEdit) form.put(`/opportunities/${props.opportunity!.id}`);
    else form.post('/opportunities');
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Opportunity' : 'New Opportunity'" />
    <AppLayout>
        <form @submit.prevent="submit">
            <div class="max-w-2xl mx-auto p-6 space-y-6">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Opportunity' : 'New Opportunity' }}</h1>
                    <Button type="button" variant="outline" @click="router.get('/opportunities')">Cancel</Button>
                </div>

                <Card>
                    <CardHeader><CardTitle>Details</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 space-y-2">
                            <Label>Title <span class="text-destructive">*</span></Label>
                            <Input v-model="form.title" placeholder="e.g. Annual support contract" />
                            <InputError :message="form.errors.title" />
                        </div>

                        <div class="space-y-2">
                            <Label>Customer <span class="text-destructive">*</span></Label>
                            <select v-model="form.contact_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null" disabled>Select…</option>
                                <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <InputError :message="form.errors.contact_id" />
                        </div>

                        <div class="space-y-2">
                            <Label>Stage</Label>
                            <select v-model="form.stage"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option v-for="s in stages" :key="s" :value="s">{{ stageLabels[s] ?? s }}</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label>Estimated value (ZMW)</Label>
                            <Input v-model.number="form.estimated_value" type="number" min="0" step="0.01" />
                        </div>

                        <div class="space-y-2">
                            <Label>Expected close</Label>
                            <Input v-model="form.expected_close_date" type="date" />
                        </div>

                        <div class="space-y-2">
                            <Label>Owner</Label>
                            <select v-model="form.owner_id"
                                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
                                <option :value="null">Unassigned</option>
                                <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                            </select>
                        </div>

                        <div class="col-span-2 space-y-2">
                            <Label>Notes</Label>
                            <textarea v-model="form.description" rows="3"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : isEdit ? 'Update' : 'Create Opportunity' }}
                    </Button>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
