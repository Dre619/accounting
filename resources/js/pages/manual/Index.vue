<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { BookOpen, Menu, X } from 'lucide-vue-next';
import { dashboard, login, register } from '@/routes';

interface ManualImage {
    url: string;
    caption: string | null;
}

interface Section {
    slug: string;
    title: string;
    summary: string | null;
    body_html: string;
    images: ManualImage[];
}

defineProps<{
    sections: Section[];
    updatedAt: string | null;
}>();

const page = usePage();
const isAuthenticated = computed(() => Boolean((page.props.auth as { user: unknown } | undefined)?.user));

const activeSlug = ref<string | null>(null);
const tocOpen = ref(false);

let observer: IntersectionObserver | null = null;

onMounted(() => {
    const headings = Array.from(document.querySelectorAll('[data-section]'));
    if (!headings.length) return;

    // Highlight whichever section currently occupies the top of the viewport.
    observer = new IntersectionObserver(
        (entries) => {
            const visible = entries
                .filter((e) => e.isIntersecting)
                .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

            if (visible.length) {
                activeSlug.value = visible[0].target.getAttribute('data-section');
            }
        },
        { rootMargin: '-80px 0px -70% 0px', threshold: 0 },
    );

    headings.forEach((h) => observer?.observe(h));
});

onBeforeUnmount(() => observer?.disconnect());

