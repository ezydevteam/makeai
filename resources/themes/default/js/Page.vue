<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Layout from '@themes/default/js/Layouts/AppLayout.vue';
import ContactForm from '@themes/default/js/Components/ContactForm.vue';
import ContactInfoPanel from '@themes/default/js/Components/ContactInfoPanel.vue';
import FaqAccordion from '@themes/default/js/Components/FaqAccordion.vue';
import FaqBlock from '@themes/default/js/Components/FaqBlock.vue';
import AppSidebar from '@themes/default/js/Components/AppSidebar.vue';
import { useTranslate } from '@/Composables/useTranslate';
import { mediaUrl } from '@/lib/media'

type ContentBlock =
    | { type: 'html'; html: string }
    | { type: 'faqs'; props: Record<string, any> };

const props = defineProps<{
    page: any
    seo?: any
    contactSettings?: any
    contactChannels?: any[] | null
    contentBlocks?: ContentBlock[]
}>();

const { t } = useTranslate();

// Site-free title portion, used for both OG/Twitter titles and the <title> child
// (the global callback in app.ts appends " | <site>" to the latter). seo.title is
// the full document title and is consumed only by app.blade.php's server-rendered
// <title inertia> — putting it in <Head> here would double the site name.
const pageTitle = computed(() => props.page.meta_title || props.page.title);
const pageDescription = computed(() => props.seo?.description || props.page.meta_description || props.page.excerpt || '');
const pageKeywords = computed(() => props.seo?.keywords || props.page.meta_keywords || '');
const canonicalUrl = computed(() => props.seo?.canonical || '');
const robots = computed(() => props.seo?.robots || 'index,follow');
const ogImage = computed(() => props.seo?.og_image || (props.page.og_image ? mediaUrl(props.page.og_image) : ''));
const schemaJson = computed(() => props.seo?.schema ? JSON.stringify(props.seo.schema) : '');

// container_width is stored as either a CSS length ('1080px', '1536px'), 'full', or the
// semantic 'default'. 'default' (and any empty/legacy value) resolves to the standard
// 1280px — critically, 'default' is NOT valid CSS, so it must be mapped here rather than
// passed straight into max-width.
// Server-split content. Falls back to a single html block so a page rendered from a
// cached Inertia response without the prop still shows its content.
const blocks = computed<ContentBlock[]>(() =>
    props.contentBlocks ?? [{ type: 'html', html: props.page?.content ?? '' }]
);

// The legacy `/faq` page parses its own <h3> markup into an accordion. Skip that path
// once the page uses a shortcode, so the two renderers can't both claim the content.
const usesShortcodes = computed(() => blocks.value.some((block) => block.type !== 'html'));

