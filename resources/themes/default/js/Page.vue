<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Layout from '@themes/default/js/Layouts/AppLayout.vue';
import ContactForm from '@themes/default/js/Components/ContactForm.vue';
import FaqAccordion from '@themes/default/js/Components/FaqAccordion.vue';
import AppSidebar from '@themes/default/js/Components/AppSidebar.vue';
import { useTranslate } from '@/Composables/useTranslate';
import { mediaUrl } from '@/lib/media'

const props = defineProps<{
    page: any
    seo?: any
    contactSettings?: any
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
            <div :style="{ '--page-width': page.container_width === 'full' ? '100%' : (page.container_width || '1280px'), maxWidth: page.container_width === 'full' ? '100%' : (page.container_width || '1280px') }" class="mx-auto px-4 sm:px-6">

                <div class="flex flex-col lg:flex-row gap-12">
                    <!-- Main Content -->
                    <div :class="[page.show_sidebar ? 'lg:w-2/3' : 'w-full']" :style="{ order: page.sidebar_position === 'left' ? 2 : 1 }">
                        <article class="prose prose-lg prose-indigo dark:prose-invert max-w-none">
                            <header v-if="page.show_title || page.show_breadcrumbs" class="mb-12" :class="{ 'text-center flex flex-col items-center': page.center_title }">
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
                            </header>

                            <div v-if="page.show_featured_image && page.featured_image" class="mb-12 rounded-3xl overflow-hidden shadow-2xl shadow-gray-200/50">
                                <img :src="mediaUrl(page.featured_image)" :alt="page.title" class="w-full h-auto object-cover">
                            </div>

                            <div v-if="page.slug === 'contact'" class="not-prose">
                                <div v-html="page.content" class="cms-content text-gray-700 dark:text-gray-300 leading-relaxed mb-12"></div>
                                <ContactForm :settings="contactSettings" />
                            </div>
                            <div v-else-if="page.slug === 'faq'" class="not-prose">
                                <FaqAccordion :content="page.content" />
                            </div>
                            <div v-else v-html="page.content" class="cms-content text-gray-700 dark:text-gray-300 leading-relaxed space-y-6"></div>
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
.cms-content h2 { @apply text-2xl md:text-3xl font-black text-gray-900 dark:text-white mt-12 mb-6; }
.cms-content h3 { @apply text-xl md:text-2xl font-bold text-gray-900 dark:text-white mt-8 mb-4; }
.cms-content h4 { @apply text-lg md:text-xl font-bold text-gray-900 dark:text-white mt-8 mb-4; }
.cms-content h5 { @apply text-base md:text-lg font-bold text-gray-900 dark:text-white mt-6 mb-3; }
.cms-content p { @apply text-base md:text-lg text-gray-700 dark:text-gray-300 leading-relaxed mb-6; }
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