function jumpTo(slug: string) {
    tocOpen.value = false;
    document.getElementById(slug)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function fmt(date: string) {
    return new Date(date).toLocaleDateString('en-ZM', { day: 'numeric', month: 'long', year: 'numeric' });
}
</script>

<template>
    <Head title="User Manual — CloudOne Accounting">
        <meta
            name="description"
            content="How to use CloudOne Accounting: invoicing, bills, payments, VAT and ZRA Smart Invoice, payroll and reports."
        />
    </Head>

    <div class="min-h-screen bg-background text-foreground">
        <!-- Brand header, matching the public marketing pages -->
        <header class="sticky top-0 z-30" style="background: #0f2044; border-bottom: 1px solid #1a3060">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <Link href="/" class="flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg" style="background: #f97316">
                        <BookOpen class="h-4 w-4 text-white" />
                    </div>
                    <span class="text-lg font-bold tracking-tight text-white">CloudOne Accounting</span>
                </Link>

                <nav class="flex items-center gap-3 text-sm">
                    <Link
                        v-if="isAuthenticated"
                        :href="dashboard.url()"
                        class="rounded-md px-3 py-1.5 font-medium text-white transition-opacity hover:opacity-80"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link :href="login.url()" class="px-3 py-1.5 font-medium text-white/80 transition-colors hover:text-white">
                            Log in
                        </Link>
                        <Link
                            :href="register.url()"
                            class="rounded-md px-3 py-1.5 font-medium text-white"
                            style="background: #f97316"
                        >
                            Get started
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:py-12">
            <!-- Title -->
            <div class="mb-8 border-b pb-8">
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">User Manual</h1>
                <p class="mt-2 max-w-2xl text-muted-foreground">
                    Everything you need to run your books on CloudOne Accounting — from setting up your company to
                    filing VAT.
                </p>
                <p v-if="updatedAt" class="mt-3 text-xs text-muted-foreground">Last updated {{ fmt(updatedAt) }}</p>
            </div>

            <!-- Empty state -->
            <div v-if="!sections.length" class="rounded-lg border border-dashed py-20 text-center">
                <p class="text-muted-foreground">The user manual hasn't been published yet. Please check back soon.</p>
            </div>

            <div v-else class="lg:flex lg:gap-12">
                <!-- Mobile contents toggle -->
                <div class="mb-6 lg:hidden">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-lg border px-4 py-3 text-sm font-medium"
                        @click="tocOpen = !tocOpen"
                    >
                        <span class="flex items-center gap-2">
                            <Menu v-if="!tocOpen" class="h-4 w-4" />
                            <X v-else class="h-4 w-4" />
                            Contents
                        </span>
                        <span class="text-xs text-muted-foreground">{{ sections.length }} sections</span>
                    </button>
                    <nav v-if="tocOpen" class="mt-2 flex flex-col gap-0.5 rounded-lg border p-2">
                        <button
                            v-for="section in sections"
                            :key="section.slug"
                            type="button"
                            class="rounded-md px-3 py-2 text-left text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            @click="jumpTo(section.slug)"
                        >
                            {{ section.title }}
                        </button>
                    </nav>
                </div>

                <!-- Table of contents -->
                <aside class="hidden shrink-0 lg:block lg:w-60">
                    <nav class="sticky top-24 flex max-h-[calc(100vh-8rem)] flex-col gap-0.5 overflow-y-auto pr-2">
                        <p class="mb-2 px-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Contents
                        </p>
                        <button
                            v-for="section in sections"
                            :key="section.slug"
                            type="button"
                            class="rounded-md border-l-2 px-3 py-1.5 text-left text-sm transition-colors"
                            :class="
                                activeSlug === section.slug
                                    ? 'border-l-primary bg-muted font-medium text-foreground'
                                    : 'border-l-transparent text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                            "
                            @click="jumpTo(section.slug)"
                        >
                            {{ section.title }}
                        </button>
                    </nav>
                </aside>

                <!-- Sections -->
                <main class="min-w-0 flex-1 space-y-16">
                    <section
                        v-for="section in sections"
                        :id="section.slug"
                        :key="section.slug"
                        :data-section="section.slug"
                        class="scroll-mt-24"
                    >
                        <h2 class="text-2xl font-bold tracking-tight">{{ section.title }}</h2>
                        <p v-if="section.summary" class="mt-1.5 text-muted-foreground">{{ section.summary }}</p>

                        <!-- Body is Markdown rendered and HTML-stripped server-side -->
                        <div v-if="section.body_html" class="manual-prose mt-5" v-html="section.body_html" />

                        <figure v-for="(image, i) in section.images" :key="i" class="mt-6">
                            <img
                                :src="image.url"
                                :alt="image.caption ?? section.title"
                                loading="lazy"
                                class="w-full rounded-lg border bg-muted"
                            />
                            <figcaption v-if="image.caption" class="mt-2 text-center text-sm text-muted-foreground">
                                {{ image.caption }}
                            </figcaption>
                        </figure>
                    </section>

                    <div class="border-t pt-8 text-sm text-muted-foreground">
                        Still stuck? Email
                        <a href="mailto:support@cloudone.co.zm" class="underline">support@cloudone.co.zm</a> and we'll
                        help you out.
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Styles for server-rendered Markdown. Hand-rolled because the project does not
   include @tailwindcss/typography. */
.manual-prose :deep(> * + *) {
    margin-top: 1rem;
}

.manual-prose :deep(h1),
.manual-prose :deep(h2),
.manual-prose :deep(h3),
.manual-prose :deep(h4) {
    font-weight: 600;
    line-height: 1.3;
    margin-top: 2rem;
    scroll-margin-top: 6rem;
}

.manual-prose :deep(h1) {
    font-size: 1.5rem;
}
.manual-prose :deep(h2) {
    font-size: 1.25rem;
}
.manual-prose :deep(h3) {
    font-size: 1.075rem;
}
.manual-prose :deep(h4) {
    font-size: 1rem;
}

.manual-prose :deep(p),
.manual-prose :deep(li) {
    line-height: 1.7;
}

.manual-prose :deep(ul),
.manual-prose :deep(ol) {
    padding-left: 1.5rem;
}

.manual-prose :deep(ul) {
    list-style: disc;
}
.manual-prose :deep(ol) {
    list-style: decimal;
}

.manual-prose :deep(li + li) {
    margin-top: 0.375rem;
}

.manual-prose :deep(li > ul),
.manual-prose :deep(li > ol) {
    margin-top: 0.375rem;
}

.manual-prose :deep(a) {
    color: var(--primary);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.manual-prose :deep(strong) {
    font-weight: 600;
}

.manual-prose :deep(code) {
    background: var(--muted);
    border-radius: 0.25rem;
    padding: 0.125rem 0.375rem;
    font-size: 0.875em;
}

.manual-prose :deep(pre) {
    background: var(--muted);
    border-radius: 0.5rem;
    padding: 1rem;
    overflow-x: auto;
}

.manual-prose :deep(pre code) {
    background: transparent;
    padding: 0;
}

.manual-prose :deep(blockquote) {
    border-left: 3px solid var(--border);
    padding-left: 1rem;
    color: var(--muted-foreground);
}

.manual-prose :deep(hr) {
    border-color: var(--border);
    margin: 2rem 0;
}

.manual-prose :deep(table) {
    width: 100%;
    border-collapse: collapse;
    display: block;
    overflow-x: auto;
}

.manual-prose :deep(th),
.manual-prose :deep(td) {
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    text-align: left;
}

.manual-prose :deep(th) {
    background: var(--muted);
    font-weight: 600;
}

.manual-prose :deep(img) {
    max-width: 100%;
    border-radius: 0.5rem;
}
</style>
