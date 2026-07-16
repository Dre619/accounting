<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { ArrowDown, ArrowUp, Eye, ExternalLink, ImagePlus, Loader2, Pencil, Plus, Trash2, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import AdminSettingsLayout from '@/layouts/admin/AdminSettingsLayout.vue';

interface ManualImage {
    id: number;
    url: string;
    caption: string | null;
}

interface Section {
    id: number;
    slug: string;
    title: string;
    summary: string | null;
    body: string | null;
    is_published: boolean;
    sort_order: number;
    images: ManualImage[];
}

const props = defineProps<{ sections: Section[] }>();

const editing = ref<number | null>(null);
const creating = ref(false);

// ── Editing an existing section ──────────────────────────────────────────────

const editForm = useForm({
    title: '',
    summary: '',
    body: '',
    is_published: true as boolean,
});

function startEditing(section: Section) {
    editForm.clearErrors();
    editForm.title = section.title;
    editForm.summary = section.summary ?? '';
    editForm.body = section.body ?? '';
    editForm.is_published = section.is_published;
    previewHtml.value = '';
    showPreview.value = false;
    editing.value = section.id;
}

function save(section: Section) {
    editForm.put(`/admin/settings/manual/${section.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
        },
    });
}

function destroy(section: Section) {
    if (!confirm(`Delete "${section.title}"? Its images will be deleted too. This cannot be undone.`)) return;

    router.delete(`/admin/settings/manual/${section.id}`, { preserveScroll: true });
}

// ── Creating ─────────────────────────────────────────────────────────────────

const createForm = useForm({
    title: '',
    summary: '',
    body: '',
    is_published: false as boolean,
});

function create() {
    createForm.post('/admin/settings/manual', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            creating.value = false;
        },
    });
}

// ── Reordering ───────────────────────────────────────────────────────────────

function move(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= props.sections.length) return;

    const ids = props.sections.map((s) => s.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];

    router.post('/admin/settings/manual/reorder', { ids }, { preserveScroll: true });
}

// ── Live preview ─────────────────────────────────────────────────────────────
// Rendered by the server so the preview always matches the published page.

const showPreview = ref(false);
const previewHtml = ref('');
const previewing = ref(false);
let previewTimer: ReturnType<typeof setTimeout> | undefined;

async function renderPreview(body: string) {
    previewing.value = true;
    try {
        const response = await fetch('/admin/settings/manual/preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ body }),
        });
        if (!response.ok) throw new Error(String(response.status));
        previewHtml.value = (await response.json()).html;
    } catch {
        previewHtml.value = '<p class="text-destructive">Preview unavailable.</p>';
    } finally {
        previewing.value = false;
    }
}

watch([showPreview, () => editForm.body], ([visible, body]) => {
    if (!visible) return;
    clearTimeout(previewTimer);
    previewTimer = setTimeout(() => renderPreview(body), 350);
});

// ── Images ───────────────────────────────────────────────────────────────────

const uploadingFor = ref<number | null>(null);

function uploadImage(section: Section, event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    uploadingFor.value = section.id;

    router.post(
        `/admin/settings/manual/${section.id}/images`,
        { image: file, caption: '' },
        {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                uploadingFor.value = null;
                input.value = '';
            },
        },
    );
}

function saveCaption(image: ManualImage, caption: string) {
    if (caption === (image.caption ?? '')) return;

    router.put(`/admin/settings/manual/images/${image.id}`, { caption }, { preserveScroll: true });
}

function deleteImage(image: ManualImage) {
    if (!confirm('Remove this image?')) return;

    router.delete(`/admin/settings/manual/images/${image.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="User Manual" />

    <AdminSettingsLayout>
        <div class="space-y-4">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">User Manual</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Sections are published to the public manual in the order shown. Body text supports Markdown.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <a href="/manual" target="_blank" rel="noopener">
                            <ExternalLink class="mr-2 h-4 w-4" /> View live
                        </a>
                    </Button>
                    <Button size="sm" @click="creating = !creating">
                        <Plus class="mr-2 h-4 w-4" /> Add section
                    </Button>
                </div>
            </div>

            <!-- New section -->
            <Card v-if="creating" class="border-primary">
                <CardHeader>
                    <h3 class="font-semibold">New section</h3>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="space-y-2">
                        <Label for="new-title">Title</Label>
                        <Input id="new-title" v-model="createForm.title" placeholder="e.g. Creating an invoice" />
                        <InputError :message="createForm.errors.title" />
                    </div>
                    <div class="space-y-2">
                        <Label for="new-summary">Summary <span class="text-muted-foreground">(optional)</span></Label>
                        <Input id="new-summary" v-model="createForm.summary" placeholder="One line shown under the heading" />
                        <InputError :message="createForm.errors.summary" />
                    </div>
                    <div class="space-y-2">
                        <Label for="new-body">Body (Markdown)</Label>
                        <Textarea id="new-body" v-model="createForm.body" :rows="8" class="font-mono text-sm" />
                        <InputError :message="createForm.errors.body" />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="new-published" v-model="createForm.is_published" />
                        <Label for="new-published" class="font-normal">Publish immediately</Label>
                    </div>
                    <div class="flex gap-2">
                        <Button :disabled="createForm.processing" @click="create">Create section</Button>
                        <Button variant="ghost" @click="creating = false; createForm.reset()">Cancel</Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty -->
            <Card v-if="!sections.length && !creating">
                <CardContent class="py-12 text-center text-sm text-muted-foreground">
                    No sections yet. Click <strong>Add section</strong> to write the first one.
                </CardContent>
            </Card>

            <!-- Sections -->
            <Card v-for="(section, index) in sections" :key="section.id">
                <CardHeader class="flex flex-row items-start justify-between gap-3 space-y-0">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold">{{ section.title }}</h3>
                            <Badge v-if="!section.is_published" variant="outline">Draft</Badge>
                            <Badge v-if="section.images.length" variant="secondary">
                                {{ section.images.length }} image{{ section.images.length === 1 ? '' : 's' }}
                            </Badge>
                        </div>
                        <p class="mt-1 truncate text-xs text-muted-foreground">/manual#{{ section.slug }}</p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <Button variant="ghost" size="icon" :disabled="index === 0" title="Move up" @click="move(index, -1)">
                            <ArrowUp class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="index === sections.length - 1"
                            title="Move down"
                            @click="move(index, 1)"
                        >
                            <ArrowDown class="h-4 w-4" />
                        </Button>
                        <Button
                            v-if="editing !== section.id"
                            variant="ghost"
                            size="icon"
                            title="Edit"
                            @click="startEditing(section)"
                        >
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button v-else variant="ghost" size="icon" title="Close" @click="editing = null">
                            <X class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" title="Delete" @click="destroy(section)">
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>
                </CardHeader>

                <CardContent v-if="editing === section.id" class="space-y-4 border-t pt-4">
                    <div class="space-y-2">
                        <Label :for="`title-${section.id}`">Title</Label>
                        <Input :id="`title-${section.id}`" v-model="editForm.title" />
                        <InputError :message="editForm.errors.title" />
                        <p v-if="section.is_published" class="text-xs text-muted-foreground">
                            The link <code>#{{ section.slug }}</code> stays fixed while published, so existing links keep
                            working.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label :for="`summary-${section.id}`">Summary <span class="text-muted-foreground">(optional)</span></Label>
                        <Input :id="`summary-${section.id}`" v-model="editForm.summary" />
                        <InputError :message="editForm.errors.summary" />
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label :for="`body-${section.id}`">Body (Markdown)</Label>
                            <Button variant="ghost" size="sm" @click="showPreview = !showPreview">
                                <Eye class="mr-2 h-4 w-4" />
                                {{ showPreview ? 'Hide preview' : 'Preview' }}
                            </Button>
                        </div>
                        <div :class="showPreview ? 'grid gap-4 lg:grid-cols-2' : ''">
                            <Textarea
                                :id="`body-${section.id}`"
                                v-model="editForm.body"
                                :rows="16"
                                class="font-mono text-sm"
                            />
                            <div v-if="showPreview" class="relative min-h-32 rounded-md border bg-muted/30 p-4">
                                <Loader2 v-if="previewing" class="absolute top-2 right-2 h-4 w-4 animate-spin text-muted-foreground" />
                                <div v-if="previewHtml" class="manual-prose" v-html="previewHtml" />
                                <p v-else class="text-sm text-muted-foreground">Start typing to see a preview.</p>
                            </div>
                        </div>
                        <InputError :message="editForm.errors.body" />
                        <p class="text-xs text-muted-foreground">
                            Supports <code>## headings</code>, <code>**bold**</code>, <code>- lists</code>,
                            <code>1. numbered lists</code>, <code>[links](/billing)</code> and tables. Raw HTML is
                            removed.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox :id="`published-${section.id}`" v-model="editForm.is_published" />
                        <Label :for="`published-${section.id}`" class="font-normal">
                            Published (visible on the public manual)
                        </Label>
                    </div>

                    <!-- Images -->
                    <div class="space-y-3 border-t pt-4">
                        <div class="flex items-center justify-between">
                            <Label>Images</Label>
                            <Button variant="outline" size="sm" as-child :disabled="uploadingFor === section.id">
                                <label class="cursor-pointer">
                                    <Loader2 v-if="uploadingFor === section.id" class="mr-2 h-4 w-4 animate-spin" />
                                    <ImagePlus v-else class="mr-2 h-4 w-4" />
                                    Upload image
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/gif,image/webp"
                                        class="hidden"
                                        @change="uploadImage(section, $event)"
                                    />
                                </label>
                            </Button>
                        </div>

                        <p v-if="!section.images.length" class="text-sm text-muted-foreground">
                            No images yet. JPG, PNG, GIF or WebP up to 4&nbsp;MB.
                        </p>

                        <div v-else class="grid gap-4 sm:grid-cols-2">
                            <div v-for="image in section.images" :key="image.id" class="space-y-2 rounded-lg border p-2">
                                <img :src="image.url" :alt="image.caption ?? ''" class="h-36 w-full rounded bg-muted object-contain" />
                                <div class="flex gap-2">
                                    <Input
                                        :model-value="image.caption ?? ''"
                                        placeholder="Caption (optional)"
                                        class="h-8 text-xs"
                                        @blur="saveCaption(image, ($event.target as HTMLInputElement).value)"
                                    />
                                    <Button variant="ghost" size="icon" class="h-8 w-8 shrink-0" @click="deleteImage(image)">
                                        <Trash2 class="h-3.5 w-3.5 text-destructive" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 border-t pt-4">
                        <Button :disabled="editForm.processing" @click="save(section)">Save changes</Button>
                        <Button variant="ghost" @click="editing = null">Cancel</Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AdminSettingsLayout>
</template>

<style scoped>
.manual-prose :deep(> * + *) {
    margin-top: 0.75rem;
}
.manual-prose :deep(h1) {
    font-size: 1.35rem;
    font-weight: 600;
}
.manual-prose :deep(h2) {
    font-size: 1.15rem;
    font-weight: 600;
}
.manual-prose :deep(h3) {
    font-size: 1rem;
    font-weight: 600;
}
.manual-prose :deep(p),
.manual-prose :deep(li) {
    font-size: 0.875rem;
    line-height: 1.65;
}
.manual-prose :deep(ul) {
    list-style: disc;
    padding-left: 1.25rem;
}
.manual-prose :deep(ol) {
    list-style: decimal;
    padding-left: 1.25rem;
}
.manual-prose :deep(a) {
    color: var(--primary);
    text-decoration: underline;
}
.manual-prose :deep(strong) {
    font-weight: 600;
}
.manual-prose :deep(code) {
    background: var(--muted);
    border-radius: 0.25rem;
    padding: 0.1rem 0.3rem;
    font-size: 0.8em;
}
.manual-prose :deep(table) {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
    display: block;
    overflow-x: auto;
}
.manual-prose :deep(th),
.manual-prose :deep(td) {
    border: 1px solid var(--border);
    padding: 0.3rem 0.5rem;
    text-align: left;
}
.manual-prose :deep(img) {
    max-width: 100%;
}
</style>