const pageMaxWidth = computed(() => {
    const width = props.page?.container_width;
    if (width === 'full') return '100%';
    if (!width || width === 'default') return '1280px';
    return width;
});
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <meta v-if="pageDescription" name="description" :content="pageDescription">
        <meta v-if="pageKeywords" name="keywords" :content="pageKeywords">
        <meta name="robots" :content="robots">
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl">
        <meta property="og:type" content="website">
        <meta property="og:title" :content="pageTitle">
        <meta v-if="pageDescription" property="og:description" :content="pageDescription">
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl">
        <meta v-if="ogImage" property="og:image" :content="ogImage">
        <meta name="twitter:card" :content="ogImage ? 'summary_large_image' : 'summary'">
        <meta name="twitter:title" :content="pageTitle">
        <meta v-if="pageDescription" name="twitter:description" :content="pageDescription">
        <meta v-if="ogImage" name="twitter:image" :content="ogImage">
        <component :is="'script'" v-if="schemaJson" type="application/ld+json" v-html="schemaJson" />
    </Head>

    <Layout>
        <div class="w-full pt-6 md:pt-10 pb-12">
            <div :style="{ '--page-width': pageMaxWidth, maxWidth: pageMaxWidth }" class="mx-auto px-4 sm:px-6">

                <div class="flex flex-col lg:flex-row gap-12">
                    <!-- Main Content -->
                    <div :class="[page.show_sidebar ? 'lg:w-2/3' : 'w-full']" :style="{ order: page.sidebar_position === 'left' ? 2 : 1 }">
                        <article class="prose prose-lg prose-indigo dark:prose-invert max-w-none">
                            <header v-if="page.show_title || page.show_breadcrumbs || (page.show_excerpt && page.excerpt)" class="mb-12" :class="{ 'text-center flex flex-col items-center': page.center_title }">
                                <div v-if="page.show_breadcrumbs" class="flex items-center gap-2 text-xs text-gray-400 mb-3" :class="{ 'justify-center': page.center_title }">
                                    <Link href="/" class="hover:text-primary-600 transition-colors">{{ t('Home') }}</Link>
                                    <template v-if="page.parent">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                        <Link :href="route('page.show', page.parent.slug)" class="hover:text-primary-600 transition-colors">{{ page.parent.title }}</Link>
                                    </template>
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                    <span class="text-gray-900 dark:text-gray-100">{{ page.title }}</span>
                                </div>
                                <h1 v-if="page.show_title" class="text-2xl md:text-4xl font-black text-gray-900 dark:text-white leading-tight">
                                    {{ page.title }}
                                </h1>
                                <p v-if="page.show_excerpt && page.excerpt" class="not-prose mt-4 max-w-2xl whitespace-pre-line text-base leading-relaxed text-gray-500 dark:text-gray-400">
                                    {{ page.excerpt }}
                                </p>
                            </header>

                            <div v-if="page.show_featured_image && page.featured_image" class="mb-12 rounded-3xl overflow-hidden shadow-2xl shadow-gray-200/50">
                                <img :src="mediaUrl(page.featured_image)" :alt="page.title" class="w-full h-auto object-cover">
                            </div>

                            <!-- Contact: intro copy and reachable channels on the left, the form
                                 alongside it. Collapses to a single column below lg. -->
                            <div v-if="page.slug === 'contact'" class="not-prose">
                                <div class="grid gap-10 lg:grid-cols-12 lg:items-start lg:gap-12">
                                    <div class="lg:col-span-5 lg:sticky lg:top-24 space-y-8">
                                        <template v-for="(block, i) in blocks" :key="i">
                                            <div v-if="block.type === 'html'" v-html="block.html" class="cms-content text-gray-700 dark:text-gray-300 leading-relaxed"></div>
                                            <FaqBlock v-else-if="block.type === 'faqs'" v-bind="block.props" />
                                        </template>
                                        <ContactInfoPanel :channels="contactChannels" />
                                    </div>

                                    <div class="lg:col-span-7">
                                        <ContactForm :settings="contactSettings" />
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="page.slug === 'faq' && !usesShortcodes" class="not-prose">
                                <FaqAccordion :content="page.content" />
                            </div>
                            <template v-else>
                                <template v-for="(block, i) in blocks" :key="i">
                                    <div v-if="block.type === 'html'" v-html="block.html" class="cms-content text-gray-700 dark:text-gray-300 leading-relaxed space-y-6"></div>
                                    <FaqBlock v-else-if="block.type === 'faqs'" v-bind="block.props" />
                                </template>
                            </template>
                        </article>
                    </div>

                    <!-- Sidebar -->
                    <div v-if="page.show_sidebar" class="lg:w-1/3" :style="{ order: page.sidebar_position === 'left' ? 1 : 2 }">
                        <AppSidebar />
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>

<style>
@reference "../../../css/app.css";

/* Base Styles for CMS Content */
.cms-content h2 { @apply text-xl md:text-2xl font-bold text-gray-900 dark:text-white mt-12 mb-6; }
.cms-content h3 { @apply text-lg md:text-xl font-bold text-gray-900 dark:text-white mt-8 mb-4; }
.cms-content h4 { @apply text-lg md:text-lg font-bold text-gray-900 dark:text-white mt-8 mb-4; }
.cms-content h5 { @apply text-sm md:text-base font-bold text-gray-900 dark:text-white mt-6 mb-3; }
.cms-content p { @apply text-sm md:text-base text-gray-700 dark:text-gray-300 leading-relaxed mb-6; }
.cms-content ul { @apply list-disc list-inside space-y-3 mb-6 ml-4; }
.cms-content ol { @apply list-decimal list-inside space-y-3 mb-6 ml-4; }
.cms-content a { @apply text-primary-600 dark:text-primary-400 font-bold border-b-2 border-primary-100 dark:border-primary-950 hover:border-primary-600 dark:hover:border-primary-400 transition-all; }
.cms-content img { @apply rounded-3xl shadow-lg my-12; }
.cms-content blockquote { @apply border-l-4 border-primary-500 pl-6 italic text-gray-500 dark:text-gray-400 my-12; }
.cms-content pre { @apply overflow-x-auto rounded-2xl bg-gray-950 p-6 text-sm text-gray-100 my-8; }
.cms-content code { @apply rounded bg-gray-100 dark:bg-surface-800 px-1.5 py-0.5 text-sm text-gray-800 dark:text-gray-200; }
.cms-content pre code { @apply bg-transparent p-0 text-gray-100; }
.cms-content table { @apply w-full border-collapse overflow-hidden rounded-2xl my-8 text-sm; }
.cms-content th { @apply bg-gray-50 dark:bg-surface-800 text-left font-bold text-gray-700 dark:text-gray-200 border border-gray-100 dark:border-surface-800 px-4 py-3; }
.cms-content td { @apply border border-gray-100 dark:border-surface-800 px-4 py-3 text-gray-700 dark:text-gray-300; }
.cms-content hr { @apply my-10 border-gray-100 dark:border-surface-800; }
</style>
