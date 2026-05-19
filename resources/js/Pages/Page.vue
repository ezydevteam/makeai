<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Layout from '@/Layouts/AppLayout.vue';
import ContactForm from '@/Components/ContactForm.vue';
import FaqAccordion from '@/Components/FaqAccordion.vue';
import AppSidebar from '@/Components/AppSidebar.vue';

const props = defineProps<{
    page: any
    seo?: any
    contactSettings?: any
}>();

const pageTitle = computed(() => props.seo?.title || props.page.meta_title || props.page.title);
const pageDescription = computed(() => props.seo?.description || props.page.meta_description || props.page.excerpt || '');
const pageKeywords = computed(() => props.seo?.keywords || props.page.meta_keywords || '');
const canonicalUrl = computed(() => props.seo?.canonical || '');
const robots = computed(() => props.seo?.robots || 'index,follow');
const ogImage = computed(() => props.seo?.og_image || (props.page.og_image ? `/storage/${props.page.og_image}` : ''));
const schemaJson = computed(() => props.seo?.schema ? JSON.stringify(props.seo.schema) : '');
const isBlank = computed(() => props.page.template === 'blank');
const containerClass = computed(() => {
    if (props.page.template === 'full_width' || props.page.container_width === 'full') return 'max-w-full';
    if (props.page.template === 'landing' || props.page.container_width === 'wide') return 'max-w-7xl';
    if (props.page.container_width === 'narrow') return 'max-w-3xl';
    return 'max-w-5xl';
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
        <div :class="[containerClass, isBlank ? 'px-0 py-0' : 'px-6 py-12 md:py-20']" class="mx-auto bg-white">
            
            <div class="flex flex-col lg:flex-row gap-12">
                <!-- Main Content -->
                <div :class="[page.show_sidebar ? 'lg:w-2/3' : 'w-full']" :style="{ order: page.sidebar_position === 'left' ? 2 : 1 }">
                    <article class="prose prose-lg prose-indigo max-w-none">
                        <header v-if="page.show_title" class="mb-12">
                            <h1 class="text-4xl md:text-5xl font-black text-gray-900 leading-tight mb-4">
                                {{ page.title }}
                            </h1>
                            <div v-if="page.show_breadcrumbs" class="flex items-center gap-2 text-xs font-bold text-gray-400 uppercase tracking-widest">
                                <Link href="/" class="hover:text-primary-600 transition-colors">Home</Link>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                <span class="text-gray-900">{{ page.title }}</span>
                            </div>
                        </header>

                        <div v-if="page.show_featured_image && page.featured_image" class="mb-12 rounded-3xl overflow-hidden shadow-2xl shadow-gray-200/50">
                            <img :src="'/storage/' + page.featured_image" :alt="page.title" class="w-full h-auto object-cover">
                        </div>

                        <div v-if="page.slug === 'contact'" class="not-prose">
                            <div v-html="page.content" class="cms-content text-gray-700 leading-relaxed mb-12"></div>
                            <ContactForm :settings="contactSettings" />
                        </div>
                        <div v-else-if="page.slug === 'faq'" class="not-prose">
                            <FaqAccordion :content="page.content" />
                        </div>
                        <div v-else v-html="page.content" class="cms-content text-gray-700 leading-relaxed space-y-6"></div>
                    </article>
                </div>

                <!-- Sidebar -->
                <div v-if="page.show_sidebar" class="lg:w-1/3" :style="{ order: page.sidebar_position === 'left' ? 1 : 2 }">
                    <AppSidebar />
                </div>
            </div>
        </div>
    </Layout>
</template>

<style>
@reference "../../css/app.css";

/* Base Styles for CMS Content */
.cms-content h2 { @apply text-3xl font-black text-gray-900 mt-12 mb-6; }
.cms-content h3 { @apply text-2xl font-bold text-gray-900 mt-8 mb-4; }
.cms-content h4 { @apply text-xl font-bold text-gray-900 mt-8 mb-4; }
.cms-content h5 { @apply text-lg font-bold text-gray-900 mt-6 mb-3; }
.cms-content p { @apply text-lg text-gray-700 leading-relaxed mb-6; }
.cms-content ul { @apply list-disc list-inside space-y-3 mb-6 ml-4; }
.cms-content ol { @apply list-decimal list-inside space-y-3 mb-6 ml-4; }
.cms-content a { @apply text-primary-600 font-bold border-b-2 border-primary-100 hover:border-primary-600 transition-all; }
.cms-content img { @apply rounded-3xl shadow-lg my-12; }
.cms-content blockquote { @apply border-l-4 border-primary-500 pl-6 italic text-gray-500 my-12; }
.cms-content pre { @apply overflow-x-auto rounded-2xl bg-gray-950 p-6 text-sm text-gray-100 my-8; }
.cms-content code { @apply rounded bg-gray-100 px-1.5 py-0.5 text-sm text-gray-800; }
.cms-content pre code { @apply bg-transparent p-0 text-gray-100; }
.cms-content table { @apply w-full border-collapse overflow-hidden rounded-2xl my-8 text-sm; }
.cms-content th { @apply bg-gray-50 text-left font-bold text-gray-700 border border-gray-100 px-4 py-3; }
.cms-content td { @apply border border-gray-100 px-4 py-3 text-gray-700; }
.cms-content hr { @apply my-10 border-gray-100; }
</style>
